<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\HttpClient;

/**
 * Wallet operations — balance, transfer between accounts.
 *
 * The base list/balance endpoint `/api/wallets` lives on the **Legacy API**
 * (www.vivapayments.com / demo.vivapayments.com) with Basic Auth.
 * Detailed wallet management (`/walletaccounts/v1/*`) lives on the New API
 * with Bearer OAuth2.
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
     * Retrieve all wallets for the merchant via the Legacy API.
     *
     * @return array<int, array<string, mixed>>  List of wallets with balance info
     */
    public function list(): array
    {
        $result = $this->http->legacyGet('/api/wallets');

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
     * Transfer money between Viva wallets (Legacy API).
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

    // ==================== Account API (New) ====================

    /**
     * List wallets via the Account API (richer response with IBAN, swift, etc.).
     *
     * @return array<int, array{iban: string, walletId: int, amount: float, isPrimary: bool, currencyCode: string, friendlyName: ?string}>
     */
    public function listDetailed(): array
    {
        return $this->http->get('/walletaccounts/v1/wallets');
    }

    /**
     * Create a new wallet (sub-account).
     *
     * @param  string  $friendlyName  Display name for the wallet
     * @param  string  $currencyCode  ISO 4217 alpha code (e.g. 'EUR')
     * @return array<string, mixed>
     */
    public function create(string $friendlyName, string $currencyCode = 'EUR'): array
    {
        return $this->http->post('/walletaccounts/v1/wallets', [
            'friendlyName' => $friendlyName,
            'currencyCode' => $currencyCode,
        ]);
    }

    /**
     * Update a wallet's friendly name.
     *
     * @return array<string, mixed>
     */
    public function update(int $walletId, string $friendlyName): array
    {
        return $this->http->post("/walletaccounts/v1/wallets/{$walletId}", [
            'friendlyName' => $friendlyName,
        ]);
    }

    /**
     * Search account transactions (across all wallets).
     *
     * @param  array{date_from?: string, date_to?: string, walletId?: int}  $filters
     * @return array<int, array<string, mixed>>
     */
    public function searchTransactions(array $filters = []): array
    {
        return $this->http->get('/walletaccounts/v1/transactions', array_filter($filters));
    }

    /**
     * Get account transaction details.
     *
     * @return array<string, mixed>
     */
    public function getTransaction(string $transactionId): array
    {
        return $this->http->get("/walletaccounts/v1/transactions/{$transactionId}");
    }
}
