<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\HttpClient;

/**
 * Payment Sources management.
 *
 * Uses the Legacy API (Basic Auth).
 *
 * @see https://developer.viva.com/getting-started/create-a-payment-source/
 */
final class Sources
{
    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * List all payment sources.
     *
     * @return array<int, array<string, mixed>>
     */
    public function list(): array
    {
        return $this->http->legacyGet('/api/sources');
    }

    /**
     * Create a payment source.
     *
     * @param  string  $name  Display name
     * @param  string  $sourceCode  4-digit code
     * @param  string|null  $domain  Website domain
     * @param  string|null  $pathSuccess  Success redirect path
     * @param  string|null  $pathFail  Failure redirect path
     * @return array<string, mixed>
     */
    public function create(
        string $name,
        string $sourceCode,
        ?string $domain = null,
        ?string $pathSuccess = null,
        ?string $pathFail = null,
    ): array {
        return $this->http->legacyPost('/api/sources', array_filter([
            'Name' => $name,
            'SourceCode' => $sourceCode,
            'Domain' => $domain,
            'PathSuccess' => $pathSuccess,
            'PathFail' => $pathFail,
        ]));
    }
}
