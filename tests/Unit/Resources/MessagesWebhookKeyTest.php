<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Resources\Messages;

final class MessagesWebhookKeyTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Messages $messages;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->messages = new Messages($this->http);
    }

    #[Test]
    public function get_webhook_key_calls_legacy_get_on_token_endpoint(): void
    {
        $this->http
            ->expects($this->once())
            ->method('legacyGet')
            ->with('/api/messages/config/token')
            ->willReturn(['Key' => 'verification_key']);

        $result = $this->messages->getWebhookKey();

        $this->assertSame(['Key' => 'verification_key'], $result);
    }
}
