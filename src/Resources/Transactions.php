<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\HttpClient;

/**
 * Transaction operations — get, list, refund, capture, recurring.
 *
 * Uses the Legacy API (Basic Auth) for all operations.
 * The New API (/checkout/v2/transactions) only supports GET.
 *
 * @see https://developer.viva.com/apis-for-payments/
 */
final class Transactions
{
    public function __construct(
        private readonly HttpClient $http,
        private readonly Config $config,
    ) {}

    /**
     * Get transaction details.
     *
     * @return array<string, mixed>  Raw Viva transaction data
     */
    public function get(string $transactionId): array
    {
        return $this->http->legacyGet("/api/transactions/{$transactionId}");
    }

    /**
     * List transactions for a date.
     *
     * @param  string  $date  Y-m-d format
     * @return array<int, array<string, mixed>>
     */
    public function listByDate(string $date): array
    {
        $result = $this->http->legacyGet('/api/transactions', ['date' => $date]);

        return $result['Transactions'] ?? [];
    }

    /**
     * Cancel or refund a transaction.
     *
     * Same-day = void (cancel). Past day = refund.
     *
     * @param  string  $transactionId  Transaction UUID
     * @param  int|null  $amount  Amount in cents (null = full refund)
     * @param  string|null  $sourceCode  Payment source code
     * @return array{TransactionId: string}
     */
    public function cancel(string $transactionId, ?int $amount = null, ?string $sourceCode = null): array
    {
        $params = array_filter([
            'amount' => $amount,
            'sourceCode' => $sourceCode,
        ]);

        $url = $this->config->legacyUrl()."/api/transactions/{$transactionId}";
        if (! empty($params)) {
            $url .= '?'.http_build_query($params);
        }

        return $this->http->legacyDeleteUrl($url);
    }

    /**
     * Capture a pre-authorized transaction.
     *
     * @param  string  $transactionId  The preauth transaction UUID
     * @param  int  $amount  Amount in cents to capture
     * @return array<string, mixed>
     */
    public function capture(string $transactionId, int $amount): array
    {
        $result = $this->http->legacyPost("/api/transactions/{$transactionId}", [
            'Amount' => $amount,
        ]);

        if (($result['ErrorCode'] ?? -1) !== 0) {
            throw new \QrCommunication\VivaMerchant\Exceptions\ApiException(
                $result['ErrorText'] ?? 'Capture failed',
                400,
                $result,
            );
        }

        return $result;
    }

    /**
     * Charge a recurring payment using the initial transaction token.
     *
     * @param  string  $initialTransactionId  The initial transaction UUID
     * @param  int  $amount  Amount in cents
     * @param  string|null  $sourceCode  Payment source code
     * @return array<string, mixed>
     */
    public function recurring(string $initialTransactionId, int $amount, ?string $sourceCode = null): array
    {
        $payload = ['Amount' => $amount];
        if ($sourceCode) {
            $payload['SourceCode'] = $sourceCode;
        }

        $result = $this->http->legacyPost("/api/transactions/{$initialTransactionId}", $payload);

        if (($result['ErrorCode'] ?? -1) !== 0) {
            throw new \QrCommunication\VivaMerchant\Exceptions\ApiException(
                $result['ErrorText'] ?? 'Recurring charge failed',
                400,
                $result,
            );
        }

        return $result;
    }
}
