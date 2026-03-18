# Viva Wallet Merchant SDK for PHP

SDK PHP pour l'API Viva Wallet — opérations marchands (ordres, transactions, sources, webhooks).

> **Ce SDK couvre les opérations marchands standard.** Pour les opérations ISV (comptes connectés, composite auth), voir `sdk-php-viva-isv`.

## Installation

```bash
composer require qrcommunication/viva-merchant-sdk
```

## Configuration

```php
use QrCommunication\VivaMerchant\VivaClient;

$viva = new VivaClient(
    merchantId: 'your-merchant-uuid',
    apiKey: 'your-api-key',
    clientId: 'your-client-id.apps.vivapayments.com',
    clientSecret: 'your-client-secret',
    environment: 'demo', // 'demo' ou 'production'
);
```

### Où trouver les credentials

| Credential | Emplacement dans Viva Dashboard |
|------------|--------------------------------|
| Merchant ID | Settings > API Access > Merchant ID |
| API Key | Settings > API Access > API Key |
| Client ID | Settings > API Access > OAuth Credentials > Client ID |
| Client Secret | Settings > API Access > OAuth Credentials > Client Secret |

## Utilisation

### Ordres de paiement (Smart Checkout)

```php
// Créer un ordre (montant en centimes)
$order = $viva->orders->create(
    amount: 1500,                           // €15.00
    customerDescription: 'Consultation',     // affiché au client
    merchantReference: 'session_123',        // référence interne
    allowRecurring: true,                    // tokeniser la carte
    preauth: false,                         // pré-autorisation
);

echo $order['checkout_url'];
// => https://demo.vivapayments.com/web/checkout?ref=1234567890

// Statut d'un ordre
$status = $viva->orders->get(1234567890);

// Annuler un ordre non payé
$viva->orders->cancel(1234567890);
```

### Transactions

```php
// Détails d'une transaction
$txn = $viva->transactions->get('transaction-uuid');

// Lister les transactions du jour
$txns = $viva->transactions->listByDate('2026-03-16');

// Remboursement total
$viva->transactions->cancel('transaction-uuid');

// Remboursement partiel (€5.00)
$viva->transactions->cancel('transaction-uuid', amount: 500);

// Capturer un preauth
$viva->transactions->capture('preauth-uuid', amount: 1500);

// Paiement récurrent (utilise le token de la transaction initiale)
$viva->transactions->recurring('initial-txn-uuid', amount: 1500);
```

### Sources de paiement

```php
// Lister les sources
$sources = $viva->sources->list();

// Créer une source
$viva->sources->create(
    name: 'Mon site web',
    sourceCode: '1234',
    domain: 'example.com',
);
```

### Webhooks

```php
// Vérification du webhook (répondre au GET de Viva)
$response = $viva->webhooks->verificationResponse('your-verification-key');
return response()->json($response);

// Parser un événement webhook (POST)
$event = $viva->webhooks->parse($request->getContent());
// => ['event_type' => 'transaction.payment.created', 'event_data' => [...]]

match ($event['event_type']) {
    'transaction.payment.created' => handlePayment($event['event_data']),
    'transaction.refund.created' => handleRefund($event['event_data']),
    default => null,
};
```

### Compte

```php
// Infos du compte marchand
$info = $viva->account->info();

// Solde des portefeuilles
$wallets = $viva->account->wallets();

// Test de connexion
$ok = $viva->testConnection(); // true/false
```

## Gestion des erreurs

```php
use QrCommunication\VivaMerchant\Exceptions\AuthenticationException;
use QrCommunication\VivaMerchant\Exceptions\ApiException;

try {
    $order = $viva->orders->create(amount: 1500);
} catch (AuthenticationException $e) {
    // Credentials invalides
    echo "Auth failed: {$e->getMessage()}";
} catch (ApiException $e) {
    // Erreur API Viva
    echo "API error [{$e->httpStatus}]: {$e->getMessage()}";
    echo "Error code: {$e->getErrorCode()}";
    echo "Error text: {$e->getErrorText()}";
}
```

## Architecture

```
VivaClient (point d'entrée)
├── orders       → Orders       (Legacy API, Basic Auth)
├── transactions → Transactions (Legacy API, Basic Auth)
├── sources      → Sources      (Legacy API, Basic Auth)
├── webhooks     → Webhooks     (pas d'auth — parsing/vérification)
└── account      → Account      (New API, Bearer OAuth)
```

### 3 hosts Viva Wallet

| Host | Auth | Usage dans le SDK |
|------|------|-------------------|
| `accounts.vivapayments.com` | Form POST | OAuth token (interne) |
| `api.vivapayments.com` | Bearer token | Account, New API |
| `www.vivapayments.com` | Basic Auth | Orders, Transactions, Sources |

> En mode `demo`, les hosts sont préfixés par `demo-` / `demo.`.

## Enums utiles

```php
use QrCommunication\VivaMerchant\Enums\TransactionStatus;
use QrCommunication\VivaMerchant\Enums\Currency;

$status = TransactionStatus::from('F');
$status->isSuccessful(); // true
$status->label();        // 'Finalized'

$eur = Currency::EUR;
$eur->value; // 978
```

## Environnements

| Env | Dashboard | API |
|-----|-----------|-----|
| Demo | demo.vivapayments.com | demo-api.vivapayments.com |
| Production | www.vivapayments.com | api.vivapayments.com |

## Carte de test

- **Numéro** : `4111111111111111`
- **CVV** : `111`
- **Expiration** : N'importe quelle date future
- **3DS password** : `Secret!33`

## Licence

MIT
