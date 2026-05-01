<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Contracts;

/**
 * Contract for webhook subscription management.
 *
 * Extracted to allow mocking in unit tests (Messages is final).
 * WebhookRegistrar depends on this interface, not the concrete class.
 *
 * @internal Implement via Messages only.
 */
interface MessagesInterface
{
    /**
     * @return array{Id: string, Active: bool}
     */
    public function register(int $eventTypeId, string $callbackUrl): array;

    /**
     * @return array<string, mixed>
     */
    public function list(): array;

    /**
     * @return array<string, mixed>
     */
    public function delete(string $messageId): array;
}
