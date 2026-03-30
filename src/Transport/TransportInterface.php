<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Transport;

/**
 * Sends a serialised transfer payload to Atlas Core.
 *
 * Implementations must send the payload once and return the result.
 * Retry logic, backoff scheduling, and failure persistence are the
 * host application's responsibility.
 */
interface TransportInterface
{
    /**
     * Send a serialised payload array to Atlas Core.
     *
     * @param array<string, mixed> $payload The array produced by OrderSerialiser::serialise().
     */
    public function send(array $payload): TransportResult;
}
