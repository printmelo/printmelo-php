<?php

declare(strict_types=1);

namespace Printmelo\Services;

use Printmelo\Resources\CursorPage;
use Printmelo\Resources\PrintJob;
use Printmelo\Resources\PrintJobState;

/**
 * Send documents to a printer and follow them.
 *
 * Every create method takes an optional idempotency key. Send one, such as the order number with the
 * kind of document (`order-1001-label`): a second request with the same key returns the existing job
 * instead of printing again, and the SDK may then safely retry on a network error.
 */
class PrintJobs extends Service
{
    /**
     * Print jobs, newest first.
     *
     * @param  array{printer_id?: int, state?: string, limit?: int, cursor?: string}  $filters
     * @return CursorPage<PrintJob>
     */
    public function list(array $filters = []): CursorPage
    {
        return $this->page($this->client->request('GET', 'printjobs', null, $filters));
    }

    /**
     * Every print job that matches the filters, fetching the next page when needed.
     *
     * @param  array{printer_id?: int, state?: string, limit?: int}  $filters
     * @return \Generator<int, PrintJob>
     */
    public function all(array $filters = []): \Generator
    {
        $filters['limit'] ??= 200;

        do {
            $page = $this->list($filters);

            foreach ($page as $job) {
                yield $job;
            }

            $filters['cursor'] = $page->nextCursor;
        } while ($page->hasMore());
    }

    /**
     * The print jobs of one printer, newest first.
     *
     * @param  array{state?: string, limit?: int, cursor?: string}  $filters
     * @return CursorPage<PrintJob>
     */
    public function forPrinter(int|string $printerId, array $filters = []): CursorPage
    {
        return $this->page($this->client->request('GET', 'printers/'.self::id($printerId).'/printjobs', null, $filters));
    }

    public function get(int|string $printJobId): PrintJob
    {
        return new PrintJob($this->client->request('GET', 'printjobs/'.self::id($printJobId))['data']);
    }

    /**
     * Send a print job. Pass `printer_id` or `printer_group_id`, a `title`, the `content_type` and the `content`.
     *
     * @param  array{
     *     printer_id?: int,
     *     printer_group_id?: int,
     *     fallback?: bool,
     *     title: string,
     *     content_type: string,
     *     content: string,
     *     source?: string,
     *     copies?: int,
     *     options?: array{paper?: string, duplex?: string, fit_to_page?: bool, bin?: string, color?: bool, rotate?: int, pages?: string},
     *     expire_after?: int,
     * }  $params
     */
    public function create(array $params, ?string $idempotencyKey = null): PrintJob
    {
        $headers = $idempotencyKey !== null ? ['Idempotency-Key' => $idempotencyKey] : [];

        return new PrintJob($this->client->request('POST', 'printjobs', $params, [], $headers)['data']);
    }

    /**
     * Print a PDF that the client downloads from a URL.
     *
     * @param  array<string, mixed>  $params  Extra fields, such as `copies` or `options`.
     */
    public function printPdfUrl(int $printerId, string $url, string $title, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->send($printerId, PrintJob::PDF_URI, $url, $title, $params, $idempotencyKey);
    }

    /**
     * Print a PDF from its contents. The SDK encodes it as base64. At most 20 MB.
     *
     * @param  array<string, mixed>  $params
     */
    public function printPdf(int $printerId, string $pdf, string $title, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->send($printerId, PrintJob::PDF_BASE64, base64_encode($pdf), $title, $params, $idempotencyKey);
    }

    /**
     * Print a PDF file from disk.
     *
     * @param  array<string, mixed>  $params
     */
    public function printPdfFile(int $printerId, string $path, ?string $title = null, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->printPdf($printerId, self::read($path), $title ?? basename($path), $params, $idempotencyKey);
    }

    /**
     * Send printer language such as ZPL, EPL or ESC/POS. It goes to the printer unchanged.
     *
     * @param  array<string, mixed>  $params
     */
    public function printRaw(int $printerId, string $data, string $title, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->send($printerId, PrintJob::RAW_BASE64, base64_encode($data), $title, $params, $idempotencyKey);
    }

    /**
     * Send printer language that the client downloads from a URL.
     *
     * @param  array<string, mixed>  $params
     */
    public function printRawUrl(int $printerId, string $url, string $title, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->send($printerId, PrintJob::RAW_URI, $url, $title, $params, $idempotencyKey);
    }

    /**
     * Print a PNG or JPEG from a URL. Needs client 0.2.0 or newer.
     *
     * @param  array<string, mixed>  $params
     */
    public function printImageUrl(int $printerId, string $url, string $title, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->send($printerId, PrintJob::IMAGE_URI, $url, $title, $params, $idempotencyKey);
    }

    /**
     * Print a PNG or JPEG from its contents. Needs client 0.2.0 or newer.
     *
     * @param  array<string, mixed>  $params
     */
    public function printImage(int $printerId, string $image, string $title, array $params = [], ?string $idempotencyKey = null): PrintJob
    {
        return $this->send($printerId, PrintJob::IMAGE_BASE64, base64_encode($image), $title, $params, $idempotencyKey);
    }

    /**
     * Cancel a print job that is not finished yet. When the client already handed the document
     * to the printer, it may still come out. Throws a ConflictException for a finished job.
     */
    public function cancel(int|string $printJobId): PrintJob
    {
        return new PrintJob($this->client->request('DELETE', 'printjobs/'.self::id($printJobId))['data']);
    }

    /**
     * Print a job again: a new job with the same document, printer and options. Only works while
     * the account keeps documents and `reprintable_until` has not passed. Counts towards usage.
     */
    public function reprint(int|string $printJobId): PrintJob
    {
        return new PrintJob($this->client->request('POST', 'printjobs/'.self::id($printJobId).'/reprint')['data']);
    }

    /**
     * Every step of a print job with its time, from created to printed.
     *
     * @return list<PrintJobState>
     */
    public function states(int|string $printJobId): array
    {
        return PrintJobState::collect($this->client->request('GET', 'printjobs/'.self::id($printJobId).'/states')['data']);
    }

    /**
     * Poll until the job is finished (`done`, `error`, `expired` or `cancelled`) or the timeout passes.
     * For production, prefer webhooks.
     */
    public function waitUntilFinished(int|string $printJobId, int $timeoutSeconds = 60, int $intervalSeconds = 2): PrintJob
    {
        $deadline = time() + $timeoutSeconds;

        while (true) {
            $job = $this->get($printJobId);

            if ($job->isFinished() || time() >= $deadline) {
                return $job;
            }

            sleep(max(1, $intervalSeconds));
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function send(int $printerId, string $contentType, string $content, string $title, array $params, ?string $idempotencyKey): PrintJob
    {
        return $this->create([
            'printer_id' => $printerId,
            'title' => $title,
            'content_type' => $contentType,
            'content' => $content,
        ] + $params, $idempotencyKey);
    }

    /**
     * @param  array<string, mixed>  $response
     * @return CursorPage<PrintJob>
     */
    private function page(array $response): CursorPage
    {
        return new CursorPage(PrintJob::collect($response['data'] ?? []), $response['next_cursor'] ?? null);
    }

    private static function read(string $path): string
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \InvalidArgumentException("Cannot read the file [{$path}].");
        }

        return (string) file_get_contents($path);
    }
}
