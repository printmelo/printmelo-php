<?php

declare(strict_types=1);

namespace Printmelo\Webhooks;

use Printmelo\Resources\Computer;
use Printmelo\Resources\PrintJob;
use Printmelo\Resources\Resource;

/**
 * A webhook message: `{"id", "event", "created_at", "data"}`.
 * `data` is the print job or the computer as the API returns it.
 *
 * @property-read string $id Unique per message. Use it to recognise a message you already handled.
 * @property-read string $event
 * @property-read string $created_at
 * @property-read array<string, mixed> $data
 */
class WebhookEvent extends Resource
{
    public const PRINTJOB_PRINTING = 'printjob.printing';
    public const PRINTJOB_WAITING = 'printjob.waiting';
    public const PRINTJOB_DONE = 'printjob.done';
    public const PRINTJOB_ERROR = 'printjob.error';
    public const PRINTJOB_EXPIRED = 'printjob.expired';
    public const PRINTJOB_CANCELLED = 'printjob.cancelled';
    public const COMPUTER_CONNECTED = 'computer.connected';
    public const COMPUTER_DISCONNECTED = 'computer.disconnected';

    public const ALL = [
        self::PRINTJOB_PRINTING,
        self::PRINTJOB_WAITING,
        self::PRINTJOB_DONE,
        self::PRINTJOB_ERROR,
        self::PRINTJOB_EXPIRED,
        self::PRINTJOB_CANCELLED,
        self::COMPUTER_CONNECTED,
        self::COMPUTER_DISCONNECTED,
    ];

    public function type(): string
    {
        return (string) ($this->attributes['event'] ?? '');
    }

    public function isPrintJobEvent(): bool
    {
        return str_starts_with($this->type(), 'printjob.');
    }

    public function isComputerEvent(): bool
    {
        return str_starts_with($this->type(), 'computer.');
    }

    /**
     * The print job of a `printjob.*` event, or null for another event.
     */
    public function printJob(): ?PrintJob
    {
        return $this->isPrintJobEvent() ? new PrintJob($this->attributes['data'] ?? []) : null;
    }

    /**
     * The computer of a `computer.*` event, or null for another event.
     */
    public function computer(): ?Computer
    {
        return $this->isComputerEvent() ? new Computer($this->attributes['data'] ?? []) : null;
    }

    /**
     * The print job or the computer.
     */
    public function object(): ?Resource
    {
        return $this->printJob() ?? $this->computer();
    }
}
