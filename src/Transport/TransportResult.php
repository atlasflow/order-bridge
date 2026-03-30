<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Transport;

/** Immutable result of a single transport send operation. */
final readonly class TransportResult
{
    public function __construct(
        private bool $success,
        private int $statusCode,
        private string $responseBody,
        /** The transfer_id from the payload, echoed back for the host app to store. */
        private string $transferId,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }

    /**
     * The transfer_id from the payload that was sent.
     * The host application should persist this value to support idempotent retries.
     */
    public function getTransferId(): string
    {
        return $this->transferId;
    }
}
