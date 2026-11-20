<?php

declare(strict_types=1);

namespace Printmelo\Resources;

/**
 * @property-read int $id
 * @property-read string $uuid
 * @property-read int $printer_id The printer the job went to.
 * @property-read int|null $requested_printer_id Set when the job was redirected to a fallback printer.
 * @property-read int|null $printer_group_id
 * @property-read int|null $reprint_of_id
 * @property-read string|null $reprintable_until
 * @property-read int $computer_id
 * @property-read array{id: int, name: string, alias: string|null, state: string}|null $printer
 * @property-read string $title
 * @property-read string|null $source
 * @property-read string $content_type
 * @property-read int $copies
 * @property-read array<string, mixed>|null $options
 * @property-read bool $practice A job for the practice printer: nothing was printed.
 * @property-read string $state
 * @property-read string|null $state_message The error, or the reason the printer waits.
 * @property-read int|null $latency_ms
 * @property-read string|null $expires_at
 * @property-read string $created_at
 */
class PrintJob extends Resource
{
    public const STATE_NEW = 'new';
    public const STATE_SENT = 'sent';
    public const STATE_DOWNLOADED = 'downloaded';
    public const STATE_PRINTING = 'printing';
    public const STATE_SPOOLED = 'spooled';
    public const STATE_DONE = 'done';
    public const STATE_ERROR = 'error';
    public const STATE_EXPIRED = 'expired';
    public const STATE_CANCELLED = 'cancelled';

    /** A finished job does not change any more. */
    public const FINAL_STATES = [self::STATE_DONE, self::STATE_ERROR, self::STATE_EXPIRED, self::STATE_CANCELLED];

    public const PDF_URI = 'pdf_uri';
    public const PDF_BASE64 = 'pdf_base64';
    public const RAW_URI = 'raw_uri';
    public const RAW_BASE64 = 'raw_base64';
    public const IMAGE_URI = 'image_uri';
    public const IMAGE_BASE64 = 'image_base64';

    public function isFinished(): bool
    {
        return in_array($this->attributes['state'] ?? null, self::FINAL_STATES, true);
    }

    /**
     * Delivered to the print queue of the operating system, or printed according to the printer.
     */
    public function isDone(): bool
    {
        return ($this->attributes['state'] ?? null) === self::STATE_DONE;
    }

    public function failed(): bool
    {
        return in_array($this->attributes['state'] ?? null, [self::STATE_ERROR, self::STATE_EXPIRED], true);
    }

    public function wasRedirected(): bool
    {
        return ($this->attributes['requested_printer_id'] ?? null) !== null;
    }
}
