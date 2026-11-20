<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * The API could not be reached: a DNS, connection or timeout error.
 */
class TransportException extends \RuntimeException implements PrintmeloException
{
}
