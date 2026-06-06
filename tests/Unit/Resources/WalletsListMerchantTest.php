<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Enums\Environment;
use QrCommunication\VivaMerchant\Resources\Wallets;

final class WalletsListMerchantTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Wallets $wallets;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->wallets = new Wallets(
            $this->http,
            new Config('mid', 'key', 'cid', 'secret', Environment::DEMO),
        );
    }

    #[Test]
    public function list_merchant_gets_merchants_wallets_via_bearer(): void
    {
        $expected = [['walletId' => 91883585, 'isPrimary' => true]];

        $this->http
            ->expects($this->once())
            ->method('get')
            ->with('/merchants/v1/wallets')
            ->willReturn($expected);

        $result = $this->wallets->listMerchant();

        $this->assertSame($expected, $result);
    }
}
