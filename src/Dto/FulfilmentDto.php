<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/**
 * Immutable fulfilment record parsed from an inbound or outbound payload.
 *
 * @param NoteDto[]|null $notes
 */
final readonly class FulfilmentDto
{
    public function __construct(
        public string $type,
        public ?AddressDto $deliveryAddress,
        public ?DeliverySlotDto $deliverySlot,
        public ?array $notes,
    ) {
    }
}
