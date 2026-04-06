<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable address value object used in fulfilment and customer records. */
final readonly class AddressDto
{
    public function __construct(
        public string $line1,
        public ?string $line2,
        public ?string $line3,
        public string $city,
        public string $postcode,
        public ?string $region,
        public string $country,
        public ?int $addressId,
    ) {
    }
}
