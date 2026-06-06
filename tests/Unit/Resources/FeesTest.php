<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Resources\Fees;

final class FeesTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Fees $fees;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->fees = new Fees($this->http);
    }

    #[Test]
    public function get_calls_legacy_fees_with_amount_query(): void
    {
        $this->http
            ->expects($this->once())
            ->method('legacyGet')
            ->with('/api/fees', ['amount' => 1500])
            ->willReturn(['Fee' => 0.0, 'Success' => true]);

        $result = $this->fees->get(1500);

        $this->assertSame(['Fee' => 0.0, 'Success' => true], $result);
    }

    #[Test]
    public function get_includes_order_code_when_provided(): void
    {
        $this->http
            ->expects($this->once())
            ->method('legacyGet')
            ->with('/api/fees', ['amount' => 1500, 'orderCode' => 1234567890])
            ->willReturn(['Fee' => 1.5, 'Success' => true]);

        $this->fees->get(1500, 1234567890);
    }
}
