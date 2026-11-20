<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Printmelo;

abstract class Service
{
    public function __construct(protected readonly Printmelo $client)
    {
    }

    /**
     * A path segment for an id or uuid.
     */
    protected static function id(int|string $id): string
    {
        return rawurlencode((string) $id);
    }
}
