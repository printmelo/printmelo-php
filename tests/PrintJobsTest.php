<?php

declare(strict_types=1);

namespace Printmelo\Tests;

use Printmelo\Resources\PrintJob;

class PrintJobsTest extends TestCase
{
    public function test_it_prints_a_pdf_from_a_url(): void
    {
        $this->respond(201, ['data' => ['id' => 9, 'state' => 'new', 'printer_id' => 12]]);

        $job = $this->client()->printJobs->printPdfUrl(12, 'https://example.com/label.pdf', 'Label 10482', ['copies' => 2, 'options' => ['paper' => 'A6']], 'order-10482-label');

        $this->assertSame('POST', $this->lastRequest()->getMethod());
        $this->assertSame('/api/v1/printjobs', $this->lastRequest()->getUri()->getPath());
        $this->assertSame('order-10482-label', $this->lastRequest()->getHeaderLine('Idempotency-Key'));
        $this->assertSame([
            'printer_id' => 12,
            'title' => 'Label 10482',
            'content_type' => 'pdf_uri',
            'content' => 'https://example.com/label.pdf',
            'copies' => 2,
            'options' => ['paper' => 'A6'],
        ], $this->lastBody());
        $this->assertInstanceOf(PrintJob::class, $job);
        $this->assertFalse($job->isFinished());
    }

    public function test_it_encodes_raw_printer_language_and_files_as_base64(): void
    {
        $this->respond(201, ['data' => ['id' => 1]]);
        $this->respond(201, ['data' => ['id' => 2]]);

        $client = $this->client();
        $client->printJobs->printRaw(3, '^XA^FO50,50^FDHello^FS^XZ', 'ZPL');
        $this->assertSame('raw_base64', $this->lastBody()['content_type']);
        $this->assertSame('^XA^FO50,50^FDHello^FS^XZ', base64_decode($this->lastBody()['content']));

        $path = tempnam(sys_get_temp_dir(), 'pm').'.pdf';
        file_put_contents($path, '%PDF-1.4 test');
        $client->printJobs->printPdfFile(3, $path);
        unlink($path);
        $this->assertSame('pdf_base64', $this->lastBody()['content_type']);
        $this->assertSame(basename($path), $this->lastBody()['title']);
        $this->assertSame('%PDF-1.4 test', base64_decode($this->lastBody()['content']));
    }

    public function test_it_lists_print_jobs_with_filters_and_a_cursor(): void
    {
        $this->respond(200, ['data' => [['id' => 3, 'state' => 'done']], 'next_cursor' => 'abc']);

        $page = $this->client()->printJobs->list(['state' => 'done', 'printer_id' => 12, 'limit' => 10]);

        parse_str($this->lastRequest()->getUri()->getQuery(), $query);
        $this->assertSame(['state' => 'done', 'printer_id' => '12', 'limit' => '10'], $query);
        $this->assertCount(1, $page);
        $this->assertTrue($page->hasMore());
        $this->assertSame('abc', $page->nextCursor);
        $this->assertTrue($page->items[0]->isDone());
    }

    public function test_all_follows_the_cursor_through_every_page(): void
    {
        $this->respond(200, ['data' => [['id' => 3], ['id' => 2]], 'next_cursor' => 'page2']);
        $this->respond(200, ['data' => [['id' => 1]], 'next_cursor' => null]);

        $ids = array_map(fn (PrintJob $job) => $job->id, iterator_to_array($this->client()->printJobs->all(), false));

        $this->assertSame([3, 2, 1], $ids);
        parse_str($this->requests[1]->getUri()->getQuery(), $query);
        $this->assertSame('page2', $query['cursor']);
    }

    public function test_cancel_reprint_and_states(): void
    {
        $this->respond(200, ['data' => ['id' => 7, 'state' => 'cancelled']]);
        $this->respond(201, ['data' => ['id' => 8, 'reprint_of_id' => 7]]);
        $this->respond(200, ['data' => [['state' => 'new'], ['state' => 'done', 'timings' => ['print_ms' => 120]]]]);

        $client = $this->client();
        $uuid = '9f8b7c2e-1111-4a2b-9c3d-123456789abc';

        $this->assertTrue($client->printJobs->cancel($uuid)->isFinished());
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
        $this->assertSame("/api/v1/printjobs/{$uuid}", $this->lastRequest()->getUri()->getPath());

        $this->assertSame(7, $client->printJobs->reprint(7)->reprint_of_id);
        $this->assertSame('/api/v1/printjobs/7/reprint', $this->lastRequest()->getUri()->getPath());

        $states = $client->printJobs->states(7);
        $this->assertSame(120, $states[1]->timings['print_ms']);
    }
}
