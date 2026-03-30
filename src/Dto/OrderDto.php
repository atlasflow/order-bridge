<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable order record parsed from an inbound or outbound payload. */
final readonly class OrderDto
{
    /**
     * @param LineItemDto[]       $items
     * @param AncillaryDto[]|null $ancillaries
     * @param PaymentDto[]        $payments
     */
    public function __construct(
        public string $originRef,
        public string $status,
        public string $channel,
        public ?string $operatorId,
        public string $orderedAt,
        public ?string $notes,
        public CustomerDto $customer,
        public FulfilmentDto $fulfilment,
        public array $items,
        public ?array $ancillaries,
        public TotalsDto $totals,
        public array $payments,
    ) {
    }
}
