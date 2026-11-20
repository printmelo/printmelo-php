<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * One step in the history of a print job.
 *
 * @property-read string $state
 * @property-read string|null $message
 * @property-read array{queue_ms?: int, fetch_ms?: int, print_ms?: int, spool_ms?: int}|null $timings
 * @property-read string $created_at
 * @property-read int|null $created_at_ms
 */
class PrintJobState extends Resource
{
}
