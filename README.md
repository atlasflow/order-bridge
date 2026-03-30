# Atlas Core Order Bridge

[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg)](LICENSE.md)

A framework-agnostic PHP package for serialising, validating, and transporting orders between Atlas Core and host applications (ePOS, E-commerce, etc.) according to the **Order Bridge API Specification v1.3.4**.

## Core Concepts

The package owns two things exclusively:
1. **Serialisation**: Converting host application data into spec-compliant JSON payloads.
2. **Validation**: Enforcing all schema and arithmetic rules defined in the specification.

It is designed to be **unopinionated about persistence**. It does not include Eloquent models, migrations, or database logic. Instead, it defines a set of **Interfaces** that your application's models must implement.

## Key Components

- **`OrderSerialiser`**: Takes objects implementing `OrderInterface` and produces a fully validated payload array. It handles all complex arithmetic (VAT, line totals, grand totals) using `bcmath` internally.
- **`PayloadValidator`**: Implements all rules from §5 of the spec, including arithmetic verification and idempotency checks.
- **`HttpTransport`**: A thin PSR-18 compatible layer for sending payloads to Atlas Core.
- **`InboundParser`**: The reverse direction—parses and validates JSON from Atlas Core into typed DTOs.
- **`TransferIdManager`**: Utility for generating and managing `transfer_id` GUIDs to ensure idempotency.

---

## Installation

```bash
composer require atlasflow/order-bridge
```

*Note: Requires PHP 8.3+ and the `bcmath` extension.*

---

## Usage Instructions

### 1. Implement the Interfaces

The package never touches your models directly. You must create "Adapters" that wrap your models and implement the interfaces in `Atlasflow\OrderBridge\Contracts`.

```php
use Atlasflow\OrderBridge\Contracts\OrderInterface;
use Atlasflow\OrderBridge\Contracts\LineItemInterface;
// ... and other contracts

class MyOrderAdapter implements OrderInterface
{
    public function __construct(private MyOrderModel $order) {}

    public function getOriginRef(): string { return $this->order->reference; }
    public function getStatus(): string { return 'pos'; }
    // ... implement remaining methods
}
```

### 2. Serialise and Send

The `OrderSerialiser` computes all totals for you. You only provide raw values (quantities, unit prices, discount percentages, and VAT rates).

```php
use Atlasflow\OrderBridge\Serialiser\OrderSerialiser;
use Atlasflow\OrderBridge\Transport\HttpTransport;

// 1. Setup Serialiser
$serialiser = new OrderSerialiser([
    'site_id' => 'site_richmond',
    'site_name' => 'The Palm Centre',
    'source_type' => 'cassa',
]);

// 2. Serialise your order(s)
// $orders should be an iterable of OrderInterface
$payload = $serialiser->serialise($orders, trigger: 'realtime');

// 3. Send via HTTP (requires a PSR-18 client and PSR-17 factories)
$transport = new HttpTransport($httpClient, $requestFactory, $streamFactory, $endpointUrl);
$result = $transport->send($payload);

if ($result->isSuccess()) {
    // Mark order as synced in your DB
}
```

### 3. Validate a Payload

You can use the validator independently to dry-run a batch or validate inbound webhooks.

```php
use Atlasflow\OrderBridge\Validator\PayloadValidator;

$validator = new PayloadValidator();
$result = $validator->validate($payloadArray);

if (!$result->isValid()) {
    foreach ($result->getViolations() as $violation) {
        echo "Error in {$violation->field}: {$violation->message}\n";
    }
}
```

### 4. Parse Inbound Payloads

When receiving an export from Atlas Core, use the `InboundParser` to get typed DTOs.

```php
use Atlasflow\OrderBridge\Parser\InboundParser;

$parser = new InboundParser();
try {
    $envelope = $parser->parse($jsonString);
    
    foreach ($envelope->orders as $orderDto) {
        // Map DTO back to your application models
        echo $orderDto->originRef;
    }
} catch (ParseException $e) {
    // Handle malformed JSON or validation failure
}
```

---

## Idempotency

The package automatically generates a `transfer_id` (UUID v4) during serialisation. Atlas Core uses this ID combined with your `site_id` to prevent duplicate order processing. 

If you need to retry a failed transport attempt:
1. **Identical Retries**: Reuse the same payload (and thus the same `transfer_id`). Core will acknowledge the retry without creating duplicate records.
2. **Modified Payloads**: If the content changes, you **must** generate a new `transfer_id` by re-serialising the order.

---

## Laravel Integration

For Laravel applications, we recommend using the optional bridge for cleaner integration:
[atlasflow/order-bridge-laravel](https://github.com/atlasflow/order-bridge-laravel) (coming soon).

---

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
