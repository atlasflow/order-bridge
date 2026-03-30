<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable delivery slot booking reference. */
final readonly class DeliverySlotDto
{
    public function __construct(
        /** ISO 8601 delivery date. */
        public string $deliveryOn,
        /** Pre-booked slot UUID. Null if Core will assign automatically. */
        public ?string $bookingId,
        public ?string $notes,
    ) {
    }
}
