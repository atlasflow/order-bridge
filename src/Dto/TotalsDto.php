<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable order-level monetary summary. */
final readonly class TotalsDto
{
    public function __construct(
        /** Sum of all line_ex_vat values. 4 decimal places. */
        public string $itemsNet,
        /** Sum of all ancillary total_ex_vat values. 4 decimal places. */
        public string $ancillariesNet,
        /** Sum of all line_vat + ancillary total_vat values. 4 decimal places. */
        public string $totalVat,
        /** items_net + ancillaries_net + total_vat. 4 decimal places. */
        public string $grandTotal,
        /** Core-assigned UUID of the original order being refunded. Null for completed orders. */
        public ?string $refundOf,
    ) {
    }
}
