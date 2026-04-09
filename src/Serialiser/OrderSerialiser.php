<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Serialiser;

use Atlasflow\OrderBridge\Contracts\AncillaryInterface;
use Atlasflow\OrderBridge\Contracts\CustomerInterface;
use Atlasflow\OrderBridge\Contracts\FulfilmentInterface;
use Atlasflow\OrderBridge\Contracts\LineItemInterface;
use Atlasflow\OrderBridge\Contracts\OrderInterface;
use Atlasflow\OrderBridge\Contracts\PaymentInterface;
use Atlasflow\OrderBridge\Dto\NoteDto;
use Atlasflow\OrderBridge\SchemaVersion;
use Atlasflow\OrderBridge\Support\DecimalMath;
use Atlasflow\OrderBridge\Support\TransferIdManager;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Converts OrderInterface implementations into a spec-compliant transfer
 * envelope array ready for JSON encoding.
 *
 * The Serialiser owns all arithmetic (§3.5, §3.6, §3.8). The host application
 * provides only raw input values through the Contracts interfaces; all computed
 * fields (line_ex_vat, line_vat, total_ex_vat, total_vat, totals.*) are derived
 * here using DecimalMath.
 *
 * @example
 *   $serialiser = new OrderSerialiser(
 *       config: ['site_id' => 'site_richmond', 'site_name' => 'The Palm Centre', 'source_type' => 'cassa'],
 *   );
 *   $payload = $serialiser->serialise($orders, 'realtime', operatorId: '1');
 *   // Pass $payload to the Transport layer or queue it for later sending.
 */
final class OrderSerialiser
{
    /**
     * Intermediate arithmetic scale. High enough to avoid drift before
     * rounding to the final 4 or 6 decimal place output.
     */
    private const int CALC_SCALE = 10;

    /**
     * @param array{site_id: string, site_name: string, source_type: string} $config
     *   source_type: one of "core", "cassa", "website", "app" (§2.2).
     */
    public function __construct(
        private readonly array $config,
        private readonly TransferIdManager $transferIdManager = new TransferIdManager(),
    ) {
    }

    /**
     * Serialise one or more orders into a transfer envelope array.
     *
     * @param iterable<OrderInterface> $orders   One or more order objects.
     * @param string                   $trigger  Transfer trigger value (§1.2).
     * @param string|null              $operatorId Envelope-level operator ID. Null for automated transfers.
     * @return array<string, mixed>
     */
    public function serialise(
        iterable $orders,
        string $trigger,
        ?string $operatorId = null,
    ): array {
        $serialisedOrders = [];
        foreach ($orders as $order) {
            $serialisedOrders[] = $this->serialiseOrder($order);
        }

        return [
            'schema_version' => SchemaVersion::SCHEMA_VERSION,
            'generated_at' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z'),
            'transfer_id' => $this->transferIdManager->generate(),
            'trigger' => $trigger,
            'source' => [
                'type' => $this->config['source_type'],
                'site_id' => $this->config['site_id'],
                'site_name' => $this->config['site_name'],
                'operator_id' => $operatorId,
            ],
            'orders' => $serialisedOrders,
        ];
    }

    // -------------------------------------------------------------------------
    // Order
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serialiseOrder(OrderInterface $order): array
    {
        $items = array_map([$this, 'serialiseItem'], $order->getItems());
        $ancillaries = $order->getAncillaries() !== null
            ? array_map([$this, 'serialiseAncillary'], $order->getAncillaries())
            : null;
        $notes = $order->getNotes() !== null
            ? array_map([$this, 'serialiseNote'], $order->getNotes())
            : null;

        $totals = $this->computeTotals($items, $ancillaries ?? [], $order->getRefundOf());

        return [
            'origin_ref' => $order->getOriginRef(),
            'status' => $order->getStatus(),
            'channel' => $order->getChannel(),
            'operator_id' => $order->getOperatorId(),
            'ordered_at' => $order->getOrderedAt(),
            'notes' => $notes,
            'customer' => $this->serialiseCustomer($order->getCustomer()),
            'fulfilment' => $this->serialiseFulfilment($order->getFulfilment()),
            'items' => $items,
            'ancillaries' => $ancillaries,
            'totals' => $totals,
            'payments' => array_map([$this, 'serialisePayment'], $order->getPayments()),
            'signature' => $order->getSignature(),
        ];
    }

    // -------------------------------------------------------------------------
    // Line item (§3.5)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serialiseItem(LineItemInterface $item): array
    {
        $qty = (string) $item->getQty();
        $unitPrice = $item->getUnitPrice();
        $discount = $item->getDiscount();
        $vatRate = $item->getVatRate();

        // line_ex_vat = qty × unit_price × (100 − discount) / 100
        $discountFactor = DecimalMath::divide(
            DecimalMath::subtract('100', $discount, self::CALC_SCALE),
            '100',
            self::CALC_SCALE,
        );
        $lineExVat = DecimalMath::format(
            DecimalMath::multiply(
                DecimalMath::multiply($qty, $unitPrice, self::CALC_SCALE),
                $discountFactor,
                self::CALC_SCALE,
            ),
            SchemaVersion::monetaryScale(),
        );

        // line_vat = round(line_ex_vat × vat_rate / 100, vatRoundingScale, vatRoundingMode)
        $lineVat = DecimalMath::format(
            DecimalMath::round(
                DecimalMath::divide(
                    DecimalMath::multiply($lineExVat, $vatRate, self::CALC_SCALE),
                    '100',
                    self::CALC_SCALE,
                ),
                SchemaVersion::vatRoundingScale(),
                SchemaVersion::vatRoundingMode(),
            ),
            SchemaVersion::monetaryScale(),
        );

        $note = $item->getNotes() !== null
            ? $this->serialiseNote($item->getNotes())
            : null;

        return [
            'sku' => $item->getSku(),
            'name' => $item->getName(),
            'qty' => $item->getQty(),
            'uom' => $item->getUom(),
            'unit_price' => $unitPrice,
            'discount' => $discount,
            'vat_rate' => $vatRate,
            'batch' => $item->getBatch(),
            'passport' => $item->getPassport(),
            'note' => $note,
            'line_ex_vat' => $lineExVat,
            'line_vat' => $lineVat,
        ];
    }

