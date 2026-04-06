<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable line item parsed from an inbound or outbound payload. */
final readonly class LineItemDto
{
    public function __construct(
        public string $sku,
        public string $name,
        public int|float $qty,
        public string $uom,
        /** 4 decimal places. */
        public string $unitPrice,
        /** 6 decimal places. */
        public string $discount,
        /** 2 decimal places. */
        public string $vatRate,
        public ?string $batch,
        public ?string $passport,
        public ?NoteDto $note,
        /** Derived: qty × unit_price × (100 − discount) / 100. 4 decimal places. */
        public string $lineExVat,
        /** Derived: line_ex_vat × vat_rate / 100. 4 decimal places. */
        public string $lineVat,
    ) {
    }
}
