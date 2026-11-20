<?php

declare(strict_types=1);

namespace Printmelo\Tests;

use GuzzleHttp\Psr7\Request;
use Printmelo\Exceptions\AuthenticationException;
use Printmelo\Exceptions\ConflictException;
use Printmelo\Exceptions\KeyBudgetException;
use Printmelo\Exceptions\PlanLimitException;
use Printmelo\Exceptions\RateLimitException;
use Printmelo\Exceptions\ServerException;
use Printmelo\Exceptions\TransportException;
use Printmelo\Exceptions\ValidationException;
use Printmelo\Printmelo;
use Psr\Http\Client\NetworkExceptionInterface;

class ClientTest extends TestCase
{
    public function test_it_requires_an_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Printmelo(' ');
    }

    public function test_it_sends_the_api_key_and_returns_the_account(): void
    {
        $this->respond(200, ['data' => ['id' => 1, 'name' => 'Webshop', 'key' => ['name' => 'Shop', 'scopes' => ['read', 'print']]]]);

        $account = $this->client(['locale' => 'en'])->whoami();

        $request = $this->lastRequest();
        $this->assertSame('GET', $request->getMethod());
        $this->assertSame('https://api.printmelo.test/api/v1/whoami', (string) $request->getUri());
        $this->assertSame('Bearer pm_test_key', $request->getHeaderLine('Authorization'));
        $this->assertSame('en', $request->getHeaderLine('Accept-Language'));
        $this->assertStringStartsWith('printmelo-php/', $request->getHeaderLine('User-Agent'));
        $this->assertSame('Webshop', $account->name);
        $this->assertSame('Webshop', $account['name']);
        $this->assertTrue($account->keyCan('print'));
        $this->assertFalse($account->keyCan('manage'));
    }

    public function test_it_works_on_behalf_of_a_child_account_without_changing_the_original_client(): void
    {
        $client = $this->client();
        $this->respond(200, ['data' => []]);
        $this->respond(200, ['data' => []]);
        $this->respond(200, ['data' => []]);

        $client->forChildAccount(42)->printers->list();
        $this->assertSame('42', $this->lastRequest()->getHeaderLine('X-Child-Account-Id'));

        $client->forChildReference('CUST-7')->printers->list();
        $this->assertSame('CUST-7', $this->lastRequest()->getHeaderLine('X-Child-Account-Reference'));

        $client->printers->list();
        $this->assertFalse($this->lastRequest()->hasHeader('X-Child-Account-Id'));
    }

    /**
     * @return iterable<string, array{int, string, class-string}>
     */
    public static function errors(): iterable
    {
        yield 'unauthenticated' => [401, 'unauthenticated', AuthenticationException::class];
        yield 'plan limit' => [402, 'plan_limit', PlanLimitException::class];
        yield 'conflict' => [409, 'conflict', ConflictException::class];
        yield 'key budget' => [429, 'key_budget', KeyBudgetException::class];
    }

    /**
     * @param  class-string  $class
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('errors')]
    public function test_it_maps_error_codes_to_exceptions(int $status, string $code, string $class): void
    {
        $this->respond($status, ['message' => 'Nope.', 'code' => $code]);

        try {
            $this->client()->whoami();
            $this->fail('Expected an exception.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf($class, $e);
            $this->assertSame('Nope.', $e->getMessage());
            $this->assertSame($code, $e->getErrorCode());
            $this->assertSame($status, $e->getStatusCode());
        }

        $this->assertCount(1, $this->requests, 'Errors that will not pass by themselves are not retried.');
    }

    public function test_validation_errors_list_the_messages_per_field(): void
    {
        $this->respond(422, ['message' => 'The title is required.', 'code' => 'validation_failed', 'errors' => ['title' => ['The title is required.']]]);

        try {
            $this->client()->printJobs->create(['printer_id' => 1, 'content_type' => 'pdf_uri', 'content' => 'https://x.test/a.pdf', 'title' => '']);
            $this->fail('Expected an exception.');
        } catch (ValidationException $e) {
            $this->assertSame(['title' => ['The title is required.']], $e->getErrors());
        }
    }

    public function test_it_retries_rate_limits_and_server_errors_on_reads(): void
    {
        $this->respond(429, ['message' => 'Slow down.', 'code' => 'rate_limited'], ['Retry-After' => '3']);
        $this->respond(503, ['message' => 'Down.', 'code' => 'server_error']);
        $this->respond(200, ['data' => []]);

        $this->assertSame([], $this->client()->printers->list());
        $this->assertCount(3, $this->requests);
        $this->assertSame([3000, 1000], $this->sleeps);
    }

    public function test_it_gives_up_after_the_maximum_number_of_retries(): void
    {
        $this->respond(500, ['message' => 'Down.', 'code' => 'server_error']);
        $this->respond(500, ['message' => 'Down.', 'code' => 'server_error']);

        $this->expectException(ServerException::class);

        try {
            $this->client(['max_retries' => 1])->printers->list();
        } finally {
            $this->assertCount(2, $this->requests);
        }
    }

    public function test_it_never_retries_a_print_job_without_an_idempotency_key(): void
    {
        $this->respond(503, ['message' => 'Down.', 'code' => 'server_error']);

        $this->expectException(ServerException::class);

        try {
            $this->client()->printJobs->printPdfUrl(1, 'https://x.test/a.pdf', 'Label');
        } finally {
            $this->assertCount(1, $this->requests);
        }
    }

    public function test_it_retries_a_print_job_with_an_idempotency_key(): void
    {
        $this->failWith($this->networkError());
        $this->respond(201, ['data' => ['id' => 5, 'state' => 'new']]);

        $job = $this->client()->printJobs->printPdfUrl(1, 'https://x.test/a.pdf', 'Label', [], 'order-1001-label');

        $this->assertSame(5, $job->id);
        $this->assertCount(2, $this->requests);
        $this->assertSame('order-1001-label', $this->requests[1]->getHeaderLine('Idempotency-Key'));
    }

    public function test_a_network_error_becomes_a_transport_exception(): void
    {
        $this->failWith($this->networkError());

        $this->expectException(TransportException::class);

        $this->client(['max_retries' => 0])->printers->list();
    }

    public function test_rate_limit_exception_exposes_retry_after(): void
    {
        $this->respond(429, ['message' => 'Slow down.', 'code' => 'rate_limited'], ['Retry-After' => '7']);

        try {
            $this->client(['max_retries' => 0])->printers->list();
            $this->fail('Expected an exception.');
        } catch (RateLimitException $e) {
            $this->assertSame(7, $e->getRetryAfter());
        }
    }

    private function networkError(): NetworkExceptionInterface
    {
        return new class('Connection refused', new Request('GET', 'https://api.printmelo.test')) extends \RuntimeException implements NetworkExceptionInterface {
            public function __construct(string $message, private \Psr\Http\Message\RequestInterface $request)
            {
                parent::__construct($message);
            }

            public function getRequest(): \Psr\Http\Message\RequestInterface
            {
                return $this->request;
            }
        };
    }
}
