<?php

declare(strict_types=1);

namespace Printmelo;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Utils;
use Printmelo\Exceptions\ApiException;
use Printmelo\Exceptions\RateLimitException;
use Printmelo\Exceptions\ServerException;
use Printmelo\Exceptions\TransportException;
use Printmelo\Resources\Account;
use Printmelo\Services\ChildAccounts;
use Printmelo\Services\Computers;
use Printmelo\Services\PairingCodes;
use Printmelo\Services\PrinterGroups;
use Printmelo\Services\Printers;
use Printmelo\Services\PrintJobs;
use Printmelo\Services\Webhooks;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * The entry point of the SDK.
 *
 * ```php
 * $printmelo = new Printmelo\Printmelo('pm_live_...');
 * $job = $printmelo->printJobs->printPdfUrl(12, 'https://example.com/label.pdf', 'Label 10482');
 * ```
 *
 * @property-read Computers $computers
 * @property-read Printers $printers
 * @property-read PrintJobs $printJobs
 * @property-read PrinterGroups $printerGroups
 * @property-read PairingCodes $pairingCodes
 * @property-read ChildAccounts $childAccounts
 * @property-read Webhooks $webhooks
 */
class Printmelo
{
    public const VERSION = '1.0.0';

    public const DEFAULT_BASE_URL = 'https://api.printmelo.com/api/v1';

    private string $apiKey;

    private string $baseUrl;

    private ClientInterface $httpClient;

    private int $maxRetries;

    private ?string $locale;

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    /** @var callable(int): void */
    private $sleeper;

    /** @var array<string, object> */
    private array $services = [];

    /**
     * @param  array{
     *     base_url?: string,
     *     timeout?: float|int,
     *     connect_timeout?: float|int,
     *     max_retries?: int,
     *     locale?: string|null,
     *     http_client?: ClientInterface,
     *     headers?: array<string, string>,
     *     sleeper?: callable(int): void,
     * }  $options
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (trim($apiKey) === '') {
            throw new \InvalidArgumentException('A Printmelo API key is required. Create one in the dashboard under API keys.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->maxRetries = max(0, (int) ($options['max_retries'] ?? 2));
        $this->locale = $options['locale'] ?? null;
        $this->defaultHeaders = $options['headers'] ?? [];
        $this->sleeper = $options['sleeper'] ?? static function (int $milliseconds): void {
            usleep($milliseconds * 1000);
        };
        $this->httpClient = $options['http_client'] ?? new GuzzleClient([
            'timeout' => $options['timeout'] ?? 30,
            'connect_timeout' => $options['connect_timeout'] ?? 10,
            'http_errors' => false,
        ]);
    }

    public function __get(string $name): object
    {
        $classes = [
            'computers' => Computers::class,
            'printers' => Printers::class,
            'printJobs' => PrintJobs::class,
            'printerGroups' => PrinterGroups::class,
            'pairingCodes' => PairingCodes::class,
            'childAccounts' => ChildAccounts::class,
            'webhooks' => Webhooks::class,
        ];

        if (! isset($classes[$name])) {
            throw new \OutOfRangeException("Unknown Printmelo service [{$name}].");
        }

        return $this->services[$name] ??= new $classes[$name]($this);
    }

    /**
     * The account of this API key, with its plan, usage and the scopes of the key.
     */
    public function whoami(): Account
    {
        return new Account($this->request('GET', 'whoami')['data']);
    }

    /**
     * A copy of this client that works on behalf of a child account, by its id or uuid.
     * Only for integrators. Usage counts towards the integrator's subscription.
     */
    public function forChildAccount(int|string $childAccountId): static
    {
        return $this->withHeaders(['X-Child-Account-Id' => (string) $childAccountId]);
    }

    /**
     * A copy of this client that works on behalf of the child account with this reference.
     */
    public function forChildReference(string $reference): static
    {
        return $this->withHeaders(['X-Child-Account-Reference' => $reference]);
    }

    /**
     * A copy of this client with messages in this language, such as `en` or `nl`.
     */
    public function withLocale(string $locale): static
    {
        $clone = clone $this;
        $clone->locale = $locale;

        return $clone;
    }

    /**
     * @param  array<string, string>  $headers
     */
    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->defaultHeaders = array_merge($this->defaultHeaders, $headers);

        return $clone;
    }

    public function __clone()
    {
        $this->services = [];
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Send a request to the API and return the decoded JSON body.
     *
     * Retries connection errors, `429 rate_limited` and 5xx responses with an increasing delay.
     * A POST is only retried when it carries an `Idempotency-Key`, so a print job never prints twice.
     *
     * @param  array<string, mixed>|null  $body
     * @param  array<string, scalar|null>  $query
     * @param  array<string, string>  $headers
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, array $query = [], array $headers = []): array
    {
        $method = strtoupper($method);
        $request = $this->buildRequest($method, $path, $body, $query, $headers);
        $retryable = $method !== 'POST' || $request->hasHeader('Idempotency-Key');

        for ($attempt = 0; ; $attempt++) {
            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (ClientExceptionInterface $e) {
                if ($retryable && $attempt < $this->maxRetries) {
                    ($this->sleeper)($this->backoff($attempt));

                    continue;
                }

                throw new TransportException('Could not reach Printmelo: '.$e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();

            if ($status < 400) {
                return $this->decode($response);
            }

            $exception = ApiException::fromResponse($response, $this->decodeError($response));
            $transient = $exception instanceof ServerException
                || ($exception instanceof RateLimitException && $exception->getErrorCode() === 'rate_limited');

            if ($retryable && $transient && $attempt < $this->maxRetries) {
                ($this->sleeper)($this->backoff($attempt, $response));

                continue;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, scalar|null>  $query
     * @param  array<string, string>  $headers
     */
    private function buildRequest(string $method, string $path, ?array $body, array $query, array $headers): Request
    {
        $query = array_filter($query, static fn ($value) => $value !== null);
        $query = array_map(static fn ($value) => is_bool($value) ? ($value ? 'true' : 'false') : $value, $query);
        $url = $this->baseUrl.'/'.ltrim($path, '/').($query === [] ? '' : '?'.http_build_query($query));

        $headers = array_merge([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'printmelo-php/'.self::VERSION.' PHP/'.PHP_VERSION,
        ], $this->locale !== null ? ['Accept-Language' => $this->locale] : [], $this->defaultHeaders, $headers);

        $request = new Request($method, $url, $headers);

        if ($body !== null) {
            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody(Utils::streamFor(json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)));
        }

        return $request;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $contents = (string) $response->getBody();

        if ($contents === '') {
            return [];
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new ApiException('Printmelo returned a response that is not valid JSON.', $response->getStatusCode(), null, [], $response, $e);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeError(ResponseInterface $response): array
    {
        $decoded = json_decode((string) $response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function backoff(int $attempt, ?ResponseInterface $response = null): int
    {
        $retryAfter = $response?->getHeaderLine('Retry-After');

        if ($retryAfter !== null && ctype_digit($retryAfter)) {
            return min((int) $retryAfter, 60) * 1000;
        }

        return (int) (500 * (2 ** $attempt));
    }
}
