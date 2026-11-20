<?php

declare(strict_types=1);

namespace Printmelo\Tests;

use GuzzleHttp\Psr7\Response;
use Printmelo\Printmelo;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    /** @var list<RequestInterface> */
    protected array $requests = [];

    /** @var list<ResponseInterface|\Throwable> */
    protected array $responses = [];

    /** @var list<int> */
    protected array $sleeps = [];

    /**
     * @param  array<string, mixed>  $options
     */
    protected function client(array $options = []): Printmelo
    {
        $test = $this;

        $http = new class($test) implements ClientInterface {
            public function __construct(private TestCase $test)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                return $this->test->handle($request);
            }
        };

        return new Printmelo('pm_test_key', $options + [
            'http_client' => $http,
            'base_url' => 'https://api.printmelo.test/api/v1',
            'sleeper' => function (int $ms): void {
                $this->sleeps[] = $ms;
            },
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $body
     * @param  array<string, string>  $headers
     */
    protected function respond(int $status, ?array $body = null, array $headers = []): void
    {
        $this->responses[] = new Response($status, $headers + ['Content-Type' => 'application/json'], $body === null ? '' : json_encode($body));
    }

    protected function failWith(\Throwable $e): void
    {
        $this->responses[] = $e;
    }

    /**
     * @internal
     */
    public function handle(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;
        $response = array_shift($this->responses) ?? throw new \LogicException('No response queued for '.$request->getMethod().' '.$request->getUri());

        if ($response instanceof \Throwable) {
            throw $response;
        }

        return $response;
    }

    protected function lastRequest(): RequestInterface
    {
        return $this->requests[array_key_last($this->requests)];
    }

    /**
     * @return array<string, mixed>
     */
    protected function lastBody(): array
    {
        return json_decode((string) $this->lastRequest()->getBody(), true);
    }
}
