<?php

declare(strict_types=1);

use Atlasflow\OrderBridge\Tests\Fixtures\ExamplePayload;
use Atlasflow\OrderBridge\Validator\PayloadValidator;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/** @return array<string, mixed> */
function validPayload(): array
{
    return ExamplePayload::valid();
}

/** @param array<string, mixed> $payload */
function validate(array $payload): \Atlasflow\OrderBridge\Validator\ValidationResult
{
    return (new PayloadValidator())->validate($payload);
}

function hasViolation(\Atlasflow\OrderBridge\Validator\ValidationResult $result, string $rule): bool
{
    foreach ($result->getViolations() as $v) {
        if ($v->rule === $rule) {
            return true;
        }
    }
    return false;
}

// ---------------------------------------------------------------------------
// §5.1 Envelope
// ---------------------------------------------------------------------------

describe('§5.1 envelope validation', function () {
    it('passes the canonical example payload', function () {
        expect(validate(validPayload())->isValid())->toBeTrue();
    });

    it('rejects wrong schema_version', function () {
        $payload = validPayload();
        $payload['schema_version'] = '1.3.3';
        expect(hasViolation(validate($payload), 'schema_version_mismatch'))->toBeTrue();
    });

    it('rejects missing schema_version', function () {
        $payload = validPayload();
        unset($payload['schema_version']);
        expect(hasViolation(validate($payload), 'schema_version_mismatch'))->toBeTrue();
    });

    it('rejects a future generated_at timestamp', function () {
        $payload = validPayload();
        $payload['generated_at'] = '2099-01-01T00:00:00Z';
        expect(hasViolation(validate($payload), 'generated_at_in_future'))->toBeTrue();
    });

    it('rejects a malformed generated_at', function () {
        $payload = validPayload();
        $payload['generated_at'] = 'not-a-date';
        expect(hasViolation(validate($payload), 'invalid_iso8601_timestamp'))->toBeTrue();
    });

    it('rejects an invalid trigger', function () {
        $payload = validPayload();
        $payload['trigger'] = 'unknown';
        expect(hasViolation(validate($payload), 'invalid_trigger'))->toBeTrue();
    });

    it('accepts all valid trigger values', function () {
        foreach (['realtime', 'eod_batch', 'manual', 'offline_flush', 'push'] as $trigger) {
            $payload = validPayload();
            $payload['trigger'] = $trigger;
            expect(validate($payload)->isValid())->toBeTrue("Trigger '{$trigger}' should be valid.");
        }
    });

    it('rejects an empty orders array', function () {
        $payload = validPayload();
        $payload['orders'] = [];
        expect(hasViolation(validate($payload), 'orders_non_empty'))->toBeTrue();
    });

    it('rejects a malformed transfer_id', function () {
        $payload = validPayload();
        $payload['transfer_id'] = 'not-a-uuid';
        expect(hasViolation(validate($payload), 'invalid_uuid_v4'))->toBeTrue();
    });

    it('accepts a valid UUID v4 transfer_id', function () {
        $payload = validPayload();
        $payload['transfer_id'] = 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d';
        expect(validate($payload)->isValid())->toBeTrue();
    });

    it('accepts payload without transfer_id (Core export)', function () {
        $payload = validPayload();
        unset($payload['transfer_id']);
        expect(validate($payload)->isValid())->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// §5.2 Order validation
// ---------------------------------------------------------------------------

describe('§5.2 order validation', function () {
    it('rejects when items array is empty', function () {
        $payload = validPayload();
        $payload['orders'][0]['items'] = [];
        expect(hasViolation(validate($payload), 'items_non_empty'))->toBeTrue();
    });

    it('rejects when payments array is empty', function () {
        $payload = validPayload();
        $payload['orders'][0]['payments'] = [];
        expect(hasViolation(validate($payload), 'payments_non_empty'))->toBeTrue();
    });

    it('rejects when payment sum does not equal grand_total', function () {
        $payload = validPayload();
        $payload['orders'][0]['payments'][0]['amount'] = '1.0000';
        expect(hasViolation(validate($payload), 'payment_sum_mismatch'))->toBeTrue();
    });

    it('rejects a refund order without refund_of', function () {
        $payload = validPayload();
        $payload['orders'][0]['status'] = 'refund';
        $payload['orders'][0]['totals']['refund_of'] = null;
        expect(hasViolation(validate($payload), 'refund_of_required'))->toBeTrue();
    });

    it('accepts a valid refund order with refund_of', function () {
        $payload = validPayload();
        $payload['orders'][0]['status'] = 'refund';
        $payload['orders'][0]['totals']['refund_of'] = 'core-order-uuid-abc123';
        expect(validate($payload)->isValid())->toBeTrue();
    });

    it('rejects account payment when customer is not registered', function () {
        $payload = validPayload();
        $payload['orders'][0]['customer'] = ['type' => 'anonymous'];
        $payload['orders'][0]['payments'][0]['method'] = 'account';
        expect(hasViolation(validate($payload), 'account_payment_requires_registered_customer'))->toBeTrue();
    });

    it('accepts account payment when customer is registered', function () {
        $payload = validPayload();
        $payload['orders'][0]['payments'][0]['method'] = 'account';
        // customer is already 'registered' in the example fixture
        expect(validate($payload)->isValid())->toBeTrue();
    });

    it('rejects an invalid order status', function () {
        $payload = validPayload();
        $payload['orders'][0]['status'] = 'completed';
        expect(hasViolation(validate($payload), 'invalid_status'))->toBeTrue();
    });

    it('accepts all valid status values', function () {
        foreach (['pos', 'order'] as $status) {
            $payload = validPayload();
            $payload['orders'][0]['status'] = $status;
            expect(validate($payload)->isValid())->toBeTrue("Status '{$status}' should be valid.");
        }
    });
});

// ---------------------------------------------------------------------------
// §5.3 Line item arithmetic
// ---------------------------------------------------------------------------

describe('§5.3 line item arithmetic', function () {
    it('rejects a non-numeric line_ex_vat', function () {
        $payload = validPayload();
        $payload['orders'][0]['items'][0]['line_ex_vat'] = 'not-a-number';
        expect(hasViolation(validate($payload), 'invalid_decimal'))->toBeTrue();
    });

    it('rejects line_ex_vat outside tolerance', function () {
        $payload = validPayload();
        $payload['orders'][0]['items'][0]['line_ex_vat'] = '99.9999';
        // Adjust totals to avoid cascading failures on other rules
        $payload['orders'][0]['totals']['items_net'] = '99.9999';
        $payload['orders'][0]['totals']['grand_total'] = '142.3378';
        $payload['orders'][0]['payments'][0]['amount'] = '142.3378';
        expect(hasViolation(validate($payload), 'line_ex_vat_arithmetic'))->toBeTrue();
    });

    it('rejects line_vat outside tolerance', function () {
        $payload = validPayload();
        $payload['orders'][0]['items'][0]['vat_rate'] = '20.00';
        $payload['orders'][0]['items'][0]['line_vat'] = '99.0000'; // wrong
        expect(hasViolation(validate($payload), 'line_vat_arithmetic'))->toBeTrue();
    });

    it('rejects when items_net does not match sum of line_ex_vat', function () {
        $payload = validPayload();
        $payload['orders'][0]['totals']['items_net'] = '99.0000';
        $payload['orders'][0]['totals']['grand_total'] = '141.3379';
        $payload['orders'][0]['payments'][0]['amount'] = '141.3379';
        expect(hasViolation(validate($payload), 'items_net_mismatch'))->toBeTrue();
    });

    it('rejects when total_vat does not match sum of all VAT', function () {
        $payload = validPayload();
        $payload['orders'][0]['totals']['total_vat'] = '99.0000';
        $payload['orders'][0]['totals']['grand_total'] = '210.6880';
        $payload['orders'][0]['payments'][0]['amount'] = '210.6880';
        expect(hasViolation(validate($payload), 'total_vat_mismatch'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// §5.3 Ancillary arithmetic
// ---------------------------------------------------------------------------

describe('§5.3 ancillary arithmetic', function () {
    it('rejects total_ex_vat outside tolerance', function () {
        $payload = validPayload();
        $payload['orders'][0]['ancillaries'][0]['total_ex_vat'] = '3.9990'; // stale wrong value
        expect(hasViolation(validate($payload), 'total_ex_vat_arithmetic'))->toBeTrue();
    });

    it('rejects total_vat outside tolerance', function () {
        $payload = validPayload();
        $payload['orders'][0]['ancillaries'][0]['total_vat'] = '99.0000';
        expect(hasViolation(validate($payload), 'total_vat_arithmetic'))->toBeTrue();
    });

    it('rejects ancillaries_net mismatch', function () {
        $payload = validPayload();
        $payload['orders'][0]['totals']['ancillaries_net'] = '99.0000';
        expect(hasViolation(validate($payload), 'ancillaries_net_mismatch'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// §5.3 Totals
// ---------------------------------------------------------------------------

describe('§5.3 grand_total arithmetic', function () {
    it('rejects grand_total outside tolerance', function () {
        $payload = validPayload();
        $payload['orders'][0]['totals']['grand_total'] = '1.0000';
        $payload['orders'][0]['payments'][0]['amount'] = '1.0000';
        expect(hasViolation(validate($payload), 'grand_total_arithmetic'))->toBeTrue();
    });
});

// ---------------------------------------------------------------------------
// ValidationResult::assertValid
// ---------------------------------------------------------------------------

describe('ValidationResult::assertValid', function () {
    it('does not throw on a valid payload', function () {
        $result = validate(validPayload());
        $result->assertValid(); // must not throw
        expect($result->isValid())->toBeTrue();
    });

    it('throws ValidationException on an invalid payload', function () {
        $payload = validPayload();
        $payload['schema_version'] = 'bad';
        $result = validate($payload);
        expect(fn () => $result->assertValid())
            ->toThrow(\Atlasflow\OrderBridge\Exceptions\ValidationException::class);
    });
});
