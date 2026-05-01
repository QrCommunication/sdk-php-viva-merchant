<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Contracts\MessagesInterface;

/**
 * Manage webhook subscriptions for this merchant account via /api/messages/config.
 *
 * Uses Legacy API (Basic Auth: MerchantId:ApiKey) — not Bearer OAuth2 — because
 * /api/messages/config lives on the legacy host (www.vivapayments.com / demo.vivapayments.com).
 *
 * This is the merchant-side counterpart of IsvMessages in sdk-php-viva-isv.
 * The difference: no connectedMerchantId — we ARE the merchant.
 *
 * Banking events to register per-merchant (not via ISV webhooks):
 *
 *  - 768  : Command Bank Transfer Created
 *  - 769  : Command Bank Transfer Executed
 *  - 2054 : Account Transaction Created
 *
 * @see https://developer.viva.com/webhooks-for-payments/
 * @see https://developer.viva.com/apis-for-payments/bank-transfer-api/
 */
final class Messages implements MessagesInterface
{
    public function __construct(private readonly HttpClientInterface $http) {}

    /**
     * Register a webhook subscription for a given event type.
     *
     * @param  int     $eventTypeId   Viva Wallet event type ID (e.g. 768, 769, 2054)
     * @param  string  $callbackUrl   Full HTTPS URL that will receive POST payloads
     * @return array{Id: string, Active: bool}
     */
    public function register(int $eventTypeId, string $callbackUrl): array
    {
        return $this->http->legacyPost('/api/messages/config', [
            'Url'           => $callbackUrl,
            'EventTypeId'   => $eventTypeId,
            'MessageTypeId' => 0,
            'IsActive'      => true,
        ]);
    }

    /**
     * List all registered webhook subscriptions for this merchant.
     *
     * @return array<string, mixed>
     */
    public function list(): array
    {
        return $this->http->legacyGet('/api/messages/config');
    }

    /**
     * Delete a webhook subscription by its ID.
     *
     * @param  string  $messageId  The subscription ID returned by register() or list()
     * @return array<string, mixed>
     */
    public function delete(string $messageId): array
    {
        return $this->http->legacyDeletePath('/api/messages/config/' . $messageId);
    }
}
