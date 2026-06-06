<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;

/**
 * Payments — RF Code generation.
 *
 * RF Codes let a merchant get paid via bank reference (applicable in Greece).
 * Uses the New API (POST /web2/checkout/v2/paymentsessions) with Bearer OAuth2.
 *
 * @see https://developer.viva.com/apis-for-payments/
 */
final class Payments
{
    public function __construct(
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Generate an RF Code for an existing payment order.
     *
     * @param  array<string, mixed>  $payload  e.g. { orderCode: int }
     * @return array<string, mixed>
     */
    public function createRfCode(array $payload): array
    {
        return $this->http->post('/web2/checkout/v2/paymentsessions', $payload);
    }
}