    // -------------------------------------------------------------------------
    // Ancillary (§3.8)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serialiseAncillary(AncillaryInterface $ancillary): array
    {
        $qty = (string) $ancillary->getQty();
        $unitPrice = $ancillary->getUnitPrice();
        $vatRate = $ancillary->getVatRate();

        // total_ex_vat = qty × unit_price
        $totalExVat = DecimalMath::format(
            DecimalMath::multiply($qty, $unitPrice, self::CALC_SCALE),
            SchemaVersion::monetaryScale(),
        );

        // total_vat = round(total_ex_vat × vat_rate / 100, vatRoundingScale, vatRoundingMode)
        $totalVat = DecimalMath::format(
            DecimalMath::round(
                DecimalMath::divide(
                    DecimalMath::multiply($totalExVat, $vatRate, self::CALC_SCALE),
                    '100',
                    self::CALC_SCALE,
                ),
                SchemaVersion::vatRoundingScale(),
                SchemaVersion::vatRoundingMode(),
            ),
            SchemaVersion::monetaryScale(),
        );

        return [
            'category' => $ancillary->getCategory(),
            'qty' => $ancillary->getQty(),
            'unit_price' => $unitPrice,
            'vat_rate' => $vatRate,
            'description' => $ancillary->getDescription(),
            'total_ex_vat' => $totalExVat,
            'total_vat' => $totalVat,
        ];
    }

    // -------------------------------------------------------------------------
    // Note
    // -------------------------------------------------------------------------

    /** @return array<string, string> */
    private function serialiseNote(NoteDto $note): array
    {
        return [
            'type' => $note->type,
            'note' => $note->note,
            'created_by' => $note->createdBy,
            'created_at' => $note->createdAt,
        ];
    }

    // -------------------------------------------------------------------------
    // Totals (§3.6)
    // -------------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $items       Already-serialised line items.
     * @param array<int, array<string, mixed>> $ancillaries Already-serialised ancillary items.
     * @return array<string, mixed>
     */
    private function computeTotals(array $items, array $ancillaries, ?string $refundOf): array
    {
        $scale = self::CALC_SCALE;
        $itemsNet = '0';
        $totalVat = '0';

        foreach ($items as $item) {
            $itemsNet = DecimalMath::add($itemsNet, (string) $item['line_ex_vat'], $scale);
            $totalVat = DecimalMath::add($totalVat, (string) $item['line_vat'], $scale);
        }

        $ancillariesNet = '0';
        foreach ($ancillaries as $anc) {
            $ancillariesNet = DecimalMath::add($ancillariesNet, (string) $anc['total_ex_vat'], $scale);
            $totalVat = DecimalMath::add($totalVat, (string) $anc['total_vat'], $scale);
        }

        $grandTotal = DecimalMath::add(
            DecimalMath::add($itemsNet, $ancillariesNet, $scale),
            $totalVat,
            $scale,
        );

        return [
            'items_net' => DecimalMath::format($itemsNet, SchemaVersion::monetaryScale()),
            'ancillaries_net' => DecimalMath::format($ancillariesNet, SchemaVersion::monetaryScale()),
            'total_vat' => DecimalMath::format($totalVat, SchemaVersion::monetaryScale()),
            'grand_total' => DecimalMath::format($grandTotal, SchemaVersion::monetaryScale()),
            'refund_of' => $refundOf,
        ];
    }

    // -------------------------------------------------------------------------
    // Customer (§3.3)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serialiseCustomer(CustomerInterface $customer): array
    {
        return [
            'type' => $customer->getType(),
            'id' => $customer->getId(),
            'name' => $customer->getName(),
            'email' => $customer->getEmail(),
            'phone' => $customer->getPhone(),
            'address' => $customer->getAddress(),
            'contact' => $customer->getContact(),
        ];
    }

    // -------------------------------------------------------------------------
    // Fulfilment (§3.4)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serialiseFulfilment(FulfilmentInterface $fulfilment): array
    {
        $slot = $fulfilment->getDeliverySlot();
        $notes = $fulfilment->getNotes() !== null
            ? array_map([$this, 'serialiseNote'], $fulfilment->getNotes())
            : null;

        return [
            'type' => $fulfilment->getType(),
            'delivery_slot' => $slot !== null ? [
                'delivery_on' => $slot['delivery_on'],
                'booking_id' => $slot['booking_id'] ?? null,
                'notes' => $slot['notes'] ?? null,
            ] : null,
            'delivery_address' => $fulfilment->getDeliveryAddress(),
            'notes' => $notes,
        ];
    }

    // -------------------------------------------------------------------------
    // Payment (§3.7)
    // -------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function serialisePayment(PaymentInterface $payment): array
    {
        return [
            'method' => $payment->getMethod(),
            'amount' => $payment->getAmount(),
            'reference' => $payment->getReference(),
            'date' => $payment->getDate(),
        ];
    }
}
