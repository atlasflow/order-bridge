<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Contracts;

/**
 * Represents a single payment instrument used in a transaction.
 *
 * Split payments are represented as multiple PaymentInterface entries.
 * The sum of all amounts must equal the order's grand_total.
 */
interface PaymentInterface
{
    /**
     * Payment method used. Permitted values: "cash", "card", "account",
     * "online", "gift_card".
     */
    public function getMethod(): string;

    /** Amount tendered via this method — 4 decimal places. */
    public function getAmount(): string;

    /**
     * Payment gateway or terminal reference (e.g. Stripe charge ID).
     * Null for cash payments.
     */
    public function getReference(): ?string;

    /** Payment date in ISO 8601 format (YYYY-MM-DDThh:mm:ssZ). */
    public function getDate(): string;
}
