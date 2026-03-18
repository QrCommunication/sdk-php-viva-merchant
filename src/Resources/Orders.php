<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\Enums\Currency;
use QrCommunication\VivaMerchant\HttpClient;

/**
 * Payment Orders — Smart Checkout.
 *
 * Creates payment orders and generates checkout URLs.
 * Uses the Legacy API (POST /api/orders) with Basic Auth.
 *
 * @see https://developer.viva.com/smart-checkout/
 */
final class Orders
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {}

    /**
     * Create a payment order.
     *
     * @param  int  $amount  Amount in cents (e.g. 1500 = €15.00)
     * @param  string|null  $customerDescription  Description shown to customer in checkout
     * @param  string|null  $merchantReference  Internal reference (appears in exports)
     * @param  string|null  $sourceCode  Payment source (null = default)
     * @param  bool  $allowRecurring  Allow card tokenization for future charges
     * @param  bool  $preauth  Pre-authorize only (capture later)
     * @param  int  $maxInstallments  Max installments (0 = disabled)
     * @return array{order_code: int, checkout_url: string}
     */
    public function create(
        int $amount,
        ?string $customerDescription = null,
        ?string $merchantReference = null,
        ?string $sourceCode = null,
        bool $allowRecurring = false,
        bool $preauth = false,
        int $maxInstallments = 0,
    ): array {
        $payload = array_filter([
            'Amount' => $amount,
            'CustomerTrns' => $customerDescription,
            'MerchantTrns' => $merchantReference,
            'SourceCode' => $sourceCode,
            'AllowRecurring' => $allowRecurring,
            'IsPreAuth' => $preauth,
            'MaxInstallments' => $maxInstallments > 0 ? $maxInstallments : null,
        ], fn ($v) => $v !== null);

        $result = $this->http->legacyPost('/api/orders', $payload);

        if (! ($result['Success'] ?? false)) {
            throw new \QrCommunication\VivaMerchant\Exceptions\ApiException(
                $result['ErrorText'] ?? 'Order creation failed',
                400,
                $result,
            );
        }

        $orderCode = $result['OrderCode'];

        return [
            'order_code' => $orderCode,
            'checkout_url' => $this->config->checkoutUrl().'?ref='.$orderCode,
        ];
    }

    /**
     * Get order status.
     *
     * @return array<string, mixed>  Raw Viva order data
     */
    public function get(int $orderCode): array
    {
        return $this->http->legacyGet("/api/orders/{$orderCode}");
    }

    /**
     * Cancel an unpaid order.
     *
     * @return array<string, mixed>
     */
    public function cancel(int $orderCode): array
    {
        return $this->http->legacyDeleteUrl($this->config->legacyUrl()."/api/orders/{$orderCode}");
    }

    /**
     * Get the Smart Checkout URL for an existing order code.
     */
    public function checkoutUrl(int $orderCode): string
    {
        return $this->config->checkoutUrl().'?ref='.$orderCode;
    }
}
