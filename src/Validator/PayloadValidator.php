<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Validator;

use Atlasflow\OrderBridge\SchemaVersion;
use Atlasflow\OrderBridge\Support\DecimalMath;

/**
 * Validates a transfer payload array against all rules defined in §5 of the
 * Atlas Core Order Bridge API specification (version 1.3.4).
 *
 * Validates the envelope (§5.1), each order (§5.2), each line item and
 * ancillary (§5.3), and idempotency fields (§5.4).
 *
 * Returns a ValidationResult containing all violations found. The caller
 * decides whether to throw (via assertValid()) or enumerate violations.
 */
final class PayloadValidator
{
    private const array VALID_TRIGGERS = ['realtime', 'eod_batch', 'manual', 'offline_flush', 'push'];
    private const array VALID_STATUSES = ['pos', 'order', 'refund'];
    private const array VALID_CUSTOMER_TYPES = ['anonymous', 'registered', 'new'];
    private const array VALID_FULFILMENT_TYPES = ['collection', 'delivery'];
    private const array VALID_PAYMENT_METHODS = ['cash', 'card', 'account', 'online', 'gift_card'];
    private const array VALID_SOURCE_TYPES = ['core', 'cassa', 'website', 'app'];

    private const string UUID_V4_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';
    private const string ISO8601_UTC_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/';

    /**
     * @param array<string, mixed> $payload
     */
    public function validate(array $payload): ValidationResult
    {
        $result = new ValidationResult();

        $this->validateEnvelope($payload, $result);

        if (!isset($payload['orders']) || !is_array($payload['orders'])) {
            return $result;
        }

        foreach ($payload['orders'] as $i => $order) {
            if (!is_array($order)) {
                $result->addViolation(new ValidationViolation(
                    "orders.{$i}",
                    'order_must_be_object',
                    "Order at index {$i} must be an object.",
                ));
                continue;
            }
            $this->validateOrder($order, $i, $result);
        }

        return $result;
    }

    // -------------------------------------------------------------------------
    // §5.1 Envelope validation
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $payload */
    private function validateEnvelope(array $payload, ValidationResult $result): void
    {
        // schema_version must be "1.3.4"
        $schemaVersion = $payload['schema_version'] ?? null;
        if ($schemaVersion !== SchemaVersion::SCHEMA_VERSION) {
            $result->addViolation(new ValidationViolation(
                'schema_version',
                'schema_version_mismatch',
                'schema_version must be "' . SchemaVersion::SCHEMA_VERSION . '".',
                SchemaVersion::SCHEMA_VERSION,
                $schemaVersion,
            ));
        }

        // generated_at must be a valid ISO 8601 UTC timestamp, current or in the past
        $generatedAt = $payload['generated_at'] ?? null;
        if (!is_string($generatedAt) || !preg_match(self::ISO8601_UTC_PATTERN, $generatedAt)) {
            $result->addViolation(new ValidationViolation(
                'generated_at',
                'invalid_iso8601_timestamp',
                'generated_at must be a valid ISO 8601 UTC timestamp (YYYY-MM-DDThh:mm:ssZ).',
                null,
                $generatedAt,
            ));
        } elseif (strtotime($generatedAt) > time()) {
            $result->addViolation(new ValidationViolation(
                'generated_at',
                'generated_at_in_future',
                'generated_at must be current or in the past.',
                null,
                $generatedAt,
            ));
        }

        // trigger must be a valid enum value
        $trigger = $payload['trigger'] ?? null;
        if (!in_array($trigger, self::VALID_TRIGGERS, true)) {
            $result->addViolation(new ValidationViolation(
                'trigger',
                'invalid_trigger',
                'trigger must be one of: ' . implode(', ', self::VALID_TRIGGERS) . '.',
                self::VALID_TRIGGERS,
                $trigger,
            ));
        }

        // source validation
        $source = $payload['source'] ?? null;
        if (!is_array($source)) {
            $result->addViolation(new ValidationViolation(
                'source',
                'source_required',
                'source must be an object.',
            ));
        } else {
            $this->validateSource($source, $result);
        }

        // orders must be a non-empty array
        $orders = $payload['orders'] ?? null;
        if (!is_array($orders) || $orders === []) {
            $result->addViolation(new ValidationViolation(
                'orders',
                'orders_non_empty',
                'orders must be a non-empty array.',
                null,
                $orders,
            ));
        }

        // transfer_id, when present, must be a valid UUID v4 (§5.1 / §5.4)
        if (array_key_exists('transfer_id', $payload) && $payload['transfer_id'] !== null) {
            $transferId = $payload['transfer_id'];
            if (!is_string($transferId) || !preg_match(self::UUID_V4_PATTERN, $transferId)) {
                $result->addViolation(new ValidationViolation(
                    'transfer_id',
                    'invalid_uuid_v4',
                    'transfer_id must be a valid UUID v4.',
                    null,
                    $transferId,
                ));
            }
        }
    }

