<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * A printer that a computer reported.
 *
 * @property-read int $id Stays the same, even when the printer briefly disappears from the computer.
 * @property-read string $uuid
 * @property-read int $computer_id
 * @property-read string $name The name on the computer.
 * @property-read string|null $alias The own name from the dashboard.
 * @property-read string|null $description
 * @property-read bool $is_default
 * @property-read string $state `online` or `offline`.
 * @property-read array{pdf?: bool, raw?: bool, image?: bool, model?: string|null, papers?: list<string>, bins?: list<string>, duplex?: bool, color?: bool}|null $capabilities
 * @property-read int|null $fallback_printer_id
 * @property-read array<string, mixed>|null $default_options
 * @property-read string $created_at
 */
class Printer extends Resource
{
    public function isOnline(): bool
    {
        return $this->attributes['state'] === 'online';
    }

    /**
     * The alias from the dashboard, or else the name on the computer.
     */
    public function displayName(): string
    {
        return $this->attributes['alias'] ?? $this->attributes['name'] ?? '';
    }
}
