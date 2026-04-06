<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Contracts;

use Atlasflow\OrderBridge\Dto\NoteDto;

/**
 * Describes how and where an order will be delivered or collected.
 *
 * Address and delivery slot are returned as plain arrays to avoid coupling
 * the host application to package-internal DTO classes.
 */
interface FulfilmentInterface
{
    /** Fulfilment method. Permitted values: "collection", "delivery". */
    public function getType(): string;

    /**
     * Full delivery address. Required when type is "delivery". Null otherwise.
     *
     * Expected keys: line1 (required), line2, line3, city (required),
     * postcode (required), region, country (required, ISO 3166-1 alpha-2).
     *
     * @return array{line1: string, line2: ?string, line3: ?string, city: string, postcode: string, region: ?string, country: string}|null
     */
    public function getDeliveryAddress(): ?array;

    /**
     * Delivery slot booking. Optional; used only when type is "delivery".
     *
     * Expected keys: delivery_on (required, ISO 8601), booking_id (conditional UUID),
     * notes (optional string).
     *
     * @return array{delivery_on: string, booking_id: ?string, notes: ?string}|null
     */
    public function getDeliverySlot(): ?array;

    /**
     * Optional fulfilment-level notes. Null when no notes are present.
     *
     * @return NoteDto[]|null
     */
    public function getNotes(): ?array;
}
