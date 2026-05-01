<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Contracts;

/**
 * Contract for the low-level HTTP client.
 *
 * Extracted to allow mocking in unit tests (HttpClient is final).
 * Consumers should type-hint against this interface.
 *
 * @internal Implement via HttpClient only. Do not create custom implementations in production.
 */
interface HttpClientInterface
{
    /**
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array;

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function post(string $path, array $body = []): array;

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function put(string $path, array $body = []): array;

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function delete(string $path, array $body = []): array;

    /**
     * @param  array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function legacyGet(string $path, array $query = []): array;

    /**
     * @param  array<string, mixed> $body
     * @return array<string, mixed>
     */
    public function legacyPost(string $path, array $body = []): array;

    /** @return array<string, mixed> */
    public function legacyDelete(string $url): array;

    /** @return array<string, mixed> */
    public function legacyDeleteUrl(string $fullUrl): array;

    /** @return array<string, mixed> */
    public function legacyDeletePath(string $path): array;

    public function invalidateToken(): void;
}
