<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $name
 * @property-read string|null $reference
 * @property-read int|null $parent_id Set for a child account.
 * @property-read bool $is_integrator May this account manage child accounts?
 * @property-read int $computers
 * @property-read int $printers
 * @property-read array{key: string, name: string, trial_ends_at: string|null, printjobs_used: int, printjobs_limit: int|null, computers_limit: int|null}|null $plan
 * @property-read array{name: string, scopes: list<string>, printjobs_today: int, daily_print_limit: int|null}|null $key
 */
class Account extends Resource
{
    /**
     * Does the API key of this request have this scope (`read`, `print` or `manage`)?
     */
    public function keyCan(string $scope): bool
    {
        return in_array($scope, $this->attributes['key']['scopes'] ?? [], true);
    }
}
