<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * Too many requests (`429 rate_limited`). The SDK retries these automatically.
 */
class RateLimitException extends ApiException
{
    /**
     * Seconds to wait before the next request, from the `Retry-After` header.
     */
    public function getRetryAfter(): ?int
    {
        $value = $this->getResponse()?->getHeaderLine('Retry-After');

        return $value !== null && ctype_digit($value) ? (int) $value : null;
    }
}
