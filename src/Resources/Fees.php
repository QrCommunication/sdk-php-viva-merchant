<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;

/**
 * Fees — retrieve the fee amount for a given payment amount / order.
 *
 * Uses the Legacy API (GET /api/fees) with Basic Auth.
 *
 * @see https://developer.viva.com/apis-for-payments/
 */
final class Fees
{
    public function __construct(
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Retrieve the fee amount for a payment.
     *
     * @param  int  $amount  Amount in cents associated with the payment order
     * @param  int|null  $orderCode  Optional order code to scope the fee calculation
     * @return array<string, mixed>  { Fee, BillFee, ServiceFee, ResellerFee, ..., Success }
     */
    public function get(int $amount, ?int $orderCode = null): array
    {
        return $this->http->legacyGet('/api/fees', array_filter([
            'amount' => $amount,
            'orderCode' => $orderCode,
        ], fn ($v) => $v !== null));
    }
}
