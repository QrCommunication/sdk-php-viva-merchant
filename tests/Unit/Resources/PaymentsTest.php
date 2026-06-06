<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Resources\Payments;

final class PaymentsTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Payments $payments;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->payments = new Payments($this->http);
    }

    #[Test]
    public function create_rf_code_posts_to_paymentsessions(): void
    {
        $payload = ['orderCode' => 3097103021072605];

        $this->http
            ->expects($this->once())
            ->method('post')
            ->with('/web2/checkout/v2/paymentsessions', $payload)
            ->willReturn(['rfCode' => 'RF12']);

        $result = $this->payments->createRfCode($payload);

        $this->assertSame(['rfCode' => 'RF12'], $result);
    }
}
