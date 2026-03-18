<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\HttpClient;

/**
 * Wallet operations — balance, transfer between accounts.
 *
 * Retrieve Wallets uses the New API (Bearer token).
 * Balance Transfer uses the Legacy API (Basic Auth).
 *
 * Prerequisite for transfers: "Allow transfers between accounts" must be
 * enabled in Settings > API Access on the source wallet.
 *
 * @see https://developer.viva.com/apis-for-payments/wallet-api/
 */
final class Wallets
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {}

    /**
     * Retrieve all wallets for the merchant.
     *
     * @return array<int, array<string, mixed>>  List of wallets with balance info
     */
    public function list(): array
    {
        $result = $this->http->get('/api/wallets');

        return $result['Wallets'] ?? $result['wallets'] ?? [$result];
    }

    /**
     * Get aggregated balance across all wallets.
     *
     * @return array{available: float, pending: float, reserved: float, currency: string}
     */
    public function balance(): array
    {
        $wallets = $this->list();

        $available = 0.0;
        $pending = 0.0;
        $reserved = 0.0;
        $currency = 'EUR';

        foreach ($wallets as $wallet) {
            $available += (float) ($wallet['Available'] ?? $wallet['available'] ?? 0);
            $pending += (float) ($wallet['Pending'] ?? $wallet['pending'] ?? 0);
            $reserved += (float) ($wallet['Reserved'] ?? $wallet['reserved'] ?? 0);
            $currency = $wallet['CurrencyCode'] ?? $wallet['currency'] ?? $currency;
        }

        return compact('available', 'pending', 'reserved', 'currency');
    }

    /**
     * Transfer money between Viva wallets.
     *
     * Prerequisite: "Allow transfers between accounts" must be enabled
     * on the source wallet in Settings > API Access.
     *
     * @param  int  $amount  Amount in cents
     * @param  string  $sourceWalletId  Source wallet UUID
     * @param  string  $targetWalletId  Target wallet UUID
     * @param  string|null  $description  Transfer description
     * @return array<string, mixed>
     */
    public function transfer(int $amount, string $sourceWalletId, string $targetWalletId, ?string $description = null): array
    {
        return $this->http->legacyPost('/api/wallets/transfer', array_filter([
            'Amount' => $amount,
            'SourceWalletId' => $sourceWalletId,
            'TargetWalletId' => $targetWalletId,
            'Description' => $description,
        ]));
    }
}
