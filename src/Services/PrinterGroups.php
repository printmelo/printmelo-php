<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\PrinterGroup;

/**
 * Print to the first available printer of a group.
 */
class PrinterGroups extends Service
{
    /**
     * @return list<PrinterGroup>
     */
    public function list(): array
    {
        return PrinterGroup::collect($this->client->request('GET', 'printer-groups')['data']);
    }

    public function get(int|string $groupId): PrinterGroup
    {
        return new PrinterGroup($this->client->request('GET', 'printer-groups/'.self::id($groupId))['data']);
    }

    /**
     * Needs the `manage` scope.
     *
     * @param  list<int>  $printerIds  In order of preference, at most 20.
     */
    public function create(string $name, array $printerIds): PrinterGroup
    {
        return new PrinterGroup($this->client->request('POST', 'printer-groups', [
            'name' => $name,
            'printer_ids' => array_values($printerIds),
        ])['data']);
    }

    /**
     * Send only what changes. `printer_ids` replaces the whole list. Needs the `manage` scope.
     *
     * @param  array{name?: string, printer_ids?: list<int>}  $params
     */
    public function update(int|string $groupId, array $params): PrinterGroup
    {
        return new PrinterGroup($this->client->request('PUT', 'printer-groups/'.self::id($groupId), $params)['data']);
    }

    /**
     * The printers themselves stay. Needs the `manage` scope.
     */
    public function delete(int|string $groupId): void
    {
        $this->client->request('DELETE', 'printer-groups/'.self::id($groupId));
    }
}
