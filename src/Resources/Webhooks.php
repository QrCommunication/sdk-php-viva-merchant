<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

/**
 * Webhook verification and parsing.
 *
 * Viva Wallet sends a GET request to verify the webhook URL,
 * then POST requests with transaction events.
 *
 * @see https://developer.viva.com/webhooks-for-payments/
 */
final class Webhooks
{
    /**
     * All supported Viva Wallet webhook event types.
     *
     * @var array<int, string>
     */
    public const EVENTS = [
        1796 => 'transaction.payment.created',
        1797 => 'transaction.refund.created',
        1798 => 'transaction.payment.cancelled',
        1799 => 'transaction.reversal.created',
        1800 => 'transaction.preauth.created',
        1801 => 'transaction.preauth.completed',
        1802 => 'transaction.preauth.cancelled',
        1810 => 'pos.session.created',
        1811 => 'pos.session.failed',
        1812 => 'transaction.price.calculated',
        1813 => 'transaction.failed',
        1819 => 'account.connected',
        1820 => 'account.verification.status.changed',
        1821 => 'account.transaction.created',
        1822 => 'command.bank.transfer.created',
        1823 => 'command.bank.transfer.executed',
        1824 => 'transfer.created',
        1825 => 'obligation.created',
        1826 => 'obligation.captured',
        1827 => 'order.updated',
        1828 => 'sale.transactions.file',
    ];

    /**
     * Handle the webhook verification (GET request).
     *
     * Viva sends a GET with no body to verify the URL is reachable.
     * You must respond with your verification key.
     *
     * @param  string  $verificationKey  Your webhook verification key from Viva Dashboard
     * @return array{StatusCode: int, Key: string}
     */
    public function verificationResponse(string $verificationKey): array
    {
        return [
            'StatusCode' => 0,
            'Key' => $verificationKey,
        ];
    }

    /**
     * Parse a webhook POST payload.
     *
     * @param  string  $rawBody  Raw JSON body from the POST request
     * @return array{event_type: string, event_type_id: int, event_data: array<string, mixed>}
     */
    public function parse(string $rawBody): array
    {
        $data = json_decode($rawBody, true);

        if (! is_array($data)) {
            throw new \InvalidArgumentException('Invalid webhook payload: not valid JSON');
        }

        $eventTypeId = $data['EventTypeId'] ?? 0;

        return [
            'event_type' => $this->resolveEventType($eventTypeId),
            'event_type_id' => $eventTypeId,
            'event_data' => $data['EventData'] ?? $data,
        ];
    }

    /**
     * Check if an event type ID is known.
     */
    public function isKnownEvent(int $eventTypeId): bool
    {
        return isset(self::EVENTS[$eventTypeId]);
    }

    /**
     * Get all known event type IDs.
     *
     * @return array<int>
     */
    public function eventTypeIds(): array
    {
        return array_keys(self::EVENTS);
    }

    /**
     * Map Viva EventTypeId to a human-readable event type.
     */
    private function resolveEventType(int $eventTypeId): string
    {
        return match ($eventTypeId) {
            1796 => 'transaction.payment.created',
            1797 => 'transaction.refund.created',
            1798 => 'transaction.payment.cancelled',
            1799 => 'transaction.reversal.created',
            1800 => 'transaction.preauth.created',
            1801 => 'transaction.preauth.completed',
            1802 => 'transaction.preauth.cancelled',
            1810 => 'pos.session.created',
            1811 => 'pos.session.failed',
            1812 => 'transaction.price.calculated',
            1813 => 'transaction.failed',
            1819 => 'account.connected',
            1820 => 'account.verification.status.changed',
            1821 => 'account.transaction.created',
            1822 => 'command.bank.transfer.created',
            1823 => 'command.bank.transfer.executed',
            1824 => 'transfer.created',
            1825 => 'obligation.created',
            1826 => 'obligation.captured',
            1827 => 'order.updated',
            1828 => 'sale.transactions.file',
            default => "unknown.{$eventTypeId}",
        };
    }
}
