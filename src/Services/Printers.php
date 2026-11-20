<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\Printer;

class Printers extends Service
{
    /**
     * @return list<Printer>
     */
    public function list(): array
    {
        return Printer::collect($this->client->request('GET', 'printers')['data']);
    }

    public function get(int|string $printerId): Printer
    {
        return new Printer($this->client->request('GET', 'printers/'.self::id($printerId))['data']);
    }

    /**
     * The practice printer of the account. A job for it runs through every state and webhook,
     * but nothing is printed and it does not count towards the plan. Created when it does not exist yet.
     * Needs the `print` scope.
     */
    public function practice(): Printer
    {
        return new Printer($this->client->request('POST', 'practice-printer')['data']);
    }
}
