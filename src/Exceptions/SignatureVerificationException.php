<?php

declare(strict_types=1);

namespace Printmelo\Exceptions;

/**
 * A webhook message does not carry a valid `X-Printmelo-Signature`.
 */
class SignatureVerificationException extends \RuntimeException implements PrintmeloException
{
}
