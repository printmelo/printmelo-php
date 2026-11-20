<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\Computer;
use Printmelo\Resources\Printer;

/**
 * Computers that run the Printmelo client.
 */
class Computers extends Service
{
    /**
     * @return list<Computer>
     */
    public function list(): array
    {
        return Computer::collect($this->client->request('GET', 'computers')['data']);
    }

    public function get(int|string $computerId): Computer
    {
        return new Computer($this->client->request('GET', 'computers/'.self::id($computerId))['data']);
    }

    /**
     * @return list<Printer>
     */
    public function printers(int|string $computerId): array
    {
        return Printer::collect($this->client->request('GET', 'computers/'.self::id($computerId).'/printers')['data']);
    }

    /**
     * Revoke the token of the client and delete the computer with its printers and print jobs.
     * Needs the `manage` scope.
     */
    public function delete(int|string $computerId): void
    {
        $this->client->request('DELETE', 'computers/'.self::id($computerId));
    }
}
