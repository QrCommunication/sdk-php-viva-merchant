<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\HttpClient;

/**
 * Account information.
 *
 * Uses the New API (Bearer token) for account details.
 */
final class Account
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {}

    /**
     * Get merchant account information.
     *
     * @return array<string, mixed>  Account data (merchantId, businessName, email, etc.)
     */
    public function info(): array
    {
        return $this->http->get('/api/accounts/'.$this->config->merchantId);
    }

    /**
     * Get wallets/balance for the merchant.
     *
     * @return array<string, mixed>
     */
    public function wallets(): array
    {
        return $this->http->get('/api/wallets');
    }
}
