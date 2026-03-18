# Viva Wallet Merchant SDK for PHP

[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--merchant--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-merchant-sdk)

SDK PHP pour l'API Viva Wallet. Gestion des paiements marchands : ordres, transactions, remboursements, sources, webhooks et compte.

> **Ce SDK couvre les operations marchands standard.** Pour les operations ISV (comptes connectes, composite auth), voir `sdk-php-viva-isv`.

---

## Table des matieres

- [Installation](#installation)
- [Configuration](#configuration)
- [Utilisation](#utilisation)
  - [Ordres de paiement (Smart Checkout)](#ordres-de-paiement-smart-checkout)
  - [Transactions](#transactions)
  - [Sources de paiement](#sources-de-paiement)
  - [Webhooks](#webhooks)
  - [Compte](#compte)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Enums](#enums)
- [Architecture](#architecture)
- [Environnements](#environnements)
- [Test en sandbox](#test-en-sandbox)
- [Documentation API](#documentation-api)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-merchant-sdk
```

**Prerequis :** PHP 8.2+ avec `ext-json` et `ext-curl`.

---

## Configuration

```php
use QrCommunication\VivaMerchant\VivaClient;

$viva = new VivaClient(
    merchantId:   'your-merchant-uuid',
    apiKey:       'your-api-key',
    clientId:     'your-client-id.apps.vivapayments.com',
    clientSecret: 'your-client-secret',
    environment:  'demo', // 'demo' ou 'production'
);
```

### Ou trouver les credentials

| Credential    | Emplacement dans le Dashboard Viva                          |
|---------------|-------------------------------------------------------------|
| Merchant ID   | Settings > API Access > Merchant ID                         |
| API Key       | Settings > API Access > API Key                             |
| Client ID     | Settings > API Access > OAuth Credentials > Client ID       |
| Client Secret | Settings > API Access > OAuth Credentials > Client Secret   |

### Test de connexion

```php
if ($viva->testConnection()) {
    echo 'Connexion OK';
}
```

---

## Utilisation

### Ordres de paiement (Smart Checkout)

Creez un ordre de paiement puis redirigez le client vers la page de checkout Viva Wallet.

```php
// Creer un ordre (montant en centimes)
$order = $viva->orders->create(
    amount: 1500,                           // EUR 15.00
    customerDescription: 'Consultation',     // affiche au client
    merchantReference: 'session_123',        // reference interne
    allowRecurring: true,                    // tokeniser la carte
    preauth: false,                         // pre-autorisation
    maxInstallments: 3,                     // paiement en 3 fois max
);

echo $order['order_code'];    // 1234567890
echo $order['checkout_url'];  // https://demo.vivapayments.com/web/checkout?ref=1234567890

// Recuperer le statut d'un ordre
$status = $viva->orders->get(1234567890);

// Annuler un ordre non paye
$viva->orders->cancel(1234567890);

// Generer l'URL de checkout pour un ordre existant
$url = $viva->orders->checkoutUrl(1234567890);
```

### Transactions

```php
// Details d'une transaction
$txn = $viva->transactions->get('transaction-uuid');

// Lister les transactions du jour
$transactions = $viva->transactions->listByDate('2026-03-18');

// Remboursement total
$refund = $viva->transactions->cancel('transaction-uuid');

// Remboursement partiel (EUR 5.00)
$refund = $viva->transactions->cancel('transaction-uuid', amount: 500);

// Capturer une pre-autorisation
$viva->transactions->capture('preauth-uuid', amount: 1500);

// Paiement recurrent (utilise le token de la transaction initiale)
$viva->transactions->recurring('initial-txn-uuid', amount: 1500);
```

> **Note :** Un `cancel` le meme jour = annulation (void). Un `cancel` sur un jour precedent = remboursement (refund).

### Sources de paiement

```php
// Lister les sources configurees
$sources = $viva->sources->list();

// Creer une source de paiement
$viva->sources->create(
    name: 'Mon site web',
    sourceCode: '1234',
    domain: 'example.com',
    pathSuccess: '/payment/success',
    pathFail: '/payment/failed',
);
```

### Webhooks

Viva Wallet envoie un GET pour verifier l'URL, puis des POST avec les evenements de transaction.

```php
// 1. Verification du webhook (repondre au GET de Viva)
$response = $viva->webhooks->verificationResponse('your-verification-key');
return response()->json($response); // Laravel

// 2. Parser un evenement webhook (POST)
$event = $viva->webhooks->parse($request->getContent());

match ($event['event_type']) {
    'transaction.payment.created'  => handlePayment($event['event_data']),
    'transaction.refund.created'   => handleRefund($event['event_data']),
    'transaction.payment.cancelled' => handleCancellation($event['event_data']),
    'transaction.preauth.created'  => handlePreauth($event['event_data']),
    default => null,
};
```

#### Evenements supportes

| EventTypeId | event_type                       | Description                  |
|-------------|----------------------------------|------------------------------|
| 1796        | `transaction.payment.created`    | Paiement cree                |
| 1797        | `transaction.refund.created`     | Remboursement cree           |
| 1798        | `transaction.payment.cancelled`  | Paiement annule              |
| 1799        | `transaction.reversal.created`   | Annulation creee             |
| 1800        | `transaction.preauth.created`    | Pre-autorisation creee       |
| 1801        | `transaction.preauth.completed`  | Pre-autorisation finalisee   |
| 1802        | `transaction.preauth.cancelled`  | Pre-autorisation annulee     |
| 1810        | `pos.session.created`            | Session POS creee            |
| 1811        | `pos.session.failed`             | Session POS echouee          |

### Compte

```php
// Informations du compte marchand
$info = $viva->account->info();

// Solde des portefeuilles
$wallets = $viva->account->wallets();
```

---

## Gestion des erreurs

Le SDK lance des exceptions typees pour chaque type d'erreur.

```php
use QrCommunication\VivaMerchant\Exceptions\AuthenticationException;
use QrCommunication\VivaMerchant\Exceptions\ApiException;
use QrCommunication\VivaMerchant\Exceptions\ValidationException;

try {
    $order = $viva->orders->create(amount: 1500);
} catch (AuthenticationException $e) {
    // Credentials invalides (HTTP 401)
    echo "Auth failed: {$e->getMessage()}";
} catch (ValidationException $e) {
    // Donnees invalides (HTTP 422)
    echo "Validation: " . json_encode($e->errors);
} catch (ApiException $e) {
    // Erreur API Viva (HTTP 4xx/5xx)
    echo "API error [{$e->httpStatus}]: {$e->getMessage()}";
    echo "Error code: {$e->getErrorCode()}";
    echo "Error text: {$e->getErrorText()}";
    echo "Response: " . json_encode($e->responseBody);
}
```

### Hierarchie des exceptions

```
RuntimeException
  └── VivaException
        ├── ApiException           (erreurs HTTP generales)
        ├── AuthenticationException (OAuth2 / 401)
        └── ValidationException    (validation / 422)
```

---

## Enums

### TransactionStatus

```php
use QrCommunication\VivaMerchant\Enums\TransactionStatus;

$status = TransactionStatus::from('F');
$status->isSuccessful(); // true
$status->isPending();    // false
$status->isFailed();     // false
$status->label();        // 'Finalized'
```

| Code | Label            | `isSuccessful()` | `isPending()` | `isFailed()` |
|------|------------------|:-----------------:|:--------------:|:-------------:|
| `F`  | Finalized        | true              | false          | false         |
| `A`  | Pending          | false             | true           | false         |
| `C`  | Clearing         | false             | true           | false         |
| `E`  | Error            | false             | false          | true          |
| `M`  | Reversed         | false             | false          | true          |
| `X`  | Requires Action  | false             | false          | false         |
| `R`  | Refunded         | false             | false          | false         |

### Currency

```php
use QrCommunication\VivaMerchant\Enums\Currency;

$eur = Currency::EUR;
$eur->value;  // 978 (ISO 4217)
$eur->iso();  // 'EUR'

$usd = Currency::fromIso('USD');
$usd->value;  // 840
```

Devises supportees : `EUR`, `GBP`, `USD`, `PLN`, `RON`, `BGN`, `CZK`, `HRK`, `HUF`, `DKK`, `SEK`, `NOK`.

### Environment

```php
use QrCommunication\VivaMerchant\Enums\Environment;

$env = Environment::DEMO;
$env->apiUrl();      // https://demo-api.vivapayments.com
$env->legacyUrl();   // https://demo.vivapayments.com
$env->accountsUrl(); // https://demo-accounts.vivapayments.com
$env->checkoutUrl(); // https://demo.vivapayments.com/web/checkout
```

---

## Architecture

```
VivaClient (point d'entree)
├── orders       → Orders       (Legacy API, Basic Auth)
├── transactions → Transactions (Legacy API, Basic Auth)
├── sources      → Sources      (Legacy API, Basic Auth)
├── webhooks     → Webhooks     (pas d'auth — parsing/verification)
└── account      → Account      (New API, Bearer OAuth2)
```

### Hosts Viva Wallet

Le SDK communique avec 3 hosts distincts :

| Host                            | Auth                    | Usage dans le SDK                    |
|---------------------------------|-------------------------|--------------------------------------|
| `accounts.vivapayments.com`     | OAuth2 Client Creds     | Acquisition de token (interne)       |
| `api.vivapayments.com`          | Bearer token            | Account (info, wallets)              |
| `www.vivapayments.com`          | Basic Auth              | Orders, Transactions, Sources        |

> En mode `demo`, les hosts sont prefixes par `demo-` ou `demo.`.

### Authentification automatique

Le SDK gere l'authentification de maniere transparente :
- Les tokens OAuth2 sont caches en memoire et renouveles 60 secondes avant expiration.
- Les credentials Basic Auth sont envoyes avec chaque requete Legacy API.
- Aucune gestion manuelle de tokens necessaire.

```php
// Forcer le renouvellement du token OAuth2
$viva->invalidateToken();
```

### Structure du code

```
src/
├── VivaClient.php            # Point d'entree principal
├── Config.php                # Configuration (credentials, URLs)
├── HttpClient.php            # Client HTTP (Guzzle, OAuth2, Basic Auth)
├── Enums/
│   ├── Environment.php       # DEMO / PRODUCTION
│   ├── Currency.php          # 12 devises ISO 4217
│   └── TransactionStatus.php # 7 statuts de transaction
├── Exceptions/
│   ├── VivaException.php     # Exception de base
│   ├── ApiException.php      # Erreurs HTTP generales
│   ├── AuthenticationException.php  # OAuth2 / 401
│   └── ValidationException.php      # Validation / 422
└── Resources/
    ├── Account.php           # New API — info compte, wallets
    ├── Orders.php            # Legacy API — Smart Checkout
    ├── Transactions.php      # Legacy API — get, refund, capture, recurring
    ├── Sources.php           # Legacy API — gestion sources
    └── Webhooks.php          # Verification & parsing webhooks
```

---

## Environnements

| Env        | Dashboard                  | API                             |
|------------|----------------------------|---------------------------------|
| Demo       | demo.vivapayments.com      | demo-api.vivapayments.com       |
| Production | www.vivapayments.com       | api.vivapayments.com            |

```php
use QrCommunication\VivaMerchant\Enums\Environment;

// Via string
$viva = new VivaClient(..., environment: 'demo');

// Via enum
$viva = new VivaClient(..., environment: Environment::PRODUCTION);
```

---

## Test en sandbox

### Carte de test

| Champ        | Valeur                |
|--------------|-----------------------|
| Numero       | `4111 1111 1111 1111` |
| CVV          | `111`                 |
| Expiration   | N'importe quelle date future |
| 3DS password | `Secret!33`           |

### Workflow de test complet

```php
// 1. Creer un ordre
$order = $viva->orders->create(amount: 1500, customerDescription: 'Test');
echo "Checkout: {$order['checkout_url']}\n";

// 2. Payer manuellement dans le navigateur avec la carte de test

// 3. Verifier la transaction
$txns = $viva->transactions->listByDate(date('Y-m-d'));
foreach ($txns as $txn) {
    echo "{$txn['TransactionId']} — {$txn['Amount']}\n";
}

// 4. Rembourser
$viva->transactions->cancel($txns[0]['TransactionId']);
```

---

## Documentation API

La documentation complete de l'API est disponible au format OpenAPI 3.1 :

- **Documentation interactive (ReDoc)** : [qrcommunication.github.io/sdk-php-viva-merchant](https://qrcommunication.github.io/sdk-php-viva-merchant/)
- **Specification OpenAPI** : [`docs/openapi.yaml`](docs/openapi.yaml)

---

## Licence

MIT - voir le fichier [LICENSE](LICENSE) pour les details.

---

<p align="center">
  Developpe par <a href="https://qrcommunication.com"><strong>QrCommunication</strong></a>
</p>
