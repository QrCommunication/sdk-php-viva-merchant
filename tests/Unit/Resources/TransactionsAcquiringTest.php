<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Tests\Unit\Resources;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QrCommunication\VivaMerchant\Config;
use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;
use QrCommunication\VivaMerchant\Enums\Environment;
use QrCommunication\VivaMerchant\Exceptions\ApiException;
use QrCommunication\VivaMerchant\Resources\Transactions;

final class TransactionsAcquiringTest extends TestCase
{
    private HttpClientInterface&MockObject $http;
    private Transactions $transactions;

    protected function setUp(): void
    {
        $this->http = $this->createMock(HttpClientInterface::class);
        $this->transactions = new Transactions(
            $this->http,
            new Config('mid', 'key', 'cid', 'secret', Environment::DEMO),
        );
    }

    #[Test]
    public function increase_preauth_posts_to_acquiring_with_camel_case_amount(): void
    {
        $this->http
            ->expects($this->once())
            ->method('post')
            ->with(
                '/acquiring/v1/transactions/txn-1:increasepreauth',
                ['amount' => 500],
            )
            ->willReturn(['transactionId' => 'txn-1']);

        $result = $this->transactions->increasePreauth('txn-1', 500);

        $this->assertSame(['transactionId' => 'txn-1'], $result);
    }

    #[Test]
    public function rebate_posts_amount_and_filters_null_reference(): void
    {
        $this->http
            ->expects($this->once())
            ->method('post')
            ->with(
                '/acquiring/v1/transactions/txn-2:rebate',
                ['amount' => 100],
            )
            ->willReturn(['transactionId' => 'rb-1']);

        $this->transactions->rebate('txn-2', 100);
    }

    #[Test]
    public function rebate_includes_merchant_reference_when_provided(): void
    {
        $this->http
            ->expects($this->once())
            ->method('post')
            ->with(
                '/acquiring/v1/transactions/txn-2:rebate',
                ['amount' => 100, 'merchantTrns' => 'ref-9'],
            )
            ->willReturn(['transactionId' => 'rb-2']);

        $this->transactions->rebate('txn-2', 100, 'ref-9');
    }

    #[Test]
    public function fast_refund_posts_to_acquiring_fastrefund(): void
    {
        $this->http
            ->expects($this->once())
            ->method('post')
            ->with(
                '/acquiring/v1/transactions/txn-3:fastrefund',
                ['amount' => 250, 'merchantTrns' => 'ref-x'],
            )
            ->willReturn(['transactionId' => 'fr-1']);

        $this->transactions->fastRefund('txn-3', 250, 'ref-x');
    }

    #[Test]
    public function cancel_acquiring_deletes_without_amount(): void
    {
        $this->http
            ->expects($this->once())
            ->method('delete')
            ->with('/acquiring/v1/transactions/txn-4')
            ->willReturn(['transactionId' => 'txn-4']);

        $this->transactions->cancelAcquiring('txn-4');
    }

    #[Test]
    public function cancel_acquiring_appends_amount_as_query(): void
    {
        $this->http
            ->expects($this->once())
            ->method('delete')
            ->with('/acquiring/v1/transactions/txn-4?amount=500')
            ->willReturn(['transactionId' => 'txn-4']);

        $this->transactions->cancelAcquiring('txn-4', 500);
    }

    #[Test]
    public function create_card_token_posts_body_to_cards_tokens(): void
    {
        $card = ['transactionId' => '0de405a3', 'cardTokenType' => 0];

        $this->http
            ->expects($this->once())
            ->method('post')
            ->with('/acquiring/v1/cards/tokens', $card)
            ->willReturn(['token' => 'ct_5d0a']);

        $result = $this->transactions->createCardToken($card);

        $this->assertSame(['token' => 'ct_5d0a'], $result);
    }

    #[Test]
    public function moto_posts_to_legacy_transactions_endpoint(): void
    {
        $payload = [
            'moto' => true,
            'orderCode' => 7909823376998902,
            'creditcard' => ['number' => '5199816266546408', 'expirationDate' => '2030-01'],
        ];

        $this->http
            ->expects($this->once())
            ->method('legacyPost')
            ->with('/api/transactions', $payload)
            ->willReturn(['ErrorCode' => 0, 'TransactionId' => 'm-1', 'Success' => true]);

        $result = $this->transactions->moto($payload);

        $this->assertSame('m-1', $result['TransactionId']);
    }

    #[Test]
    public function moto_throws_on_non_zero_error_code(): void
    {
        $this->http
            ->expects($this->atLeastOnce())
            ->method('legacyPost')
            ->willReturn(['ErrorCode' => 12, 'ErrorText' => 'Declined']);

        $this->expectException(ApiException::class);
        $this->expectExceptionMessage('Declined');

        $this->transactions->moto(['moto' => true]);
    }
}
