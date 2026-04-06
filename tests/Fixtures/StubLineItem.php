<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

use Atlasflow\OrderBridge\Contracts\LineItemInterface;
use Atlasflow\OrderBridge\Dto\NoteDto;

/** Default values reproduce the §4 example line item. */
final class StubLineItem implements LineItemInterface
{
    public function __construct(
        public string $sku = 'TRF-45L',
        public string $name = 'Trachycarpus fortunei 45L',
        public int|float $qty = 2,
        public string $uom = 'item',
        public string $unitPrice = '42.6100',
        public string $discount = '10.000000',
        public string $vatRate = '0.00',
        public ?string $batch = 'BATCH-2024-TF-009',
        public ?string $passport = 'GB-12345-A',
        public ?NoteDto $notes = null,
    ) {
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getQty(): int|float
    {
        return $this->qty;
    }

    public function getUom(): string
    {
        return $this->uom;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getDiscount(): string
    {
        return $this->discount;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function getBatch(): ?string
    {
        return $this->batch;
    }

    public function getPassport(): ?string
    {
        return $this->passport;
    }

    public function getNotes(): ?NoteDto
    {
        return $this->notes;
    }
}
