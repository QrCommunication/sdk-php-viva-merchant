<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Enums\Environment;
use QrCommunication\VivaMerchant\Resources\Orders;

final class OrdersUpdateTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Orders $orders;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->orders = new Orders(
            $this->http,
            new Config('mid', 'key', 'cid', 'secret', Environment::DEMO),
        );
    }

    #[Test]
    public function update_patches_legacy_orders_endpoint_with_data(): void
    {
        $data = ['amount' => 2000, 'isCanceled' => true];

        $this->http
            ->expects($this->once())
            ->method('legacyPatch')
            ->with('/api/orders/7909823376998902', $data)
            ->willReturn(['Success' => true]);

        $result = $this->orders->update(7909823376998902, $data);

        $this->assertSame(['Success' => true], $result);
    }
}
