<?php

declare(strict_types=1);

use Atlasflow\OrderBridge\Serialiser\OrderSerialiser;
use Atlasflow\OrderBridge\Tests\Fixtures\StubAncillary;
use Atlasflow\OrderBridge\Tests\Fixtures\StubLineItem;
use Atlasflow\OrderBridge\Tests\Fixtures\StubOrder;
use Atlasflow\OrderBridge\Tests\Fixtures\StubPayment;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** @param array<string, mixed> $configOverrides */
function makeSerialiser(array $configOverrides = []): OrderSerialiser
{
    return new OrderSerialiser(array_merge([
        'site_id' => 'site_richmond',
        'site_name' => 'The Palm Centre',
        'source_type' => 'cassa',
    ], $configOverrides));
}

// ---------------------------------------------------------------------------
// Envelope fields
// ---------------------------------------------------------------------------

describe('envelope fields', function () {
    it('sets schema_version to 1.3.4', function () {
        $payload = makeSerialiser()->serialise([new StubOrder()], 'realtime');
        expect($payload['schema_version'])->toBe('1.3.4');
    });

    it('sets trigger from argument', function () {
        $payload = makeSerialiser()->serialise([new StubOrder()], 'eod_batch');
        expect($payload['trigger'])->toBe('eod_batch');
    });

    it('sets source fields from config', function () {
        $payload = makeSerialiser()->serialise([new StubOrder()], 'realtime', '1');
        expect($payload['source']['type'])->toBe('cassa');
        expect($payload['source']['site_id'])->toBe('site_richmond');
        expect($payload['source']['site_name'])->toBe('The Palm Centre');
        expect($payload['source']['operator_id'])->toBe('1');
    });

    it('generates a valid UUID v4 transfer_id', function () {
        $payload = makeSerialiser()->serialise([new StubOrder()], 'realtime');
        expect($payload['transfer_id'])->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i');
    });

    it('sets generated_at as UTC ISO 8601', function () {
        $payload = makeSerialiser()->serialise([new StubOrder()], 'realtime');
        expect($payload['generated_at'])->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/');
    });
});

// ---------------------------------------------------------------------------
// §4 Example: zero-VAT item with 10 % discount
// ---------------------------------------------------------------------------

describe('§4 example: zero-VAT item with discount', function () {
    it('reproduces the canonical §4 example payload line item exactly', function () {
        // qty=2, unit_price=42.6100, discount=10.000000, vat_rate=0.00
        // line_ex_vat = 2 * 42.61 * 0.9 = 76.698 → "76.6980"
        // line_vat = 76.698 * 0 / 100 = 0 → "0.0000"
        $order = new StubOrder(
            items: [new StubLineItem(
                qty: 2,
                unitPrice: '42.6100',
                discount: '10.000000',
                vatRate: '0.00',
            )],
            ancillaries: [new StubAncillary(
                qty: 1,
                unitPrice: '34.9900',
                vatRate: '21.00',
            )],
            payments: [new StubPayment(amount: '119.0380')],
        );

        $payload = makeSerialiser()->serialise([$order], 'realtime', '1');
        $item = $payload['orders'][0]['items'][0];
        $anc = $payload['orders'][0]['ancillaries'][0];
        $totals = $payload['orders'][0]['totals'];

        expect($item['line_ex_vat'])->toBe('76.6980');
        expect($item['line_vat'])->toBe('0.0000');
        expect($anc['total_ex_vat'])->toBe('34.9900');
        expect($anc['total_vat'])->toBe('7.3500');
        expect($totals['items_net'])->toBe('76.6980');
        expect($totals['ancillaries_net'])->toBe('34.9900');
        expect($totals['total_vat'])->toBe('7.3500');
        expect($totals['grand_total'])->toBe('119.0380');
    });
});

// ---------------------------------------------------------------------------
// Standard-rate item (20 % VAT, no discount)
// ---------------------------------------------------------------------------

describe('standard-rate item', function () {
    it('computes line_ex_vat and line_vat for 20% VAT, no discount', function () {
        // qty=1, unit_price=10.0000, discount=0.000000, vat_rate=20.00
        // line_ex_vat = 1 * 10.0 * 1.0 = 10.0000
        // line_vat = 10.0 * 20/100 = 2.0000
        $order = new StubOrder(
            items: [new StubLineItem(
                qty: 1,
                unitPrice: '10.0000',
                discount: '0.000000',
                vatRate: '20.00',
            )],
            payments: [new StubPayment(amount: '12.0000')],
        );

        $payload = makeSerialiser()->serialise([$order], 'realtime');
        $item = $payload['orders'][0]['items'][0];

        expect($item['line_ex_vat'])->toBe('10.0000');
        expect($item['line_vat'])->toBe('2.0000');
        expect($payload['orders'][0]['totals']['grand_total'])->toBe('12.0000');
    });
});

