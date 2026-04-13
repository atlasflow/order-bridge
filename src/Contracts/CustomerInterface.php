<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Contracts;

/**
 * Identifies or describes the customer associated with an order.
 *
 * The type field controls which additional fields are relevant:
 * - "anonymous": no further fields required.
 * - "registered": only getId() is required; Core resolves the full record.
 * - "new": full name, contact, and address fields are expected.
 *
 * The address is returned as a plain array with the keys defined in §3.4.2:
 * line1, line2, line3, city, postcode, region, country.
 */
interface CustomerInterface
{
    /** Customer type. Permitted values: "anonymous", "registered", "new". */
    public function getType(): string;

    /** Core customer ID. Required when type is "registered". Null otherwise. */
    public function getId(): ?string;

    /**
     * Full name of the customer. Required when type is "new".
     * May differ from getContact() when the customer is a company.
     */
    public function getName(): ?string;

    /** Email address. Required when type is "new" if available. */
    public function getEmail(): ?string;

    /** Phone number in E.164 format (e.g. "+447712345678"). Optional. */
    public function getPhone(): ?string;

    /**
     * Customer address. Required for type "new" when fulfilment is "delivery".
     *
     * Expected keys: line1 (required), line2, line3, city (required),
     * postcode (required), region, country (required, ISO 3166-1 alpha-2).
     *
     * @return array{line1: string, line2: ?string, line3: ?string, city: string, postcode: string, region: ?string, country: string}|null
     */
    public function getAddress(): ?array;

    /** Designated contact name for the order. Required when type is "new" if available. */
    public function getContact(): ?string;

    public function getCustomerAccount(): string;
}
