<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Resources\Messages;

final class MessagesTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Messages $messages;

    protected function setUp(): void
    {
        $this->http     = $this->createMock(HttpClientInterface::class);
        $this->messages = new Messages($this->http);
    }

    #[Test]
    public function register_posts_to_legacy_api_with_correct_payload(): void
    {
        $this->http
            ->expects($this->once())
            ->method('legacyPost')
            ->with(
                '/api/messages/config',
                [
                    'Url'           => 'https://example.com/webhooks/viva',
                    'EventTypeId'   => 768,
                    'MessageTypeId' => 0,
                    'IsActive'      => true,
                ],
            )
            ->willReturn(['Id' => 'sub-uuid-1', 'Active' => true]);

        $result = $this->messages->register(768, 'https://example.com/webhooks/viva');

        $this->assertSame(['Id' => 'sub-uuid-1', 'Active' => true], $result);
    }

    #[Test]
    public function register_sends_correct_event_type_id(): void
    {
        $this->http
            ->expects($this->once())
            ->method('legacyPost')
            ->with(
                '/api/messages/config',
                $this->callback(fn (array $body) => $body['EventTypeId'] === 2054),
            )
            ->willReturn(['Id' => 'sub-uuid-2', 'Active' => true]);

        $this->messages->register(2054, 'https://example.com/webhooks/viva');
    }

    #[Test]
    public function list_calls_legacy_get_on_config_endpoint(): void
    {
        $expected = [
            ['Id' => 'sub-1', 'EventTypeId' => 768,  'Url' => 'https://example.com/webhooks/viva'],
            ['Id' => 'sub-2', 'EventTypeId' => 2054, 'Url' => 'https://example.com/webhooks/viva'],
        ];

        $this->http
            ->expects($this->once())
            ->method('legacyGet')
            ->with('/api/messages/config', [])
            ->willReturn($expected);

        $result = $this->messages->list();

        $this->assertSame($expected, $result);
    }

    #[Test]
    public function delete_calls_legacy_delete_path_with_message_id(): void
    {
        $this->http
            ->expects($this->once())
            ->method('legacyDeletePath')
            ->with('/api/messages/config/sub-uuid-1')
            ->willReturn([]);

        $this->messages->delete('sub-uuid-1');
    }

    #[Test]
    public function delete_returns_api_response(): void
    {
        $this->http
            ->method('legacyDeletePath')
            ->willReturn(['deleted' => true]);

        $result = $this->messages->delete('some-id');

        $this->assertSame(['deleted' => true], $result);
    }
}
