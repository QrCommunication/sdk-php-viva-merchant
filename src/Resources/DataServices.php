<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant\Resources;

use QrCommunication\VivaMerchant\Contracts\HttpClientInterface;

/**
 * Data Services — reporting, reconciliation, and webhook subscriptions.
 *
 * MT940 bank statement format, file generation requests,
 * and webhook subscription management.
 * All endpoints use the New API (Bearer OAuth2).
 *
 * @see https://developer.viva.com/apis-for-payments/data-services/
 */
final class DataServices
{
    public function __construct(
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Retrieve MT940 report data for a date.
     *
     * MT940 is a standard banking format for account statements.
     *
     * @param  string  $date  Date in Y-m-d format
     * @return array<string, mixed>  MT940 report data
     */
    public function mt940(string $date): array
    {
        return $this->http->get('/dataservices/v1/mt940', ['date' => $date]);
    }

    /**
     * Create a webhook subscription.
     *
     * @param  string  $url  Webhook URL to receive events
     * @param  string  $eventType  Event type to subscribe to (e.g. 'SaleTransactionsFileGenerated')
     * @return array{subscriptionId: string, url: string, eventType: string}
     */
    public function createSubscription(string $url, string $eventType = 'SaleTransactionsFileGenerated'): array
    {
        return $this->http->post('/dataservices/v1/webhooks/subscriptions', [
            'url' => $url,
            'eventType' => $eventType,
        ]);
    }

    /**
     * Update a webhook subscription.
     *
     * @param  string  $subscriptionId  Subscription UUID
     * @param  string  $url  New webhook URL
     * @param  string|null  $eventType  New event type (null = keep current)
     * @return array<string, mixed>
     */
    public function updateSubscription(string $subscriptionId, string $url, ?string $eventType = null): array
    {
        return $this->http->put("/dataservices/v1/webhooks/subscriptions/{$subscriptionId}", array_filter([
            'url' => $url,
            'eventType' => $eventType,
        ], fn ($v) => $v !== null));
    }

    /**
     * Delete a webhook subscription.
     *
     * @param  string  $subscriptionId  Subscription UUID
     * @return array<string, mixed>
     */
    public function deleteSubscription(string $subscriptionId): array
    {
        return $this->http->delete("/dataservices/v1/webhooks/subscriptions/{$subscriptionId}");
    }

    /**
     * List all webhook subscriptions.
     *
     * @return array<int, array{subscriptionId: string, url: string, eventType: string}>
     */
    public function listSubscriptions(): array
    {
        return $this->http->get('/dataservices/v1/webhooks/subscriptions/');
    }

    /**
     * Request a file generation for a specific date.
     *
     * Triggers asynchronous generation of a transaction file.
     * Use webhook subscription to be notified when the file is ready.
     *
     * @param  string  $date  Date in Y-m-d format
     * @return array<string, mixed>
     */
    public function requestFile(string $date): array
    {
        return $this->http->post('/dataservices/v1/file-request', [
            'date' => $date,
        ]);
    }

    /**
     * Search historical transactions via the Data Service (v2, Bearer).
     *
     * Pagination (page/pageSize) is sent as query params; filters go in the body
     * (PascalCase per the Data Service spec, e.g. insDateFrom, StatusIds, OrderCode).
     *
     * @param  array<string, mixed>  $filters  Search filters (request body)
     * @param  int  $page  Page number (0-based)
     * @param  int  $pageSize  Records per page (max 500)
     * @return array<string, mixed>
     */
    public function searchTransactions(array $filters = [], int $page = 0, int $pageSize = 50): array
    {
        $query = http_build_query(['Page' => $page, 'PageSize' => $pageSize]);

        return $this->http->post("/dataservices/v2/transactions/Search?{$query}", $filters);
    }

    /**
     * Search account transactions via the Data Service (v2, Bearer).
     *
     * Pagination (page/pageSize) is sent as query params; filters go in the body
     * (e.g. DateFrom, DateTo, WalletId, SubTypeIds, AmountFrom, AmountTo).
     *
     * @param  array<string, mixed>  $filters  Search filters (request body)
     * @param  int  $page  Page number (0-based)
     * @param  int  $pageSize  Records per page (max 500)
     * @return array<string, mixed>
     */
    public function searchAccountTransactions(array $filters = [], int $page = 0, int $pageSize = 50): array
    {
        $query = http_build_query(['Page' => $page, 'PageSize' => $pageSize]);

        return $this->http->post("/dataservices/v2/accounttransactions/Search?{$query}", $filters);
    }
}
