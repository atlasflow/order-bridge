<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

use Atlasflow\OrderBridge\Contracts\FulfilmentInterface;
use Atlasflow\OrderBridge\Dto\NoteDto;

/** Default values reproduce the §4 example fulfilment (collection). */
final class StubFulfilment implements FulfilmentInterface
{
    /**
     * @param array{line1: string, line2: string|null, line3: string|null, city: string, postcode: string, region: string|null, country: string}|null $deliveryAddress
     * @param array{delivery_on: string, booking_id: string|null, notes: string|null}|null $deliverySlot
     * @param NoteDto[]|null $notes
     */
    public function __construct(
        public string $type = 'collection',
        public ?array $deliveryAddress = null,
        public ?array $deliverySlot = null,
        public ?array $notes = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array{line1: string, line2: string|null, line3: string|null, city: string, postcode: string, region: string|null, country: string}|null
     */
    public function getDeliveryAddress(): ?array
    {
        return $this->deliveryAddress;
    }

    /**
     * @return array{delivery_on: string, booking_id: string|null, notes: string|null}|null
     */
    public function getDeliverySlot(): ?array
    {
        return $this->deliverySlot;
    }

    /** @return NoteDto[]|null */
    public function getNotes(): ?array
    {
        return $this->notes;
    }
}
