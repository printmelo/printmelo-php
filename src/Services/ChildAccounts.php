<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\ChildAccount;

/**
 * For integrators: manage the accounts of your own customers. Needs the `manage` scope.
 * Use `$printmelo->forChildAccount($id)` to work on behalf of a child account.
 */
class ChildAccounts extends Service
{
    /**
     * @return list<ChildAccount>
     */
    public function list(): array
    {
        return ChildAccount::collect($this->client->request('GET', 'account/children')['data']);
    }

    public function get(int|string $childAccountId): ChildAccount
    {
        return new ChildAccount($this->client->request('GET', 'account/children/'.self::id($childAccountId))['data']);
    }

    /**
     * @param  string|null  $reference  Unique within your account, such as your customer number.
     */
    public function create(string $name, ?string $reference = null): ChildAccount
    {
        return new ChildAccount($this->client->request('POST', 'account/children', array_filter([
            'name' => $name,
            'reference' => $reference,
        ], static fn ($value) => $value !== null))['data']);
    }

    /**
     * Also deletes the computers, printers and print jobs of the child account and revokes its tokens.
     */
    public function delete(int|string $childAccountId): void
    {
        $this->client->request('DELETE', 'account/children/'.self::id($childAccountId));
    }
}
