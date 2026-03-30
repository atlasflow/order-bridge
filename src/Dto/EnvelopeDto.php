<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Dto;

/** Immutable transfer envelope parsed from an inbound or outbound payload. */
final readonly class EnvelopeDto
{
    /**
     * @param OrderDto[] $orders
     */
    public function __construct(
        public string $schemaVersion,
        public string $generatedAt,
        public string $trigger,
        public string $sourceType,
        public string $sourceSiteId,
        public string $sourceSiteName,
        public ?string $sourceOperatorId,
        /** Null on Core-exported payloads; required on inbound transfers. */
        public ?string $transferId,
        public array $orders,
    ) {
    }
}
