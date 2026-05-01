<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Helpers;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\MessagesInterface;
use QrCommunication\VivaMerchant\Exceptions\ApiException;
use QrCommunication\VivaMerchant\Helpers\WebhookRegistrar;

final class WebhookRegistrarTest extends TestCase
{
    private MessagesInterface&MockObject $messages;
    private WebhookRegistrar $registrar;

    protected function setUp(): void
    {
        $this->messages   = $this->createMock(MessagesInterface::class);
        $this->registrar  = new WebhookRegistrar($this->messages);
    }

    #[Test]
    public function banking_events_constant_contains_expected_ids(): void
    {
        $this->assertArrayHasKey(768,  WebhookRegistrar::BANKING_EVENTS);
        $this->assertArrayHasKey(769,  WebhookRegistrar::BANKING_EVENTS);
        $this->assertArrayHasKey(2054, WebhookRegistrar::BANKING_EVENTS);
    }

    #[Test]
    public function register_all_calls_messages_register_for_each_event(): void
    {
        $this->messages
            ->expects($this->exactly(3))
            ->method('register')
            ->willReturn(['Id' => 'sub-uuid', 'Active' => true]);

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva');

        $this->assertSame(['768' => 'registered', '769' => 'registered', '2054' => 'registered'], $results);
    }

    #[Test]
    public function register_all_returns_already_exists_on_400_duplicate_error(): void
    {
        $this->messages
            ->method('register')
            ->willThrowException(
                new ApiException('duplicate subscription', 400, ['ErrorText' => 'duplicate']),
            );

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva');

        foreach ($results as $status) {
            $this->assertSame('already_exists', $status);
        }
    }

    #[Test]
    public function register_all_returns_already_exists_on_already_keyword(): void
    {
        $this->messages
            ->method('register')
            ->willThrowException(
                new ApiException('already registered', 400, ['message' => 'subscription already registered']),
            );

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva');

        foreach ($results as $status) {
            $this->assertSame('already_exists', $status);
        }
        $this->assertCount(3, $results);
    }

    #[Test]
    public function register_all_returns_already_exists_on_error_code_1100(): void
    {
        $this->messages
            ->method('register')
            ->willThrowException(
                new ApiException('conflict', 400, ['ErrorCode' => 1100]),
            );

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva');

        foreach ($results as $status) {
            $this->assertSame('already_exists', $status);
        }
        $this->assertCount(3, $results);
    }

    #[Test]
    public function register_all_returns_error_prefixed_message_on_unexpected_exception(): void
    {
        $this->messages
            ->method('register')
            ->willThrowException(
                new ApiException('Service unavailable', 503, []),
            );

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva');

        foreach ($results as $status) {
            $this->assertStringStartsWith('error:', $status);
        }
    }

    #[Test]
    public function register_all_accepts_subset_of_events(): void
    {
        $this->messages
            ->expects($this->exactly(2))
            ->method('register')
            ->willReturn(['Id' => 'sub-uuid', 'Active' => true]);

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva', [768, 769]);

        $this->assertArrayHasKey('768',  $results);
        $this->assertArrayHasKey('769',  $results);
        $this->assertArrayNotHasKey('2054', $results);
    }

    #[Test]
    public function register_all_handles_mixed_success_and_duplicate(): void
    {
        $this->messages
            ->method('register')
            ->willReturnCallback(function (int $eventTypeId): array {
                if ($eventTypeId === 768) {
                    throw new ApiException('duplicate', 400, ['ErrorText' => 'duplicate']);
                }

                return ['Id' => 'sub-uuid', 'Active' => true];
            });

        $results = $this->registrar->registerAll('https://example.com/webhooks/viva');

        $this->assertArrayHasKey('768',  $results);
        $this->assertArrayHasKey('769',  $results);
        $this->assertArrayHasKey('2054', $results);
        $this->assertContains('already_exists', $results);
        $this->assertNotContains('registered', [$results['768'] ?? '']);
        $this->assertSame(
            ['768' => 'already_exists', '769' => 'registered', '2054' => 'registered'],
            $results,
        );
    }
}
