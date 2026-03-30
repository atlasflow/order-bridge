<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable customer record parsed from an inbound or outbound payload. */
final readonly class CustomerDto
{
    public function __construct(
        public string $type,
        public ?string $id,
        public ?string $name,
        public ?string $email,
        public ?string $phone,
        public ?AddressDto $address,
        public ?string $contact,
    ) {
    }
}
