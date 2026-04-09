<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Contracts;

use Atlasflow\OrderBridge\Dto\NoteDto;
use Atlasflow\OrderBridge\Dto\TotalsDto;

/**
 * Represents a single order as provided by the host application.
 *
 * The host application wraps its own order model in a class implementing
 * this interface. The package never touches the underlying model directly.
 *
 * Computed fields (line totals, totals.*) are derived by the Serialiser;
 * the host application must NOT provide them through this interface.
 */
interface OrderInterface
{
    /** The order reference as known by the source system (e.g. "CASSA-2026-00412"). */
    public function getOriginRef(): string;

    /** Order status. Permitted values: "pos", "order", "refund". */
    public function getStatus(): string;

    /** Sales channel (e.g. "in-store", "website", "phone"). */
    public function getChannel(): string;

    /** ID of the staff member who processed the order. Null for automated channels. */
    public function getOperatorId(): ?string;

    /** UTC timestamp of order creation in ISO 8601 format (YYYY-MM-DDThh:mm:ssZ). */
    public function getOrderedAt(): string;

    /**
     * Optional order-level notes. Null when no notes are present.
     *
     * @return NoteDto[]|null
     */
    public function getNotes(): ?array;

    /** Customer information. */
    public function getCustomer(): CustomerInterface;

    /** Fulfilment information. */
    public function getFulfilment(): FulfilmentInterface;

    /**
     * Line items. Must contain at least one item.
     *
     * @return LineItemInterface[]
     */
    public function getItems(): array;

    /**
     * Ancillary fee items (delivery charges, packaging, etc.), or null if none.
     *
     * @return AncillaryInterface[]|null
     */
    public function getAncillaries(): ?array;

    /**
     * Payment records. Must contain at least one entry.
     *
     * @return PaymentInterface[]
     */
    public function getPayments(): array;

    /**
     * Core-assigned signature of the original order being refunded.
     * Required when status is "refund". Must be null for completed orders.
     */
    public function getRefundOf(): ?string;

    public function getSignature(): string;
}
