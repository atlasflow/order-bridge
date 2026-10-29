<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/**
 * Order-level discount voucher that was applied to the sale.
 *
 * The money already travels on each eligible line's `discount`; this records
 * only which voucher produced it.
 */
final readonly class DiscountVoucherDto
{
    public function __construct(
        /** Voucher code as entered at the till. Non-empty, at most 64 characters. */
        public string $code,
        /** Percentage applied to eligible lines, > 0 and <= 100, at SchemaVersion::DISCOUNT_SCALE places (e.g. "10.000000"). */
        public string $percentage,
    ) {
    }
}
