<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Contracts;

/**
 * Represents an ancillary fee item attached to an order (e.g. delivery charge,
 * packaging, phytosanitary certificate).
 *
 * The host application provides raw inputs only. The Serialiser derives
 * total_ex_vat and total_vat using the formulas in §3.8.
 */
interface AncillaryInterface
{
    /**
     * Category slug as defined in Atlas Core (e.g. "delivery_fee",
     * "phytosanitary_certificate").
     */
    public function getCategory(): string;

    /** Quantity of the ancillary item. Must be a positive number. */
    public function getQty(): int|float;

    /** Unit selling price excluding VAT — 4 decimal places. Always positive. */
    public function getUnitPrice(): string;

    /** VAT rate as a percentage — 2 decimal places (e.g. "20.00", "0.00"). */
    public function getVatRate(): string;

    /** Optional human-readable description of the ancillary item. */
    public function getDescription(): ?string;
}
