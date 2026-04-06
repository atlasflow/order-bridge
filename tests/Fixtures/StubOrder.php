<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Tests\Fixtures;

use Atlasflow\OrderBridge\Contracts\AncillaryInterface;
use Atlasflow\OrderBridge\Contracts\CustomerInterface;
use Atlasflow\OrderBridge\Contracts\FulfilmentInterface;
use Atlasflow\OrderBridge\Contracts\LineItemInterface;
use Atlasflow\OrderBridge\Contracts\OrderInterface;
use Atlasflow\OrderBridge\Contracts\PaymentInterface;
use Atlasflow\OrderBridge\Dto\NoteDto;

/**
 * Minimal OrderInterface stub for tests.
 * Override properties to customise behaviour.
 */
final class StubOrder implements OrderInterface
{
    /**
     * @param LineItemInterface[]       $items
     * @param AncillaryInterface[]|null $ancillaries
     * @param PaymentInterface[]        $payments
     * @param NoteDto[]|null            $notes
     */
    public function __construct(
        public string $originRef = 'CASSA-2026-00412',
        public string $status = 'pos',
        public string $channel = 'in-store',
        public ?string $operatorId = 'usr_042',
        public string $orderedAt = '2026-03-21T10:44:11Z',
        public ?array $notes = null,
        public CustomerInterface $customer = new StubCustomer(),
        public FulfilmentInterface $fulfilment = new StubFulfilment(),
        public array $items = [],
        public ?array $ancillaries = null,
        public array $payments = [],
        public ?string $refundOf = null,
    ) {
        if ($this->items === []) {
            $this->items = [new StubLineItem()];
        }
        if ($this->payments === []) {
            $this->payments = [new StubPayment()];
        }
    }

    public function getOriginRef(): string
    {
        return $this->originRef;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getOperatorId(): ?string
    {
        return $this->operatorId;
    }

    public function getOrderedAt(): string
    {
        return $this->orderedAt;
    }

    /** @return NoteDto[]|null */
    public function getNotes(): ?array
    {
        return $this->notes;
    }

    public function getCustomer(): CustomerInterface
    {
        return $this->customer;
    }

    public function getFulfilment(): FulfilmentInterface
    {
        return $this->fulfilment;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function getAncillaries(): ?array
    {
        return $this->ancillaries;
    }

    public function getPayments(): array
    {
        return $this->payments;
    }

    public function getRefundOf(): ?string
    {
        return $this->refundOf;
    }
}
