<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\HttpClient;

/**
 * Native Checkout — Apple Pay & Google Pay.
 *
 * Generate charge tokens from Apple Pay / Google Pay payment data,
 * then execute transactions using those tokens.
 * All endpoints use the New API (Bearer OAuth2).
 *
 * @see https://developer.viva.com/native-checkout-v2/
 */
final class NativeCheckout
{
    private const PAYMENT_METHOD_APPLE_PAY = 10;

    private const PAYMENT_METHOD_GOOGLE_PAY = 11;

    public function __construct(
        private readonly HttpClient $http,
    ) {}

    /**
     * Generate a one-time charge token from Apple Pay / Google Pay payment data.
     *
     * @param  int  $amount  Amount in cents (e.g. 1500 = €15.00)
     * @param  string  $paymentData  Payment data string from Apple Pay / Google Pay
     * @param  string  $paymentMethod  'applepay' or 'googlepay'
     * @param  string|null  $sourceCode  Payment source code
     * @param  string|null  $dynamicDescriptor  Dynamic descriptor for the charge
     * @return array{chargeToken: string, redirectToACSForm: ?string}
     */
    public function createChargeToken(
        int $amount,
        string $paymentData,
        string $paymentMethod = 'applepay',
        ?string $sourceCode = null,
        ?string $dynamicDescriptor = null,
    ): array {
        $paymentMethodId = match (strtolower($paymentMethod)) {
            'applepay', 'apple_pay', 'apple' => self::PAYMENT_METHOD_APPLE_PAY,
            'googlepay', 'google_pay', 'google' => self::PAYMENT_METHOD_GOOGLE_PAY,
            default => self::PAYMENT_METHOD_APPLE_PAY,
        };

        return $this->http->post('/nativecheckout/v2/chargetokens', array_filter([
            'amount' => $amount,
            'chargeData' => $paymentData,
            'paymentMethodId' => $paymentMethodId,
            'sourceCode' => $sourceCode,
            'dynamicDescriptor' => $dynamicDescriptor,
        ], fn ($v) => $v !== null));
    }

    /**
     * Execute a transaction using a charge token from createChargeToken().
     *
     * @param  string  $chargeToken  Token obtained from createChargeToken()
     * @param  int  $amount  Amount in cents (e.g. 1500 = €15.00)
     * @param  int  $currencyCode  ISO 4217 numeric code (default: 978 = EUR)
     * @param  string|null  $sourceCode  Payment source code
     * @param  string|null  $merchantTrns  Internal reference
     * @param  string|null  $customerTrns  Description shown to customer
     * @param  bool  $preauth  Pre-authorize only (capture later)
     * @param  int  $tipAmount  Tip amount in cents
     * @param  int|null  $installments  Number of installments (null = disabled)
     * @return array{transactionId: string, statusId: string, amount: int, orderCode: int}
     */
    public function createTransaction(
        string $chargeToken,
        int $amount,
        int $currencyCode = 978,
        ?string $sourceCode = null,
        ?string $merchantTrns = null,
        ?string $customerTrns = null,
        bool $preauth = false,
        int $tipAmount = 0,
        ?int $installments = null,
    ): array {
        $payload = [
            'chargeToken' => $chargeToken,
            'amount' => $amount,
            'currencyCode' => $currencyCode,
        ];

        if ($sourceCode !== null) {
            $payload['sourceCode'] = $sourceCode;
        }
        if ($merchantTrns !== null) {
            $payload['merchantTrns'] = $merchantTrns;
        }
        if ($customerTrns !== null) {
            $payload['customerTrns'] = $customerTrns;
        }
        if ($preauth) {
            $payload['preauth'] = true;
        }
        if ($tipAmount > 0) {
            $payload['tipAmount'] = $tipAmount;
        }
        if ($installments !== null && $installments > 0) {
            $payload['installments'] = $installments;
        }

        return $this->http->post('/nativecheckout/v2/transactions', $payload);
    }
}