    /** @param array<string, mixed> $source */
    private function validateSource(array $source, ValidationResult $result): void
    {
        $type = $source['type'] ?? null;
        if (!in_array($type, self::VALID_SOURCE_TYPES, true)) {
            $result->addViolation(new ValidationViolation(
                'source.type',
                'invalid_source_type',
                'source.type must be one of: ' . implode(', ', self::VALID_SOURCE_TYPES) . '.',
                self::VALID_SOURCE_TYPES,
                $type,
            ));
        }

        foreach (['site_id', 'site_name'] as $required) {
            if (empty($source[$required])) {
                $result->addViolation(new ValidationViolation(
                    "source.{$required}",
                    'field_required',
                    "source.{$required} is required.",
                ));
            }
        }
    }

    // -------------------------------------------------------------------------
    // §5.2 Order validation
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $order */
    private function validateOrder(array $order, int $index, ValidationResult $result): void
    {
        $prefix = "orders.{$index}";

        // Required string fields
        foreach (['origin_ref', 'status', 'channel', 'ordered_at'] as $field) {
            if (empty($order[$field])) {
                $result->addViolation(new ValidationViolation(
                    "{$prefix}.{$field}",
                    'field_required',
                    "{$prefix}.{$field} is required.",
                ));
            }
        }

        // status enum
        $status = $order['status'] ?? null;
        if (!in_array($status, self::VALID_STATUSES, true)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.status",
                'invalid_status',
                "{$prefix}.status must be one of: " . implode(', ', self::VALID_STATUSES) . '.',
                self::VALID_STATUSES,
                $status,
            ));
        }

        // ordered_at timestamp
        $orderedAt = $order['ordered_at'] ?? null;
        if (is_string($orderedAt) && !preg_match(self::ISO8601_UTC_PATTERN, $orderedAt)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.ordered_at",
                'invalid_iso8601_timestamp',
                "{$prefix}.ordered_at must be a valid ISO 8601 UTC timestamp.",
                null,
                $orderedAt,
            ));
        }

        // items must contain at least one line item
        $items = $order['items'] ?? null;
        if (!is_array($items) || $items === []) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.items",
                'items_non_empty',
                "{$prefix}.items must contain at least one line item.",
            ));
            $items = [];
        }

        // payments must contain at least one entry
        $payments = $order['payments'] ?? null;
        if (!is_array($payments) || $payments === []) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.payments",
                'payments_non_empty',
                "{$prefix}.payments must contain at least one payment entry.",
            ));
            $payments = [];
        }

        // customer validation
        $customer = $order['customer'] ?? null;
        if (!is_array($customer)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.customer",
                'customer_required',
                "{$prefix}.customer is required.",
            ));
            $customer = [];
        } else {
            $this->validateCustomer($customer, $prefix, $result);
        }

        // fulfilment validation
        $fulfilment = $order['fulfilment'] ?? null;
        if (!is_array($fulfilment)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.fulfilment",
                'fulfilment_required',
                "{$prefix}.fulfilment is required.",
            ));
        } else {
            $this->validateFulfilment($fulfilment, $prefix, $result);
        }

        // totals validation
        $totals = $order['totals'] ?? null;
        if (!is_array($totals)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.totals",
                'totals_required',
                "{$prefix}.totals is required.",
            ));
            $totals = [];
        }

        // refund rules
        if ($status === 'refund') {
            $refundOf = $totals['refund_of'] ?? null;
            if (empty($refundOf)) {
                $result->addViolation(new ValidationViolation(
                    "{$prefix}.totals.refund_of",
                    'refund_of_required',
                    "{$prefix}.totals.refund_of is required for refund orders.",
                ));
            }

            // All monetary amounts must be positive for refund orders
            $grandTotal = $totals['grand_total'] ?? null;
            if (is_string($grandTotal) && is_numeric($grandTotal) && bccomp($grandTotal, '0', SchemaVersion::MONETARY_SCALE) <= 0) {
                $result->addViolation(new ValidationViolation(
                    "{$prefix}.totals.grand_total",
                    'refund_amounts_positive',
                    'Refund order amounts must be positive.',
                    null,
                    $grandTotal,
                ));
            }
        }

        // "account" payment method requires customer.type "registered"
        foreach ($payments as $pi => $payment) {
            if (is_array($payment) && ($payment['method'] ?? null) === 'account') {
                $customerType = $customer['type'] ?? null;
                if ($customerType !== 'registered') {
                    $result->addViolation(new ValidationViolation(
                        "{$prefix}.payments.{$pi}.method",
                        'account_payment_requires_registered_customer',
                        'Payment method "account" requires customer.type to be "registered".',
                        'registered',
                        $customerType,
                    ));
                }
            }
        }

        // Validate individual items
        $ancillaries = is_array($order['ancillaries'] ?? null) ? $order['ancillaries'] : [];
        $this->validateLineItems($items, $ancillaries, $totals, $prefix, $result);

        // Validate payments and sum
        $this->validatePayments($payments, $totals, $prefix, $result);
    }

    /** @param array<string, mixed> $customer */
    private function validateCustomer(array $customer, string $prefix, ValidationResult $result): void
    {
        $type = $customer['type'] ?? null;
        if (!in_array($type, self::VALID_CUSTOMER_TYPES, true)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.customer.type",
                'invalid_customer_type',
                "{$prefix}.customer.type must be one of: " . implode(', ', self::VALID_CUSTOMER_TYPES) . '.',
                self::VALID_CUSTOMER_TYPES,
                $type,
            ));
        }

        if ($type === 'registered' && empty($customer['id'])) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.customer.id",
                'registered_customer_id_required',
                "{$prefix}.customer.id is required when customer.type is \"registered\".",
            ));
        }
    }

    /** @param array<string, mixed> $fulfilment */
    private function validateFulfilment(array $fulfilment, string $prefix, ValidationResult $result): void
    {
        $type = $fulfilment['type'] ?? null;
        if (!in_array($type, self::VALID_FULFILMENT_TYPES, true)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.fulfilment.type",
                'invalid_fulfilment_type',
                "{$prefix}.fulfilment.type must be one of: " . implode(', ', self::VALID_FULFILMENT_TYPES) . '.',
                self::VALID_FULFILMENT_TYPES,
                $type,
            ));
        }

        if ($type === 'delivery' && empty($fulfilment['delivery_address'])) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.fulfilment.delivery_address",
                'delivery_address_required',
                "{$prefix}.fulfilment.delivery_address is required when fulfilment.type is \"delivery\".",
            ));
        }
    }

    // -------------------------------------------------------------------------
    // §5.3 Line item and ancillary validation
    // -------------------------------------------------------------------------

    /**
     * @param array<int, mixed> $items
     * @param array<int, mixed> $ancillaries
     * @param array<string, mixed> $totals
     */
    private function validateLineItems(
        array $items,
        array $ancillaries,
        array $totals,
        string $prefix,
        ValidationResult $result,
    ): void {
        $itemsNetSum = '0';
        $totalVatSum = '0';

        foreach ($items as $i => $item) {
            if (!is_array($item)) {
                continue;
            }

            $linePrefix = "{$prefix}.items.{$i}";
            $this->validateDecimalFields($item, ['unit_price', 'discount', 'vat_rate', 'line_ex_vat', 'line_vat'], $linePrefix, $result);

            if (!is_numeric($item['unit_price'] ?? null)
                || !is_numeric($item['discount'] ?? null)
                || !is_numeric($item['vat_rate'] ?? null)
                || !is_numeric($item['line_ex_vat'] ?? null)
                || !is_numeric($item['line_vat'] ?? null)
                || !is_numeric($item['qty'] ?? null)
            ) {
                continue;
            }

            $qty = (string) $item['qty'];
            $unitPrice = (string) $item['unit_price'];
            $discount = (string) $item['discount'];
            $vatRate = (string) $item['vat_rate'];
            $lineExVat = (string) $item['line_ex_vat'];
            $lineVat = (string) $item['line_vat'];

            $scale = SchemaVersion::MONETARY_SCALE + 6;

            // line_ex_vat = qty × unit_price × (100 − discount) / 100
            $factor = DecimalMath::divide(
                DecimalMath::subtract('100', $discount, $scale),
                '100',
                $scale,
            );
            $expectedLineExVat = DecimalMath::multiply(
                DecimalMath::multiply($qty, $unitPrice, $scale),
                $factor,
                $scale,
            );

            if (!DecimalMath::withinTolerance($lineExVat, $expectedLineExVat, SchemaVersion::TOLERANCE)) {
                $result->addViolation(new ValidationViolation(
                    "{$linePrefix}.line_ex_vat",
                    'line_ex_vat_arithmetic',
                    "line_ex_vat does not match qty × unit_price × (100 − discount) / 100 within tolerance.",
                    DecimalMath::format($expectedLineExVat, SchemaVersion::MONETARY_SCALE),
                    $lineExVat,
                ));
            }

            // line_vat = ceil(line_ex_vat × vat_rate / 100, VAT_ROUNDING_SCALE)
            $expectedLineVat = DecimalMath::ceil(
                DecimalMath::divide(
                    DecimalMath::multiply($lineExVat, $vatRate, $scale),
                    '100',
                    $scale,
                ),
                SchemaVersion::VAT_ROUNDING_SCALE,
            );

            if (!DecimalMath::withinTolerance($lineVat, $expectedLineVat, SchemaVersion::TOLERANCE)) {
                $result->addViolation(new ValidationViolation(
                    "{$linePrefix}.line_vat",
                    'line_vat_arithmetic',
                    "line_vat does not match line_ex_vat × vat_rate / 100 within tolerance.",
                    DecimalMath::format($expectedLineVat, SchemaVersion::MONETARY_SCALE),
                    $lineVat,
                ));
            }

            $itemsNetSum = DecimalMath::add($itemsNetSum, $lineExVat, $scale);
            $totalVatSum = DecimalMath::add($totalVatSum, $lineVat, $scale);
        }

        // Validate ancillaries and accumulate into totals
        $ancillariesNetSum = '0';
        foreach ($ancillaries as $i => $anc) {
            if (!is_array($anc)) {
                continue;
            }

            $ancPrefix = "{$prefix}.ancillaries.{$i}";
            $this->validateDecimalFields($anc, ['unit_price', 'vat_rate', 'total_ex_vat', 'total_vat'], $ancPrefix, $result);

            if (!is_numeric($anc['unit_price'] ?? null)
                || !is_numeric($anc['vat_rate'] ?? null)
                || !is_numeric($anc['total_ex_vat'] ?? null)
                || !is_numeric($anc['total_vat'] ?? null)
                || !is_numeric($anc['qty'] ?? null)
            ) {
                continue;
            }

            $qty = (string) $anc['qty'];
            $unitPrice = (string) $anc['unit_price'];
            $vatRate = (string) $anc['vat_rate'];
            $totalExVat = (string) $anc['total_ex_vat'];
            $totalVat = (string) $anc['total_vat'];

            $scale = SchemaVersion::MONETARY_SCALE + 6;

            // total_ex_vat = qty × unit_price
            $expectedTotalExVat = DecimalMath::multiply($qty, $unitPrice, $scale);
            if (!DecimalMath::withinTolerance($totalExVat, $expectedTotalExVat, SchemaVersion::TOLERANCE)) {
                $result->addViolation(new ValidationViolation(
                    "{$ancPrefix}.total_ex_vat",
                    'total_ex_vat_arithmetic',
                    "total_ex_vat does not match qty × unit_price within tolerance.",
                    DecimalMath::format($expectedTotalExVat, SchemaVersion::MONETARY_SCALE),
                    $totalExVat,
                ));
            }

            // total_vat = ceil(total_ex_vat × vat_rate / 100, VAT_ROUNDING_SCALE)
            $expectedTotalVat = DecimalMath::ceil(
                DecimalMath::divide(
                    DecimalMath::multiply($totalExVat, $vatRate, $scale),
                    '100',
                    $scale,
                ),
                SchemaVersion::VAT_ROUNDING_SCALE,
            );
            if (!DecimalMath::withinTolerance($totalVat, $expectedTotalVat, SchemaVersion::TOLERANCE)) {
                $result->addViolation(new ValidationViolation(
                    "{$ancPrefix}.total_vat",
                    'total_vat_arithmetic',
                    "total_vat does not match total_ex_vat × vat_rate / 100 within tolerance.",
                    DecimalMath::format($expectedTotalVat, SchemaVersion::MONETARY_SCALE),
                    $totalVat,
                ));
            }

            $ancillariesNetSum = DecimalMath::add($ancillariesNetSum, $totalExVat, $scale);
            $totalVatSum = DecimalMath::add($totalVatSum, $totalVat, $scale);
        }

        $scale = SchemaVersion::MONETARY_SCALE + 6;

        // sum(line_ex_vat) = totals.items_net
        $itemsNet = (string) ($totals['items_net'] ?? '0');
        if (is_numeric($itemsNet) && !DecimalMath::withinTolerance($itemsNetSum, $itemsNet, SchemaVersion::TOLERANCE)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.totals.items_net",
                'items_net_mismatch',
                'totals.items_net does not equal the sum of all line_ex_vat values within tolerance.',
                DecimalMath::format($itemsNetSum, SchemaVersion::MONETARY_SCALE),
                $itemsNet,
            ));
        }

        // sum(total_ex_vat across ancillaries) = totals.ancillaries_net
        $ancillariesNet = (string) ($totals['ancillaries_net'] ?? '0');
        if (is_numeric($ancillariesNet) && !DecimalMath::withinTolerance($ancillariesNetSum, $ancillariesNet, SchemaVersion::TOLERANCE)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.totals.ancillaries_net",
                'ancillaries_net_mismatch',
                'totals.ancillaries_net does not equal the sum of all ancillary total_ex_vat values within tolerance.',
                DecimalMath::format($ancillariesNetSum, SchemaVersion::MONETARY_SCALE),
                $ancillariesNet,
            ));
        }

        // sum(line_vat + ancillary total_vat) = totals.total_vat
        $totalVat = (string) ($totals['total_vat'] ?? '0');
        if (is_numeric($totalVat) && !DecimalMath::withinTolerance($totalVatSum, $totalVat, SchemaVersion::TOLERANCE)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.totals.total_vat",
                'total_vat_mismatch',
                'totals.total_vat does not equal the sum of all line_vat and ancillary total_vat values within tolerance.',
                DecimalMath::format($totalVatSum, SchemaVersion::MONETARY_SCALE),
                $totalVat,
            ));
        }

        // grand_total = items_net + ancillaries_net + total_vat
        $grandTotal = (string) ($totals['grand_total'] ?? '0');
        if (is_numeric($grandTotal) && is_numeric($itemsNet) && is_numeric($ancillariesNet) && is_numeric($totalVat)) {
            $expectedGrandTotal = DecimalMath::add(
                DecimalMath::add($itemsNet, $ancillariesNet, $scale),
                $totalVat,
                $scale,
            );
            if (!DecimalMath::withinTolerance($grandTotal, $expectedGrandTotal, SchemaVersion::TOLERANCE)) {
                $result->addViolation(new ValidationViolation(
                    "{$prefix}.totals.grand_total",
                    'grand_total_arithmetic',
                    'grand_total does not equal items_net + ancillaries_net + total_vat within tolerance.',
                    DecimalMath::format($expectedGrandTotal, SchemaVersion::MONETARY_SCALE),
                    $grandTotal,
                ));
            }
        }
    }

    /**
     * @param array<int, mixed> $payments
     * @param array<string, mixed> $totals
     */
    private function validatePayments(array $payments, array $totals, string $prefix, ValidationResult $result): void
    {
        $scale = SchemaVersion::MONETARY_SCALE + 4;
        $paymentSum = '0';

        foreach ($payments as $i => $payment) {
            if (!is_array($payment)) {
                continue;
            }

            $payPrefix = "{$prefix}.payments.{$i}";

            $method = $payment['method'] ?? null;
            if (!in_array($method, self::VALID_PAYMENT_METHODS, true)) {
                $result->addViolation(new ValidationViolation(
                    "{$payPrefix}.method",
                    'invalid_payment_method',
                    "{$payPrefix}.method must be one of: " . implode(', ', self::VALID_PAYMENT_METHODS) . '.',
                    self::VALID_PAYMENT_METHODS,
                    $method,
                ));
            }

            $amount = $payment['amount'] ?? null;
            if (!is_numeric($amount)) {
                $result->addViolation(new ValidationViolation(
                    "{$payPrefix}.amount",
                    'invalid_decimal',
                    "{$payPrefix}.amount must be a numeric decimal string.",
                ));
            } else {
                $paymentSum = DecimalMath::add($paymentSum, (string) $amount, $scale);
            }

            $date = $payment['date'] ?? null;
            if (!is_string($date) || !preg_match(self::ISO8601_UTC_PATTERN, $date)) {
                $result->addViolation(new ValidationViolation(
                    "{$payPrefix}.date",
                    'invalid_iso8601_timestamp',
                    "{$payPrefix}.date must be a valid ISO 8601 UTC timestamp.",
                ));
            }
        }

        // sum of payment amounts must equal grand_total
        $grandTotal = (string) ($totals['grand_total'] ?? '0');
        if (is_numeric($grandTotal) && !DecimalMath::withinTolerance($paymentSum, $grandTotal, SchemaVersion::TOLERANCE)) {
            $result->addViolation(new ValidationViolation(
                "{$prefix}.payments",
                'payment_sum_mismatch',
                'The sum of all payment amounts must equal totals.grand_total within tolerance.',
                $grandTotal,
                DecimalMath::format($paymentSum, SchemaVersion::MONETARY_SCALE),
            ));
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $fields
     */
    private function validateDecimalFields(array $data, array $fields, string $prefix, ValidationResult $result): void
    {
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (!is_numeric($value)) {
                $result->addViolation(new ValidationViolation(
                    "{$prefix}.{$field}",
                    'invalid_decimal',
                    "{$prefix}.{$field} must be a parseable numeric decimal string.",
                    null,
                    $value,
                ));
            }
        }
    }
}
