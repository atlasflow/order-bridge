<?php

declare(strict_types=1);

use Atlasflow\OrderBridge\Dto\EnvelopeDto;
use Atlasflow\OrderBridge\Exceptions\ParseException;
use Atlasflow\OrderBridge\Parser\InboundParser;
use Atlasflow\OrderBridge\Tests\Fixtures\ExamplePayload;

/**
 * @param string|array<string, mixed> $payload
 */
function parsePayload(string|array $payload): EnvelopeDto
{
    return (new InboundParser())->parse($payload);
}

// ---------------------------------------------------------------------------
// Valid payload
// ---------------------------------------------------------------------------

describe('InboundParser — valid payload', function () {
    it('parses the canonical §4 example from an array', function () {
        $dto = parsePayload(ExamplePayload::valid());

        expect($dto)->toBeInstanceOf(EnvelopeDto::class);
        expect($dto->schemaVersion)->toBe('1.3.4');
        expect($dto->transferId)->toBe('7322b271-e5e2-42d9-a42e-e4c4fb45a3f8');
        expect($dto->trigger)->toBe('realtime');
        expect($dto->sourceSiteId)->toBe('site_richmond');
        expect($dto->sourceSiteName)->toBe('The Palm Centre');
        expect($dto->sourceOperatorId)->toBe('1');
    });

    it('parses the canonical §4 example from a JSON string', function () {
        $json = json_encode(ExamplePayload::valid(), JSON_THROW_ON_ERROR);
        $dto = parsePayload($json);
        expect($dto->schemaVersion)->toBe('1.3.4');
    });

    it('maps order fields correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $order = $dto->orders[0];

        expect($order->originRef)->toBe('CASSA-2026-00412');
        expect($order->status)->toBe('pos');
        expect($order->channel)->toBe('in-store');
        expect($order->operatorId)->toBe('usr_042');
        expect($order->notes)->toBe('Customer requested care sheet');
    });

    it('maps customer fields correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $customer = $dto->orders[0]->customer;

        expect($customer->type)->toBe('registered');
        expect($customer->id)->toBe('cust_1089');
        expect($customer->name)->toBeNull();
    });

    it('maps fulfilment fields correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $fulfilment = $dto->orders[0]->fulfilment;

        expect($fulfilment->type)->toBe('collection');
        expect($fulfilment->notes)->toBe('Collecting Friday afternoon');
        expect($fulfilment->deliveryAddress)->toBeNull();
    });

    it('maps line item fields correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $item = $dto->orders[0]->items[0];

        expect($item->sku)->toBe('TRF-45L');
        expect($item->qty)->toBe(2);
        expect($item->unitPrice)->toBe('42.6100');
        expect($item->discount)->toBe('10.000000');
        expect($item->lineExVat)->toBe('76.6980');
        expect($item->lineVat)->toBe('0.0000');
        expect($item->batch)->toBe('BATCH-2024-TF-009');
        expect($item->passport)->toBe('GB-12345-A');
    });

    it('maps ancillary fields correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $anc = ($dto->orders[0]->ancillaries ?? [])[0];

        expect($anc->category)->toBe('delivery_fee');
        expect($anc->qty)->toBe(1);
        expect($anc->unitPrice)->toBe('34.9900');
        expect($anc->vatRate)->toBe('21.00');
        expect($anc->totalExVat)->toBe('34.9900');
        expect($anc->totalVat)->toBe('7.3479');
    });

    it('maps totals correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $totals = $dto->orders[0]->totals;

        expect($totals->itemsNet)->toBe('76.6980');
        expect($totals->ancillariesNet)->toBe('34.9900');
        expect($totals->totalVat)->toBe('7.3479');
        expect($totals->grandTotal)->toBe('119.0359');
        expect($totals->refundOf)->toBeNull();
    });

    it('maps payment fields correctly', function () {
        $dto = parsePayload(ExamplePayload::valid());
        $payment = $dto->orders[0]->payments[0];

        expect($payment->method)->toBe('card');
        expect($payment->amount)->toBe('119.0359');
        expect($payment->reference)->toBe('TXN-STRIPE-89234');
        expect($payment->date)->toBe('2026-03-21T10:44:11Z');
    });

    it('sets null transferId when transfer_id is absent (Core export)', function () {
        $payload = ExamplePayload::valid();
        unset($payload['transfer_id']);
        $dto = parsePayload($payload);
        expect($dto->transferId)->toBeNull();
    });
});

// ---------------------------------------------------------------------------
// Invalid JSON
// ---------------------------------------------------------------------------

describe('InboundParser — malformed JSON', function () {
    it('throws ParseException for invalid JSON string', function () {
        expect(fn () => parsePayload('{not json}'))
            ->toThrow(ParseException::class);
    });

    it('throws ParseException for schema violations', function () {
        $payload = ExamplePayload::valid();
        $payload['schema_version'] = 'wrong';
        expect(fn () => parsePayload($payload))
            ->toThrow(ParseException::class);
    });
});
