<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The API key is missing or invalid (`401 unauthenticated`).
 */
class AuthenticationException extends ApiException
{
}
