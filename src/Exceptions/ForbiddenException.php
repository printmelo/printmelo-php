<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The API key lacks the scope this endpoint asks for (`403 forbidden`).
 */
class ForbiddenException extends ApiException
{
}
