<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Resources\DataServices;

final class DataServicesSearchTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private DataServices $dataServices;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->dataServices = new DataServices($this->http);
    }

    #[Test]
    public function search_transactions_posts_filters_in_body_and_paging_in_query(): void
    {
        $filters = ['OrderCode' => '4054144410382502', 'StatusIds' => ['F']];

        $this->http
            ->expects($this->once())
            ->method('post')
            ->with(
                '/dataservices/v2/transactions/Search?Page=2&PageSize=25',
                $filters,
            )
            ->willReturn(['items' => []]);

        $result = $this->dataServices->searchTransactions($filters, 2, 25);

        $this->assertSame(['items' => []], $result);
    }

    #[Test]
    public function search_transactions_uses_default_paging(): void
    {
        $this->http
            ->expects($this->once())
            ->method('post')
            ->with('/dataservices/v2/transactions/Search?Page=0&PageSize=50', [])
            ->willReturn([]);

        $this->dataServices->searchTransactions();
    }

    #[Test]
    public function search_account_transactions_posts_to_v2_accounttransactions(): void
    {
        $filters = ['DateFrom' => '2024-01-01', 'DateTo' => '2024-02-01'];

        $this->http
            ->expects($this->once())
            ->method('post')
            ->with(
                '/dataservices/v2/accounttransactions/Search?Page=1&PageSize=100',
                $filters,
            )
            ->willReturn(['items' => []]);

        $this->dataServices->searchAccountTransactions($filters, 1, 100);
    }
}
