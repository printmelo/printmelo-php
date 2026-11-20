<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * A computer that runs the Printmelo client.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $name
 * @property-read string|null $hostname
 * @property-read string|null $os `windows` or `macos`.
 * @property-read string|null $client_version
 * @property-read string $state `connected` or `disconnected`.
 * @property-read bool $virtual The practice computer: no client, does not count towards the plan.
 * @property-read string|null $last_seen_at
 * @property-read string $created_at
 */
class Computer extends Resource
{
    public function isConnected(): bool
    {
        return $this->attributes['state'] === 'connected';
    }
}
