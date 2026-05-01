<?php

declare(strict_types=1);

namespace QrCommunication\VivaMerchant;

use QrCommunication\VivaMerchant\Enums\Environment;
use QrCommunication\VivaMerchant\Helpers\WebhookRegistrar;
use QrCommunication\VivaMerchant\Resources\Account;
use QrCommunication\VivaMerchant\Resources\BankAccounts;
use QrCommunication\VivaMerchant\Resources\DataServices;
use QrCommunication\VivaMerchant\Resources\Messages;
use QrCommunication\VivaMerchant\Resources\NativeCheckout;
use QrCommunication\VivaMerchant\Resources\Orders;
use QrCommunication\VivaMerchant\Resources\Sources;
use QrCommunication\VivaMerchant\Resources\Transactions;
use QrCommunication\VivaMerchant\Resources\Wallets;
use QrCommunication\VivaMerchant\Resources\Webhooks;

/**
 * Viva Wallet Merchant SDK — point d'entrée principal.
 *
 * Utilisation :
 *
 *     $viva = new VivaClient(
 *         merchantId: 'your-uuid',
 *         apiKey: 'your-api-key',
 *         clientId: 'your-client-id.apps.vivapayments.com',
 *         clientSecret: 'your-client-secret',
 *         environment: 'demo', // ou 'production'
 *     );
 *
 *     // Créer un ordre de paiement
 *     $order = $viva->orders->create(amount: 1500, customerDescription: 'Consultation');
 *     // => ['order_code' => 1234567890, 'checkout_url' => 'https://...']
 *
 *     // Récupérer une transaction
 *     $txn = $viva->transactions->get('uuid-here');
 *
 *     // Rembourser
 *     $viva->transactions->cancel('uuid-here', amount: 500);
 *
 *     // Capturer un preauth
 *     $viva->transactions->capture('uuid-here', amount: 1500);
 *
 *     // Paiement récurrent
 *     $viva->transactions->recurring('initial-txn-uuid', amount: 1500);
 *
 *     // Paiement Apple Pay / Google Pay
 *     $token = $viva->nativeCheckout->createChargeToken(1500, $applePayData);
 *     $txn = $viva->nativeCheckout->createTransaction($token['chargeToken'], 1500);
 *
 *     // Rapport MT940
 *     $report = $viva->dataServices->mt940('2026-03-18');
 *
 *     // Enregistrer les webhooks banking (idempotent)
 *     $viva->webhookRegistrar()->registerAll('https://example.com/webhooks/viva');
 *
 *     // Gérer les abonnements webhook manuellement
 *     $viva->messages()->register(768, 'https://example.com/webhooks/viva');
 *     $viva->messages()->list();
 */
final class VivaClient
{
    public readonly Orders $orders;

    public readonly Transactions $transactions;

    public readonly Sources $sources;

    public readonly Webhooks $webhooks;

    public readonly Wallets $wallets;

    public readonly BankAccounts $bankAccounts;

    public readonly Account $account;

    public readonly NativeCheckout $nativeCheckout;

    public readonly DataServices $dataServices;

    private readonly Config $config;

    private readonly HttpClient $http;

    private ?Messages $messagesResource = null;

    private ?WebhookRegistrar $webhookRegistrarHelper = null;

    public function __construct(
        string $merchantId,
        string $apiKey,
        string $clientId,
        string $clientSecret,
        string|Environment $environment = Environment::DEMO,
    ) {
        $this->config = new Config(
            merchantId: $merchantId,
            apiKey: $apiKey,
            clientId: $clientId,
            clientSecret: $clientSecret,
            environment: $environment,
        );

        $this->http = new HttpClient($this->config);

        $this->orders = new Orders($this->http, $this->config);
        $this->transactions = new Transactions($this->http, $this->config);
        $this->sources = new Sources($this->http);
        $this->wallets = new Wallets($this->http, $this->config);
        $this->bankAccounts = new BankAccounts($this->http);
        $this->webhooks = new Webhooks;
        $this->account = new Account($this->http, $this->config);
        $this->nativeCheckout = new NativeCheckout($this->http);
        $this->dataServices = new DataServices($this->http);
    }

    /**
     * Access webhook subscription management (/api/messages/config).
     *
     * Prefer webhookRegistrar() for idempotent banking event setup.
     */
    public function messages(): Messages
    {
        return $this->messagesResource ??= new Messages($this->http);
    }

    /**
     * Idempotent helper to register banking webhook events.
     *
     * Events registered: 768 (Bank Transfer Created), 769 (Bank Transfer Executed),
     * 2054 (Account Transaction Created).
     *
     * Usage: $viva->webhookRegistrar()->registerAll('https://example.com/webhooks/viva');
     */
    public function webhookRegistrar(): WebhookRegistrar
    {
        return $this->webhookRegistrarHelper ??= new WebhookRegistrar($this->messages());
    }

    /**
     * Get the configuration (for debugging or introspection).
     */
    public function getConfig(): Config
    {
        return $this->config;
    }

    /**
     * Force re-authentication on the next API call.
     */
    public function invalidateToken(): void
    {
        $this->http->invalidateToken();
    }

    /**
     * Test the connection by authenticating and fetching account info.
     */
    public function testConnection(): bool
    {
        try {
            $this->account->info();

            return true;
        } catch (\Exception) {
            return false;
        }
    }
}
