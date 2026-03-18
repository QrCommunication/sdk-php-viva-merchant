<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant;

use QrCommunication\VivaMerchant\Enums\Environment;

final class Config
{
    public readonly Environment $environment;

    /**
     * @param  string  $merchantId  Merchant UUID (for Basic Auth on Legacy API)
     * @param  string  $apiKey  API Key (for Basic Auth on Legacy API)
     * @param  string  $clientId  OAuth Client ID (for Bearer token on New API)
     * @param  string  $clientSecret  OAuth Client Secret
     * @param  string|Environment  $environment  'demo' or 'production'
     */
    public function __construct(
        public readonly string $merchantId,
        public readonly string $apiKey,
        public readonly string $clientId,
        public readonly string $clientSecret,
        string|Environment $environment = Environment::DEMO,
    ) {
        $this->environment = $environment instanceof Environment
            ? $environment
            : Environment::from($environment);
    }

    public function accountsUrl(): string
    {
        return $this->environment->accountsUrl();
    }

    public function apiUrl(): string
    {
        return $this->environment->apiUrl();
    }

    public function legacyUrl(): string
    {
        return $this->environment->legacyUrl();
    }

    public function checkoutUrl(): string
    {
        return $this->environment->checkoutUrl();
    }

    public function isProduction(): bool
    {
        return $this->environment === Environment::PRODUCTION;
    }
}
