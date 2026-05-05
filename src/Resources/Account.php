<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\HttpClient;

/**
 * Account information.
 *
 * Both endpoints exposed here live on the **Legacy API**
 * (www.vivapayments.com / demo.vivapayments.com) with Basic Auth:
 * - `/api/accounts/{merchantId}` — account info
 * - `/api/wallets`               — wallets list / balance
 */
final class Account
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {}

    /**
     * Get merchant account information via the Legacy API.
     *
     * @return array<string, mixed>  Account data (merchantId, businessName, email, etc.)
     */
    public function info(): array
    {
        return $this->http->legacyGet('/api/accounts/'.$this->config->merchantId);
    }

    /**
     * Get wallets/balance for the merchant via the Legacy API.
     *
     * @return array<string, mixed>
     */
    public function wallets(): array
    {
        return $this->http->legacyGet('/api/wallets');
    }
}
