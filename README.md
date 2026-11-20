# Printmelo PHP SDK

[![Tests](https://github.com/printmelo/printmelo-php/actions/workflows/tests.yml/badge.svg)](https://github.com/printmelo/printmelo-php/actions/workflows/tests.yml)
[![Latest version](https://img.shields.io/packagist/v/printmelo/printmelo-php.svg)](https://packagist.org/packages/printmelo/printmelo-php)
[![License](https://img.shields.io/packagist/l/printmelo/printmelo-php.svg)](LICENSE)

Print from your own PHP software to printers on location with [Printmelo](https://www.printmelo.com). Send a shipping label, packing slip or invoice, and the Printmelo client on the computer next to the printer prints it within a second.

Using Laravel? Install [printmelo/printmelo-laravel](https://github.com/printmelo/printmelo-laravel) instead. It adds a facade, config and webhook events on top of this SDK.

## Requirements

- PHP 8.1 or newer
- A Printmelo account and an API key. Create a key in the dashboard under **API keys**.

## Installation

```bash
composer require printmelo/printmelo-php
```

## Quick start

```php
use Printmelo\Printmelo;

$printmelo = new Printmelo(getenv('PRINTMELO_API_KEY'));

// Find a printer.
foreach ($printmelo->printers->list() as $printer) {
    echo "{$printer->id}: {$printer->displayName()} ({$printer->state})\n";
}

// Print a PDF from a URL. The idempotency key prevents double prints.
$job = $printmelo->printJobs->printPdfUrl(
    printerId: 12,
    url: 'https://example.com/labels/10482.pdf',
    title: 'Shipping label 10482',
    idempotencyKey: 'order-10482-label',
);

echo $job->state; // "new"
```

## Printing

Every print method returns a `Printmelo\Resources\PrintJob`.

```php
// PDF
$printmelo->printJobs->printPdfUrl(12, 'https://example.com/label.pdf', 'Label 10482');
$printmelo->printJobs->printPdf(12, $pdfContents, 'Invoice 2026-001');
$printmelo->printJobs->printPdfFile(12, '/path/to/packing-slip.pdf');

// Printer language such as ZPL, EPL or ESC/POS goes to the printer unchanged.
$printmelo->printJobs->printRaw(12, '^XA^FO50,50^ADN,36,20^FDHello^FS^XZ', 'Test label');
$printmelo->printJobs->printRawUrl(12, 'https://example.com/label.zpl', 'Label 10482');

// PNG or JPEG (client 0.2.0 or newer)
$printmelo->printJobs->printImageUrl(12, 'https://example.com/label.png', 'Label 10482');
$printmelo->printJobs->printImage(12, $pngContents, 'Label 10482');
```

Pass extra fields as the fourth argument:

```php
$printmelo->printJobs->printPdfUrl(12, $url, 'Pick list', [
    'copies' => 2,
    'source' => 'My webshop',
    'expire_after' => 3600, // seconds
    'options' => [
        'paper' => 'A4',
        'duplex' => 'long-edge',  // one-sided, long-edge, short-edge
        'fit_to_page' => true,
        'bin' => 'tray-1',
        'color' => false,
        'rotate' => 90,
        'pages' => '1-3,5',
    ],
]);
```

For full control, use `create()` with the fields of the API. Print to a printer group to use the first available printer:

```php
$printmelo->printJobs->create([
    'printer_group_id' => 3,
    'title' => 'Label 10482',
    'content_type' => 'pdf_uri',
    'content' => 'https://example.com/label.pdf',
], idempotencyKey: 'order-10482-label');
```

### Avoid double prints

Send an idempotency key with every print job, for example the order number with the kind of document. A second request with the same key returns the existing job instead of printing again.

The SDK retries reads, and print jobs **with** an idempotency key, on a connection error, `429 rate_limited` or a 5xx response. A print job without a key is never retried, so it never prints twice.

### Follow a print job

```php
$job = $printmelo->printJobs->get($job->id);   // id or uuid

$job->state;          // new, sent, printing, spooled, done, error, expired, cancelled
$job->isFinished();   // done, error, expired or cancelled
$job->isDone();
$job->failed();       // error or expired
$job->state_message;  // the error, or why the printer waits
$job->wasRedirected();

// Every step with its time.
foreach ($printmelo->printJobs->states($job->id) as $step) {
    echo "{$step->created_at} {$step->state} {$step->message}\n";
}

// Cancel or print again.
$printmelo->printJobs->cancel($job->id);
$printmelo->printJobs->reprint($job->id);
```

In a script or test you can wait for the result. In production, use [webhooks](#webhooks).

```php
$job = $printmelo->printJobs->waitUntilFinished($job->id, timeoutSeconds: 60);
```

### List print jobs

```php
$page = $printmelo->printJobs->list(['state' => 'error', 'printer_id' => 12, 'limit' => 50]);

foreach ($page as $job) {
    // ...
}

if ($page->hasMore()) {
    $next = $printmelo->printJobs->list(['state' => 'error', 'cursor' => $page->nextCursor]);
}

// Or let the SDK fetch every page.
foreach ($printmelo->printJobs->all(['state' => 'error']) as $job) {
    // ...
}

// The jobs of one printer.
$printmelo->printJobs->forPrinter(12, ['limit' => 10]);
```

### Test without paper

The practice printer runs a job through every state and webhook, but prints nothing. It does not count towards your plan.

```php
$printer = $printmelo->printers->practice();
$printmelo->printJobs->printPdfUrl($printer->id, 'https://example.com/label.pdf', 'Test');
```

## Other resources

```php
$account = $printmelo->whoami();
$account->name;
$account->plan['printjobs_used'];
$account->keyCan('print');

$printmelo->computers->list();
$printmelo->computers->get(1);
$printmelo->computers->printers(1);
$printmelo->computers->delete(1);            // manage

$printmelo->printers->list();
$printmelo->printers->get(12);

$printmelo->printerGroups->list();
$printmelo->printerGroups->create('Labels', [12, 14]);       // manage, in order of preference
$printmelo->printerGroups->update(3, ['printer_ids' => [14, 12]]);
$printmelo->printerGroups->delete(3);

$code = $printmelo->pairingCodes->create();  // manage
echo $code->code; // HS34-SN8S, valid for 15 minutes

$printmelo->webhooks->list();                // manage
$printmelo->webhooks->delete(5);
```

Every object reads as properties and as an array, and converts with `toArray()`. Dates are ISO 8601 strings; `$job->date('created_at')` gives a `DateTimeImmutable`.

## Integrators and child accounts

With an integrator plan you manage the accounts of your own customers. Their usage counts towards your subscription.

```php
$child = $printmelo->childAccounts->create('Webshop Jansen', reference: 'CUST-1001');

// Work on behalf of a child account, by id or by your own reference.
$customer = $printmelo->forChildAccount($child->id);
$customer = $printmelo->forChildReference('CUST-1001');

$code = $customer->pairingCodes->create();   // let the customer pair their computer
$customer->printJobs->printPdfUrl(31, $url, 'Label');

$printmelo->childAccounts->list();
$printmelo->childAccounts->delete($child->id);
```

`forChildAccount()` returns a copy. The original client keeps working on your own account.

## Webhooks

Printmelo posts to your endpoint when a print job or computer changes. Create the endpoint once and store the secret: you only see it in this response.

```php
use Printmelo\Webhooks\WebhookEvent;

$webhook = $printmelo->webhooks->create('https://example.com/webhooks/printmelo', [
    WebhookEvent::PRINTJOB_DONE,
    WebhookEvent::PRINTJOB_ERROR,
    WebhookEvent::COMPUTER_DISCONNECTED,
]);

$secret = $webhook->secret;
```

Check the signature of every message before you trust it:

```php
use Printmelo\Exceptions\SignatureVerificationException;
use Printmelo\Webhooks\WebhookSignature;

try {
    $event = WebhookSignature::constructEvent(
        file_get_contents('php://input'),
        $_SERVER['HTTP_X_PRINTMELO_SIGNATURE'] ?? '',
        $secret,
    );
} catch (SignatureVerificationException $e) {
    http_response_code(403);
    exit;
}

match ($event->type()) {
    WebhookEvent::PRINTJOB_DONE => markLabelPrinted($event->printJob()),
    WebhookEvent::PRINTJOB_ERROR => alertWarehouse($event->printJob()->state_message),
    WebhookEvent::COMPUTER_DISCONNECTED => alertWarehouse("{$event->computer()->name} is offline"),
    default => null,
};

http_response_code(200);
```

- Answer within 10 seconds with a 2xx status. Otherwise Printmelo retries five times, after 10 seconds, 1 minute, 5 minutes, 30 minutes and 2 hours.
- `$event->id` is unique per message. Use it to skip a message you already handled.
- A message older than 5 minutes is refused, against replays. Change this with the fourth argument of `constructEvent()`.
- Events: `printjob.printing`, `printjob.waiting`, `printjob.done`, `printjob.error`, `printjob.expired`, `printjob.cancelled`, `computer.connected`, `computer.disconnected`.

## Errors

Every error is a `Printmelo\Exceptions\PrintmeloException`. API errors extend `ApiException`, with the HTTP status and the fixed error code.

| Exception | Status | Code |
| --- | --- | --- |
| `AuthenticationException` | 401 | `unauthenticated` |
| `PlanLimitException` | 402 | `plan_limit` |
| `ForbiddenException` | 403 | `forbidden` |
| `NotFoundException` | 404 | `not_found` |
| `ConflictException` | 409 | `conflict` |
| `ValidationException` | 422 | `validation_failed` |
| `RateLimitException` | 429 | `rate_limited` |
| `KeyBudgetException` | 429 | `key_budget` |
| `ServerException` | 5xx | `server_error` |
| `TransportException` | | no response |

```php
use Printmelo\Exceptions\ApiException;
use Printmelo\Exceptions\PlanLimitException;
use Printmelo\Exceptions\ValidationException;

try {
    $printmelo->printJobs->printPdfUrl(12, $url, 'Label');
} catch (ValidationException $e) {
    $e->getErrors();      // ['content' => ['...']]
} catch (PlanLimitException $e) {
    // Upgrade the plan in the dashboard.
} catch (ApiException $e) {
    $e->getStatusCode();
    $e->getErrorCode();
    $e->getMessage();
}
```

## Configuration

```php
$printmelo = new Printmelo($apiKey, [
    'base_url' => 'https://api.printmelo.com/api/v1',
    'timeout' => 30,          // seconds
    'connect_timeout' => 10,
    'max_retries' => 2,
    'locale' => 'en',         // language of error messages: en or nl
    'http_client' => $psr18Client, // any PSR-18 client, Guzzle by default
]);
```

Need an endpoint the SDK does not cover yet? Use `request()`:

```php
$response = $printmelo->request('GET', 'printjobs', query: ['limit' => 5]);
```

## API reference

The full API is documented at [api.printmelo.com/docs](https://api.printmelo.com/docs), with the OpenAPI description at [api.printmelo.com/openapi.json](https://api.printmelo.com/openapi.json).

## Testing

```bash
composer install
composer test
```

## License

MIT. See [LICENSE](LICENSE).
