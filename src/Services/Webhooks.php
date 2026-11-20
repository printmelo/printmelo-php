<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\Webhook;

/**
 * Manage webhook endpoints. Needs the `manage` scope.
 * To receive webhooks, see {@see \Printmelo\Webhooks\WebhookSignature}.
 */
class Webhooks extends Service
{
    /**
     * @return list<Webhook>
     */
    public function list(): array
    {
        return Webhook::collect($this->client->request('GET', 'webhooks')['data']);
    }

    /**
     * Only public https addresses are allowed. The response holds the `secret`: store it,
     * you will not see it again.
     *
     * @param  list<string>  $events  See {@see \Printmelo\Webhooks\WebhookEvent} for the names.
     */
    public function create(string $url, array $events): Webhook
    {
        return new Webhook($this->client->request('POST', 'webhooks', [
            'url' => $url,
            'events' => array_values($events),
        ])['data']);
    }

    public function delete(int|string $webhookId): void
    {
        $this->client->request('DELETE', 'webhooks/'.self::id($webhookId));
    }
}
