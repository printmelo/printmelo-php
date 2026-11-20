<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $name
 * @property-read string|null $reference Your own reference for this customer.
 * @property-read int $computers
 * @property-read int $printers
 * @property-read string $created_at
 */
class ChildAccount extends Resource
{
}
