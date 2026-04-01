<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

use Atlasflow\OrderBridge\Contracts\PaymentInterface;

/** Default values reproduce the §4 example payment. */
final class StubPayment implements PaymentInterface
{
    public function __construct(
        public string $method = 'card',
        public string $amount = '119.0380',
        public ?string $reference = 'TXN-STRIPE-89234',
        public string $date = '2026-03-21T10:44:11Z',
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getDate(): string
    {
        return $this->date;
    }
}
