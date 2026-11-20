<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * A one-time code to pair the client on a computer with the account, without a password.
 *
 * @property-read string $code
 * @property-read string $expires_at
 */
class PairingCode extends Resource
{
}
