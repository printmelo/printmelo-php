<?php

declare(strict_types=1);

namespace Printmelo\Webhooks;

use Printmelo\Exceptions\SignatureVerificationException;

/**
 * Checks that a webhook message comes from Printmelo and has not been changed.
 *
 * The header `X-Printmelo-Signature` holds `t=<timestamp>,v1=<signature>`. The signature is the
 * HMAC-SHA256 of `<timestamp>.<body>` with the secret of the webhook.
 *
 * ```php
 * $event = WebhookSignature::constructEvent(
 *     file_get_contents('php://input'),
 *     $_SERVER['HTTP_X_PRINTMELO_SIGNATURE'] ?? '',
 *     $secret,
 * );
 * ```
 */
final class WebhookSignature
{
    public const HEADER = 'X-Printmelo-Signature';

    /** Seconds a message stays valid, against replays. Each retry gets a fresh timestamp. */
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verify the signature and return the event.
     *
     * @throws SignatureVerificationException
     */
    public static function constructEvent(string $payload, string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): WebhookEvent
    {
        self::verify($payload, $signatureHeader, $secret, $tolerance);

        $data = json_decode($payload, true);

        if (! is_array($data) || ! isset($data['event'])) {
            throw new SignatureVerificationException('The webhook payload is not a valid Printmelo event.');
        }

        return new WebhookEvent($data);
    }

    /**
     * @param  int  $tolerance  Seconds. Pass 0 to skip the timestamp check.
     *
     * @throws SignatureVerificationException
     */
    public static function verify(string $payload, string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE, ?int $now = null): void
    {
        if ($secret === '') {
            throw new SignatureVerificationException('No webhook secret is configured.');
        }

        [$timestamp, $signatures] = self::parse($signatureHeader);

        if ($timestamp === null || $signatures === []) {
            throw new SignatureVerificationException('The X-Printmelo-Signature header is missing or malformed.');
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
        $valid = false;

        foreach ($signatures as $signature) {
            $valid = $valid || hash_equals($expected, $signature);
        }

        if (! $valid) {
            throw new SignatureVerificationException('The webhook signature does not match. Check the webhook secret.');
        }

        if ($tolerance > 0 && abs(($now ?? time()) - $timestamp) > $tolerance) {
            throw new SignatureVerificationException('The webhook timestamp is outside the tolerance window.');
        }
    }

    public static function isValid(string $payload, string $signatureHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): bool
    {
        try {
            self::verify($payload, $signatureHeader, $secret, $tolerance);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }

    /**
     * Build a signature header, the way Printmelo does. Useful in your own tests.
     */
    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $timestamp ??= time();

        return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);
    }

    /**
     * @return array{0: int|null, 1: list<string>}
     */
    private static function parse(string $header): array
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $header) as $part) {
            [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');

            if ($key === 't' && ctype_digit($value)) {
                $timestamp = (int) $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }
}
