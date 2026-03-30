<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable payment record parsed from an inbound or outbound payload. */
final readonly class PaymentDto
{
    public function __construct(
        public string $method,
        /** 4 decimal places. */
        public string $amount,
        public ?string $reference,
        public string $date,
    ) {
    }
}
