<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Parser;

use Atlasflow\OrderBridge\Dto\AddressDto;
use Atlasflow\OrderBridge\Dto\AncillaryDto;
use Atlasflow\OrderBridge\Dto\CustomerDto;
use Atlasflow\OrderBridge\Dto\DeliverySlotDto;
use Atlasflow\OrderBridge\Dto\EnvelopeDto;
use Atlasflow\OrderBridge\Dto\FulfilmentDto;
use Atlasflow\OrderBridge\Dto\LineItemDto;
use Atlasflow\OrderBridge\Dto\NoteDto;
use Atlasflow\OrderBridge\Dto\OrderDto;
use Atlasflow\OrderBridge\Dto\PaymentDto;
use Atlasflow\OrderBridge\Dto\TotalsDto;
use Atlasflow\OrderBridge\Exceptions\ParseException;
use Atlasflow\OrderBridge\Validator\PayloadValidator;

/**
 * Parses a raw JSON string or decoded array received from Atlas Core into
 * typed DTO objects.
 *
 * Validates the payload before mapping. The host application maps the
 * returned DTOs into its own persistence layer; this class never writes
 * to any database.
 *
 * @throws ParseException On malformed JSON or schema validation failure.
 */
final class InboundParser
{
    public function __construct(
        private readonly PayloadValidator $validator = new PayloadValidator(),
    ) {
    }

    /**
     * Parse a raw JSON string or pre-decoded array into an EnvelopeDto.
     *
     * @param string|array<string, mixed> $payload
     * @throws ParseException
     */
    public function parse(string|array $payload): EnvelopeDto
    {
        if (is_string($payload)) {
            $decoded = json_decode($payload, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ParseException('Malformed JSON: ' . json_last_error_msg());
            }
            $payload = $decoded;
        }

        $result = $this->validator->validate($payload);
        if (!$result->isValid()) {
            $messages = array_map(
                static fn ($v) => "[{$v->field}] {$v->message}",
                $result->getViolations(),
            );
            throw new ParseException(
                'Payload failed schema validation: ' . implode('; ', $messages),
            );
        }

        return $this->mapEnvelope($payload);
    }

    // -------------------------------------------------------------------------
    // Envelope
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapEnvelope(array $data): EnvelopeDto
    {
        $source = $data['source'];
        $orders = array_map([$this, 'mapOrder'], $data['orders']);

        return new EnvelopeDto(
            schemaVersion: $data['schema_version'],
            generatedAt: $data['generated_at'],
            trigger: $data['trigger'],
            sourceType: $source['type'],
            sourceSiteId: $source['site_id'],
            sourceSiteName: $source['site_name'],
            sourceOperatorId: $source['operator_id'] ?? null,
            transferId: $data['transfer_id'] ?? null,
            orders: $orders,
        );
    }

    // -------------------------------------------------------------------------
    // Order
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapOrder(array $data): OrderDto
    {
        $items = array_map([$this, 'mapLineItem'], $data['items']);
        $ancillaries = isset($data['ancillaries']) && is_array($data['ancillaries'])
            ? array_map([$this, 'mapAncillary'], $data['ancillaries'])
            : null;
        $notes = isset($data['notes']) && is_array($data['notes'])
            ? array_map([$this, 'mapNote'], $data['notes'])
            : null;
        $payments = array_map([$this, 'mapPayment'], $data['payments']);

        return new OrderDto(
            originRef: $data['origin_ref'],
            status: $data['status'],
            channel: $data['channel'],
            operatorId: $data['operator_id'] ?? null,
            orderedAt: $data['ordered_at'],
            notes: $notes,
            customer: $this->mapCustomer($data['customer']),
            fulfilment: $this->mapFulfilment($data['fulfilment']),
            items: $items,
            ancillaries: $ancillaries,
            totals: $this->mapTotals($data['totals']),
            payments: $payments,
        );
    }

