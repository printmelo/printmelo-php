<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The action does not fit the current state, such as cancelling a finished print job (`409 conflict`).
 */
class ConflictException extends ApiException
{
}
