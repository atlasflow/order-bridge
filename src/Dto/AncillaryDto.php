<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable ancillary fee item parsed from an inbound or outbound payload. */
final readonly class AncillaryDto
{
    public function __construct(
        public string $category,
        public int|float $qty,
        /** 4 decimal places. */
        public string $unitPrice,
        /** 2 decimal places. */
        public string $vatRate,
        public ?string $description,
        /** Derived: qty × unit_price. 4 decimal places. */
        public string $totalExVat,
        /** Derived: total_ex_vat × vat_rate / 100. 4 decimal places. */
        public string $totalVat,
    ) {
    }
}
