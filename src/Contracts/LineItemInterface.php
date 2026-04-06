<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Contracts;

use Atlasflow\OrderBridge\Dto\NoteDto;

/**
 * Represents a single product line within an order.
 *
 * The host application provides the raw input values only.
 * The Serialiser derives line_ex_vat and line_vat using the formulas in §3.5.
 */
interface LineItemInterface
{
    /** SKU / variant code as held in the source system or Atlas Core. */
    public function getSku(): string;

    /** Product name at the time of sale (stored verbatim as a snapshot). */
    public function getName(): string;

    /**
     * Quantity sold. Positive integer for standard items; fractional for
     * items sold by weight or volume.
     */
    public function getQty(): int|float;

    /** Unit of measure (e.g. "item", "kg", "litre", "bundle"). */
    public function getUom(): string;

    /** Unit selling price excluding VAT — 4 decimal places (e.g. "42.6100"). Always positive. */
    public function getUnitPrice(): string;

    /**
     * Percentage discount applied to this line — 6 decimal places
     * (e.g. "10.000000"). Use "0.000000" when no discount applies.
     */
    public function getDiscount(): string;

    /** VAT rate as a percentage — 2 decimal places (e.g. "20.00", "0.00"). */
    public function getVatRate(): string;

    /** Stock batch or lot reference for traceability. Null if not applicable. */
    public function getBatch(): ?string;

    /** Plant passport identifier. Null for non-regulated items. */
    public function getPassport(): ?string;

    /** Optional structured note for this line. Null if not applicable. */
    public function getNotes(): ?NoteDto;
}
