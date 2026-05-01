<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Helpers;

use QrCommunication\VivaMerchant\Contracts\MessagesInterface;
use QrCommunication\VivaMerchant\Exceptions\ApiException;

/**
 * Idempotent webhook registration helper for this merchant account.
 *
 * Ensures banking events are registered without throwing on duplicates.
 * Mirrors the WebhookRegistrar in sdk-php-viva-isv but without connectedMerchantId
 * since this SDK operates as the merchant itself.
 *
 * Usage:
 *
 *     $results = $viva->webhookRegistrar()->registerAll('https://example.com/webhooks/viva');
 *     // ['768' => 'registered', '769' => 'registered', '2054' => 'already_exists']
 *
 * Convention cross-SDK : même noms de méthode et constantes que sdk-php-viva-isv.
 */
final class WebhookRegistrar
{
    /**
     * Banking-related event type IDs that must be registered per-merchant
     * (not via ISV-level webhooks).
     *
     * Same constant name as in sdk-php-viva-isv for cross-SDK consistency.
     *
     * @var array<int, string>
     */
    public const BANKING_EVENTS = [
        768  => 'Command Bank Transfer Created',
        769  => 'Command Bank Transfer Executed',
        2054 => 'Account Transaction Created',
    ];

    public function __construct(private readonly MessagesInterface $messages) {}

    /**
     * Register all (or a subset of) banking webhook events idempotently.
     *
     * Returns a status map indexed by event type ID:
     *  - 'registered'    : subscription was just created
     *  - 'already_exists': Viva returned a 400 duplicate error (safe to ignore)
     *  - 'error:{msg}'   : unexpected failure
     *
     * @param  string          $callbackUrl  Full HTTPS URL to receive webhook POSTs
     * @param  list<int>|null  $events       Subset of event IDs; null = all BANKING_EVENTS
     * @return array<string, string>         Status map keyed by string event type ID
     */
    public function registerAll(string $callbackUrl, ?array $events = null): array
    {
        $eventIds = $events ?? array_keys(self::BANKING_EVENTS);
        $results  = [];

        foreach ($eventIds as $eventTypeId) {
            $key = (string) $eventTypeId;

            try {
                $this->messages->register($eventTypeId, $callbackUrl);
                $results[$key] = 'registered';
            } catch (ApiException $e) {
                if ($this->isDuplicateError($e)) {
                    $results[$key] = 'already_exists';
                } else {
                    $results[$key] = 'error:' . $e->getMessage();
                }
            }
        }

        return $results;
    }

    /**
     * Determine whether an ApiException signals a duplicate subscription.
     *
     * Viva returns HTTP 400 with a body containing "duplicate" or error code 1100.
     */
    private function isDuplicateError(ApiException $e): bool
    {
        if ($e->httpStatus === 400) {
            $body    = $e->responseBody ?? [];
            $text    = strtolower($body['ErrorText'] ?? $body['message'] ?? $body['detail'] ?? '');
            $errCode = $body['ErrorCode'] ?? $body['errorCode'] ?? null;

            return str_contains($text, 'duplicate')
                || str_contains($text, 'already')
                || $errCode === 1100;
        }

        return false;
    }
}
