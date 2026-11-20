<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

use Psr\Http\Message\ResponseInterface;

/**
 * The API answered with an error. Every error has a readable message and a fixed code.
 *
 * @see https://api.printmelo.com/docs
 */
class ApiException extends \RuntimeException implements PrintmeloException
{
    /**
     * @param  array<string, mixed>  $body
     */
    public function __construct(
        string $message,
        private readonly int $statusCode,
        private readonly ?string $errorCode = null,
        private readonly array $body = [],
        private readonly ?ResponseInterface $response = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $statusCode, $previous);
    }

    /**
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(ResponseInterface $response, array $body): self
    {
        $status = $response->getStatusCode();
        $code = isset($body['code']) && is_string($body['code']) ? $body['code'] : null;
        $message = isset($body['message']) && is_string($body['message']) && $body['message'] !== ''
            ? $body['message']
            : "Printmelo returned HTTP {$status}.";

        $class = match (true) {
            $code === 'unauthenticated' || $status === 401 => AuthenticationException::class,
            $code === 'forbidden' || $status === 403 => ForbiddenException::class,
            $code === 'not_found' || $status === 404 => NotFoundException::class,
            $code === 'plan_limit' || $status === 402 => PlanLimitException::class,
            $code === 'conflict' || $status === 409 => ConflictException::class,
            $code === 'validation_failed' || $status === 422 => ValidationException::class,
            $code === 'key_budget' => KeyBudgetException::class,
            $code === 'rate_limited' || $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            default => self::class,
        };

        return new $class($message, $status, $code, $body, $response);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * The fixed error code, such as `plan_limit` or `validation_failed`.
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return array<string, mixed>
     */
    public function getBody(): array
    {
        return $this->body;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
