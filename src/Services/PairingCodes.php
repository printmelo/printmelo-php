<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\PairingCode;

class PairingCodes extends Service
{
    /**
     * A code to pair the client on a computer with the account. Valid for 15 minutes and once only.
     * A new code invalidates the previous one. Needs the `manage` scope.
     *
     * As an integrator, call this on `$printmelo->forChildAccount($id)` to pair a computer of a customer.
     */
    public function create(): PairingCode
    {
        return new PairingCode($this->client->request('POST', 'pairing-codes')['data']);
    }
}
