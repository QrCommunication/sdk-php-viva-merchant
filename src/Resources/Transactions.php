<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;

/**
 * Transaction operations — get, list, refund, capture, recurring, acquiring.
 *
 * Legacy API (Basic Auth) for capture/recurring/cancel/MOTO.
 * New API (Bearer OAuth) for /checkout/v2 reads and /acquiring/v1 operations
 * (increase preauth, rebate, fast refund, partial-auth cancel, card tokens).
 *
 * @see https://developer.viva.com/apis-for-payments/
 */
final class Transactions
{
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly Config $config,
    ) {}

    /**
     * Get transaction details via Legacy API (Basic Auth).
     *
     * Returns full transaction data including Fee, Commission, Order, Payment, CreditCard.
     *
     * @return array<string, mixed>  Raw Viva transaction data (PascalCase keys)
     */
    public function get(string $transactionId): array
    {
        return $this->http->legacyGet("/api/transactions/{$transactionId}");
    }

    /**
     * Get transaction details via New API (Bearer OAuth).
     *
     * Returns a lighter response: email, amount, orderCode, statusId, fullName,
     * cardNumber (masked), currencyCode, recurringSupport, cardUniqueReference.
     * This is the endpoint recommended by Viva for verifying Smart Checkout payments.
     *
     * @return array<string, mixed>  Transaction data (camelCase keys)
     */
    public function getV2(string $transactionId): array
    {
        return $this->http->get("/checkout/v2/transactions/{$transactionId}");
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

    /**
     * Increase the amount of an existing pre-authorized transaction (Acquiring API, Bearer).
     *
     * Used when the final amount is not known upfront (hotels, car rentals, fuel).
     *
     * @param  string  $transactionId  The pre-auth transaction UUID
     * @param  int  $amount  Amount in cents to add to the original authorized amount
     * @return array<string, mixed>  { transactionId }
     */
    public function increasePreauth(string $transactionId, int $amount): array
    {
        return $this->http->post("/acquiring/v1/transactions/{$transactionId}:increasepreauth", [
            'amount' => $amount,
        ]);
    }

    /**
     * Perform a rebate tied to an initial transaction (Acquiring API, Bearer).
     *
     * @param  string  $transactionId  The initial (parent) transaction UUID
     * @param  int  $amount  Amount in cents the merchant wishes to give to the client
     * @param  string|null  $merchantReference  Optional merchant reference
     * @return array<string, mixed>  { transactionId }
     */
    public function rebate(string $transactionId, int $amount, ?string $merchantReference = null): array
    {
        return $this->http->post("/acquiring/v1/transactions/{$transactionId}:rebate", array_filter([
            'amount' => $amount,
            'merchantTrns' => $merchantReference,
        ], fn ($v) => $v !== null));
    }

    /**
     * Perform a fast refund tied to an initial transaction (Acquiring API, Bearer).
     *
     * @param  string  $transactionId  The initial (parent) transaction UUID
     * @param  int  $amount  Amount in cents the merchant wishes to give to the client
     * @param  string|null  $merchantReference  Optional merchant reference
     * @return array<string, mixed>  { transactionId }
     */
    public function fastRefund(string $transactionId, int $amount, ?string $merchantReference = null): array
    {
        return $this->http->post("/acquiring/v1/transactions/{$transactionId}:fastrefund", array_filter([
            'amount' => $amount,
            'merchantTrns' => $merchantReference,
        ], fn ($v) => $v !== null));
    }

    /**
     * Cancel a transaction / partial authorization via the Acquiring API (Bearer).
     *
     * @param  string  $transactionId  Transaction UUID to cancel
     * @param  int|null  $amount  Amount in cents to cancel (sent as query param when provided)
     * @return array<string, mixed>  { transactionId }
     */
    public function cancelAcquiring(string $transactionId, ?int $amount = null): array
    {
        $path = "/acquiring/v1/transactions/{$transactionId}";
        if ($amount !== null) {
            $path .= '?'.http_build_query(['amount' => $amount]);
        }

        return $this->http->delete($path);
    }

    /**
     * Create a card token from an initial transaction (Acquiring API, Bearer).
     *
     * @param  array<string, mixed>  $card  Body, e.g. { transactionId, groupId?, cardTokenType? }
     * @return array<string, mixed>  { token }
     */
    public function createCardToken(array $card): array
    {
        return $this->http->post('/acquiring/v1/cards/tokens', $card);
    }

    /**
     * Make a MOTO (Mail Order / Telephone Order) card charge (Legacy API, Basic Auth).
     *
     * The payload must contain at least `moto` (always true), `creditcard`
     * (number + expirationDate) and `orderCode`.
     *
     * @param  array<string, mixed>  $payload  MOTO charge body (camelCase per Viva MOTO spec)
     * @return array<string, mixed>
     */
    public function moto(array $payload): array
    {
        $result = $this->http->legacyPost('/api/transactions', $payload);

        if (($result['ErrorCode'] ?? -1) !== 0) {
            throw new \QrCommunication\VivaMerchant\Exceptions\ApiException(
                $result['ErrorText'] ?? 'MOTO charge failed',
                400,
                $result,
            );
        }

        return $result;
    }
}
