<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Resources\BankAccounts;

final class BankAccountsUpdateTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private BankAccounts $bankAccounts;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->bankAccounts = new BankAccounts($this->http);
    }

    #[Test]
    public function update_patches_bankaccount_with_camel_case_body(): void
    {
        $data = ['archive' => true, 'friendlyName' => 'My Bank'];

        $this->http
            ->expects($this->once())
            ->method('patch')
            ->with('/banktransfers/v1/bankaccounts/ba-1', $data)
            ->willReturn($data);

        $result = $this->bankAccounts->update('ba-1', $data);

        $this->assertSame($data, $result);
    }
}
