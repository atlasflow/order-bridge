<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

use Atlasflow\OrderBridge\Contracts\CustomerInterface;

/** Default values reproduce the §4 example customer (registered). */
final class StubCustomer implements CustomerInterface
{
    /**
     * @param array{line1: string, line2: string|null, line3: string|null, city: string, postcode: string, region: string|null, country: string}|null $address
     */
    public function __construct(
        public string $type = 'registered',
        public ?string $id = 'cust_1089',
        public ?string $name = null,
        public ?string $email = null,
        public ?string $phone = null,
        public ?array $address = null,
        public ?string $contact = null,
    ) {
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    /**
     * @return array{line1: string, line2: string|null, line3: string|null, city: string, postcode: string, region: string|null, country: string}|null
     */
    public function getAddress(): ?array
    {
        return $this->address;
    }

    public function getContact(): ?string
    {
        return $this->contact;
    }
}