    // -------------------------------------------------------------------------
    // Line item
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapLineItem(array $data): LineItemDto
    {
        $note = isset($data['note']) && is_array($data['note'])
            ? $this->mapNote($data['note'])
            : null;

        return new LineItemDto(
            sku: $data['sku'],
            name: $data['name'],
            qty: $data['qty'],
            uom: $data['uom'],
            unitPrice: $data['unit_price'],
            discount: $data['discount'],
            vatRate: $data['vat_rate'],
            batch: $data['batch'] ?? null,
            passport: $data['passport'] ?? null,
            note: $note,
            lineExVat: $data['line_ex_vat'],
            lineVat: $data['line_vat'],
        );
    }

    // -------------------------------------------------------------------------
    // Ancillary
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapAncillary(array $data): AncillaryDto
    {
        return new AncillaryDto(
            category: $data['category'],
            qty: $data['qty'],
            unitPrice: $data['unit_price'],
            vatRate: $data['vat_rate'],
            description: $data['description'] ?? null,
            totalExVat: $data['total_ex_vat'],
            totalVat: $data['total_vat'],
        );
    }

    // -------------------------------------------------------------------------
    // Note
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapNote(array $data): NoteDto
    {
        return new NoteDto(
            type: $data['type'],
            note: $data['note'],
            createdBy: $data['created_by'],
            createdAt: $data['created_at'],
        );
    }

    // -------------------------------------------------------------------------
    // Totals
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapTotals(array $data): TotalsDto
    {
        return new TotalsDto(
            itemsNet: $data['items_net'],
            ancillariesNet: $data['ancillaries_net'],
            totalVat: $data['total_vat'],
            grandTotal: $data['grand_total'],
            refundOf: $data['refund_of'] ?? null,
        );
    }

    // -------------------------------------------------------------------------
    // Payment
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapPayment(array $data): PaymentDto
    {
        return new PaymentDto(
            method: $data['method'],
            amount: $data['amount'],
            reference: $data['reference'] ?? null,
            date: $data['date'],
        );
    }

    // -------------------------------------------------------------------------
    // Customer
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapCustomer(array $data): CustomerDto
    {
        $address = isset($data['address']) && is_array($data['address'])
            ? $this->mapAddress($data['address'])
            : null;

        return new CustomerDto(
            type: $data['type'],
            id: $data['id'] ?? null,
            name: $data['name'] ?? null,
            email: $data['email'] ?? null,
            phone: $data['phone'] ?? null,
            address: $address,
            contact: $data['contact'] ?? null,
        );
    }

    // -------------------------------------------------------------------------
    // Fulfilment
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapFulfilment(array $data): FulfilmentDto
    {
        $deliveryAddress = isset($data['delivery_address']) && is_array($data['delivery_address'])
            ? $this->mapAddress($data['delivery_address'])
            : null;

        $deliverySlot = isset($data['delivery_slot']) && is_array($data['delivery_slot'])
            ? $this->mapDeliverySlot($data['delivery_slot'])
            : null;

        $notes = isset($data['notes']) && is_array($data['notes'])
            ? array_map([$this, 'mapNote'], $data['notes'])
            : null;

        return new FulfilmentDto(
            type: $data['type'],
            deliveryAddress: $deliveryAddress,
            deliverySlot: $deliverySlot,
            notes: $notes,
        );
    }

    // -------------------------------------------------------------------------
    // Address
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapAddress(array $data): AddressDto
    {
        return new AddressDto(
            line1: $data['line1'],
            line2: $data['line2'] ?? null,
            line3: $data['line3'] ?? null,
            city: $data['city'],
            postcode: $data['postcode'],
            region: $data['region'] ?? null,
            country: $data['country'],
        );
    }

    // -------------------------------------------------------------------------
    // Delivery slot
    // -------------------------------------------------------------------------

    /** @param array<string, mixed> $data */
    private function mapDeliverySlot(array $data): DeliverySlotDto
    {
        return new DeliverySlotDto(
            deliveryOn: $data['delivery_on'],
            bookingId: $data['booking_id'] ?? null,
            notes: $data['notes'] ?? null,
        );
    }
}