// ---------------------------------------------------------------------------
// Mixed-rate order (zero-rated + standard-rated)
// ---------------------------------------------------------------------------

describe('mixed-rate order', function () {
    it('sums VAT correctly across mixed-rate lines', function () {
        // Line 1: qty=1, unit_price=50.0000, vat=0.00 → line_ex_vat=50.0000, line_vat=0.0000
        // Line 2: qty=2, unit_price=10.0000, vat=20.00 → line_ex_vat=20.0000, line_vat=4.0000
        // items_net=70.0000, total_vat=4.0000, grand_total=74.0000
        $zeroRated = new StubLineItem(qty: 1, unitPrice: '50.0000', discount: '0.000000', vatRate: '0.00');
        $standardRated = new StubLineItem(qty: 2, unitPrice: '10.0000', discount: '0.000000', vatRate: '20.00');

        $order = new StubOrder(
            items: [$zeroRated, $standardRated],
            payments: [new StubPayment(amount: '74.0000')],
        );

        $payload = makeSerialiser()->serialise([$order], 'realtime');
        $totals = $payload['orders'][0]['totals'];

        expect($totals['items_net'])->toBe('70.0000');
        expect($totals['ancillaries_net'])->toBe('0.0000');
        expect($totals['total_vat'])->toBe('4.0000');
        expect($totals['grand_total'])->toBe('74.0000');
    });
});

// ---------------------------------------------------------------------------
// Order with ancillaries
// ---------------------------------------------------------------------------

describe('order with ancillaries', function () {
    it('computes ancillary totals and includes them in grand_total', function () {
        // Item: qty=1, unit_price=100.0000, vat=0.00 → 100.0000
        // Ancillary: qty=1, unit_price=10.0000, vat=20.00 → total_ex_vat=10.0000, total_vat=2.0000
        // grand_total = 100.0000 + 10.0000 + 2.0000 = 112.0000
        $order = new StubOrder(
            items: [new StubLineItem(qty: 1, unitPrice: '100.0000', discount: '0.000000', vatRate: '0.00')],
            ancillaries: [new StubAncillary(qty: 1, unitPrice: '10.0000', vatRate: '20.00')],
            payments: [new StubPayment(amount: '112.0000')],
        );

        $payload = makeSerialiser()->serialise([$order], 'realtime');
        $anc = $payload['orders'][0]['ancillaries'][0];
        $totals = $payload['orders'][0]['totals'];

        expect($anc['total_ex_vat'])->toBe('10.0000');
        expect($anc['total_vat'])->toBe('2.0000');
        expect($totals['ancillaries_net'])->toBe('10.0000');
        expect($totals['total_vat'])->toBe('2.0000');
        expect($totals['grand_total'])->toBe('112.0000');
    });

    it('sets ancillaries_net to 0.0000 when no ancillaries', function () {
        $order = new StubOrder(
            items: [new StubLineItem(qty: 1, unitPrice: '10.0000', discount: '0.000000', vatRate: '0.00')],
            ancillaries: null,
            payments: [new StubPayment(amount: '10.0000')],
        );

        $payload = makeSerialiser()->serialise([$order], 'realtime');
        expect($payload['orders'][0]['totals']['ancillaries_net'])->toBe('0.0000');
    });
});

// ---------------------------------------------------------------------------
// Refund order
// ---------------------------------------------------------------------------

describe('refund order', function () {
    it('includes refund_of in totals', function () {
        $order = new StubOrder(
            status: 'refund',
            items: [new StubLineItem(qty: 1, unitPrice: '50.0000', discount: '0.000000', vatRate: '0.00')],
            payments: [new StubPayment(amount: '50.0000')],
            refundOf: 'core-order-abc-123',
        );

        $payload = makeSerialiser()->serialise([$order], 'manual');
        expect($payload['orders'][0]['totals']['refund_of'])->toBe('core-order-abc-123');
    });
});

// ---------------------------------------------------------------------------
// Serialiser output is accepted by PayloadValidator
// ---------------------------------------------------------------------------

describe('serialiser output passes validation', function () {
    it('produces a valid payload for a standard order', function () {
        $order = new StubOrder(
            items: [new StubLineItem()],
            ancillaries: [new StubAncillary()],
            payments: [new StubPayment(amount: '119.0380')],
        );

        $payload = makeSerialiser()->serialise([$order], 'realtime', '1');

        $result = (new \Atlasflow\OrderBridge\Validator\PayloadValidator())->validate($payload);
        expect($result->isValid())->toBeTrue(
            implode('; ', array_map(fn ($v) => $v->message, $result->getViolations()))
        );
    });
});
