<?php

declare(strict_types=1);

namespace Printmelo\Tests;

use PHPUnit\Framework\TestCase;
use Printmelo\Exceptions\SignatureVerificationException;
use Printmelo\Webhooks\WebhookEvent;
use Printmelo\Webhooks\WebhookSignature;

class WebhookSignatureTest extends TestCase
{
    private const SECRET = 'whsec_test';

    private function payload(string $event = 'printjob.done'): string
    {
        return json_encode([
            'id' => 'b1946ac9-2b7d-4f8a-9c1e-0f0a1b2c3d4e',
            'event' => $event,
            'created_at' => '2026-11-20T10:00:00+00:00',
            'data' => $event === 'computer.connected' ? ['id' => 1, 'state' => 'connected'] : ['id' => 9, 'state' => 'done'],
        ]);
    }

    public function test_it_matches_the_signature_printmelo_sends(): void
    {
        $payload = $this->payload();
        $timestamp = time();
        $header = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", self::SECRET);

        $event = WebhookSignature::constructEvent($payload, $header, self::SECRET);

        $this->assertSame(WebhookEvent::PRINTJOB_DONE, $event->type());
        $this->assertTrue($event->printJob()->isDone());
        $this->assertNull($event->computer());
    }

    public function test_it_returns_the_computer_of_a_computer_event(): void
    {
        $payload = $this->payload('computer.connected');

        $event = WebhookSignature::constructEvent($payload, WebhookSignature::sign($payload, self::SECRET), self::SECRET);

        $this->assertTrue($event->computer()->isConnected());
        $this->assertNull($event->printJob());
    }

    public function test_it_rejects_a_changed_payload(): void
    {
        $header = WebhookSignature::sign($this->payload(), self::SECRET);

        $this->expectException(SignatureVerificationException::class);

        WebhookSignature::constructEvent($this->payload('printjob.error'), $header, self::SECRET);
    }

    public function test_it_rejects_a_wrong_secret_and_a_missing_header(): void
    {
        $payload = $this->payload();

        $this->assertFalse(WebhookSignature::isValid($payload, WebhookSignature::sign($payload, 'other'), self::SECRET));
        $this->assertFalse(WebhookSignature::isValid($payload, '', self::SECRET));
        $this->assertFalse(WebhookSignature::isValid($payload, 'garbage', self::SECRET));
        $this->assertTrue(WebhookSignature::isValid($payload, WebhookSignature::sign($payload, self::SECRET), self::SECRET));
    }

    public function test_it_rejects_an_old_message(): void
    {
        $payload = $this->payload();
        $header = WebhookSignature::sign($payload, self::SECRET, time() - 600);

        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('tolerance');

        WebhookSignature::verify($payload, $header, self::SECRET);
    }

    public function test_the_timestamp_check_can_be_turned_off(): void
    {
        $payload = $this->payload();

        WebhookSignature::verify($payload, WebhookSignature::sign($payload, self::SECRET, 1000), self::SECRET, 0);

        $this->addToAssertionCount(1);
    }
}
