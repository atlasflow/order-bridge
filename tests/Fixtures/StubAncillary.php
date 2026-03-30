<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

use Atlasflow\OrderBridge\Contracts\AncillaryInterface;

/** Default values reproduce the §4 example ancillary item. */
final class StubAncillary implements AncillaryInterface
{
    public function __construct(
        public string $category = 'delivery_fee',
        public int|float $qty = 1,
        public string $unitPrice = '34.9900',
        public string $vatRate = '21.00',
        public ?string $description = null,
    ) {
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getQty(): int|float
    {
        return $this->qty;
    }

    public function getUnitPrice(): string
    {
        return $this->unitPrice;
    }

    public function getVatRate(): string
    {
        return $this->vatRate;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }
}
