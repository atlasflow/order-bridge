<?php

declare(strict_types=1);

namespace Atlasflow\OrderBridge\Transport;

use Atlasflow\OrderBridge\Exceptions\TransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

/**
 * PSR-18 HTTP transport for the Atlas Core Order Bridge API.
 *
 * Accepts any PSR-18 compatible HTTP client via constructor injection.
 * Sends the payload once and returns the result — no retry logic.
 *
 * Example with Guzzle:
 *   new HttpTransport(new GuzzleHttp\Client(), $requestFactory, $streamFactory, $endpointUrl);
 *
 * Example with nyholm/psr7 + symfony/http-client:
 *   new HttpTransport($client, new Psr17Factory(), new Psr17Factory(), $endpointUrl);
 */
final class HttpTransport implements TransportInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly string $endpointUrl,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @throws TransportException When the HTTP request fails at the client level.
     */
    public function send(array $payload): TransportResult
    {
        $transferId = (string) ($payload['transfer_id'] ?? '');
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        $request = $this->requestFactory
            ->createRequest('POST', $this->endpointUrl)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json')
            ->withBody($this->streamFactory->createStream($body));

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $e) {
            throw new TransportException(
                "HTTP transport failed: {$e->getMessage()}",
                previous: $e,
            );
        }

        $statusCode = $response->getStatusCode();
        $responseBody = (string) $response->getBody();
        $success = $statusCode >= 200 && $statusCode < 300;

        return new TransportResult(
            success: $success,
            statusCode: $statusCode,
            responseBody: $responseBody,
            transferId: $transferId,
        );
    }
}
