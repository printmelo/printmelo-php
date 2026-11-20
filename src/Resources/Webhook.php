<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read string $url
 * @property-read list<string> $events
 * @property-read bool $active Turns off after 20 failed deliveries in a row.
 * @property-read string|null $secret Only present in the response that creates the webhook.
 * @property-read string|null $last_success_at
 * @property-read string|null $last_failure_at
 * @property-read string $created_at
 */
class Webhook extends Resource
{
}
