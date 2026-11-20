<?php

declare(strict_types=1);

namespace Printmelo\Tests;

class ServicesTest extends TestCase
{
    public function test_computers_and_printers(): void
    {
        $this->respond(200, ['data' => [['id' => 1, 'state' => 'connected']]]);
        $this->respond(200, ['data' => [['id' => 4, 'name' => 'ZD420', 'alias' => null, 'state' => 'online']]]);
        $this->respond(204);
        $this->respond(200, ['data' => ['id' => 99, 'name' => 'Practice printer', 'state' => 'online']]);

        $client = $this->client();

        $this->assertTrue($client->computers->list()[0]->isConnected());

        $printer = $client->computers->printers(1)[0];
        $this->assertSame('/api/v1/computers/1/printers', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('ZD420', $printer->displayName());
        $this->assertTrue($printer->isOnline());

        $client->computers->delete(1);
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());

        $this->assertSame(99, $client->printers->practice()->id);
        $this->assertSame('/api/v1/practice-printer', $this->lastRequest()->getUri()->getPath());
    }

    public function test_printer_groups(): void
    {
        $this->respond(201, ['data' => ['id' => 2, 'name' => 'Labels', 'printer_ids' => [4, 5]]]);
        $this->respond(200, ['data' => ['id' => 2, 'name' => 'Labels', 'printer_ids' => [5]]]);

        $client = $this->client();

        $client->printerGroups->create('Labels', [4, 5]);
        $this->assertSame(['name' => 'Labels', 'printer_ids' => [4, 5]], $this->lastBody());

        $group = $client->printerGroups->update(2, ['printer_ids' => [5]]);
        $this->assertSame('PUT', $this->lastRequest()->getMethod());
        $this->assertSame([5], $group->printer_ids);
    }

    public function test_child_accounts_pairing_codes_and_webhooks(): void
    {
        $this->respond(201, ['data' => ['id' => 10, 'name' => 'Customer', 'reference' => null]]);
        $this->respond(201, ['data' => ['code' => 'HS34-SN8S', 'expires_at' => '2026-11-20T12:15:00+00:00']]);
        $this->respond(201, ['data' => ['id' => 3, 'url' => 'https://example.com/hook', 'secret' => 'whsec']]);

        $client = $this->client();

        $client->childAccounts->create('Customer');
        $this->assertSame(['name' => 'Customer'], $this->lastBody());

        $code = $client->forChildAccount(10)->pairingCodes->create();
        $this->assertSame('10', $this->lastRequest()->getHeaderLine('X-Child-Account-Id'));
        $this->assertSame('HS34-SN8S', $code->code);
        $this->assertSame('2026-11-20', $code->date('expires_at')?->format('Y-m-d'));

        $webhook = $client->webhooks->create('https://example.com/hook', ['printjob.done', 'printjob.error']);
        $this->assertSame(['url' => 'https://example.com/hook', 'events' => ['printjob.done', 'printjob.error']], $this->lastBody());
        $this->assertSame('whsec', $webhook->secret);
    }
}
