<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * A list of printers in order of preference. A job for the group goes to the first available printer.
 *
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $name
 * @property-read list<int> $printer_ids
 * @property-read string $created_at
 */
class PrinterGroup extends Resource
{
}
