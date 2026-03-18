# Viva Wallet Merchant SDK for PHP

[![Version 1.3.5](https://img.shields.io/badge/version-1.3.5-blue.svg)](https://github.com/qrcommunication/sdk-php-viva-merchant/releases)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--merchant--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-merchant-sdk)

SDK PHP complet pour l'API Viva Wallet marchand. 9 ressources couvrant : ordres Smart Checkout, transactions, remboursements, paiements recurrents, Apple Pay / Google Pay natif, portefeuilles, virements SEPA, comptes bancaires, reporting MT940, abonnements webhook, sources de paiement et webhooks.

> **Ce SDK couvre les operations marchands standard.** Pour les operations ISV (comptes connectes, composite auth), voir [`sdk-php-viva-isv`](https://github.com/qrcommunication/sdk-php-viva-isv).

---

## Table des matieres

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Reference API complete](#reference-api-complete)
  - [1. Orders (Smart Checkout)](#1-orders--smart-checkout)
  - [2. Transactions](#2-transactions)
  - [3. Sources de paiement](#3-sources-de-paiement)
  - [4. Wallets (Portefeuilles)](#4-wallets--portefeuilles)
  - [5. BankAccounts (Comptes bancaires / SEPA)](#5-bankaccounts--comptes-bancaires--sepa)
  - [6. NativeCheckout (Apple Pay / Google Pay)](#6-nativecheckout--apple-pay--google-pay)
  - [7. DataServices (MT940 / Reporting)](#7-dataservices--mt940--reporting)
  - [8. Account (Compte marchand)](#8-account--compte-marchand)
  - [9. Webhooks](#9-webhooks)
- [Architecture](#architecture)
- [Les deux APIs Viva Wallet](#les-deux-apis-viva-wallet)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Enums](#enums)
- [Evenements Webhook (21 types)](#evenements-webhook-21-types)
- [Apple Pay / Google Pay (Native Pay)](#apple-pay--google-pay-native-pay)
- [Virements SEPA (Bank Transfers)](#virements-sepa-bank-transfers)
- [Data Services / MT940](#data-services--mt940)
- [Test en sandbox](#test-en-sandbox)
- [Documentation API interactive](#documentation-api-interactive)
- [Integration AI](#integration-ai-claude-cursor-copilot-codex)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-merchant-sdk
```

**Prerequis** : PHP 8.2+ avec `ext-json` et `ext-curl`.

---

## Quick Start

```php
use QrCommunication\VivaMerchant\VivaClient;

// 1. Instancier le client
$viva = new VivaClient(
    merchantId:   'your-merchant-uuid',          // Basic Auth username
    apiKey:       'your-api-key',                // Basic Auth password
    clientId:     'your-client-id.apps.vivapayments.com',  // OAuth2
    clientSecret: 'your-client-secret',          // OAuth2
    environment:  'demo',                        // 'demo' ou 'production'
);

// 2. Creer un ordre de paiement (montant en centimes)
$order = $viva->orders->create(
    amount: 1500,                                // 15,00 EUR
    customerDescription: 'Consultation',
);
// => ['order_code' => 1234567890, 'checkout_url' => 'https://...']

// 3. Rediriger le client vers le checkout
header('Location: ' . $order['checkout_url']);

// 4. Apres paiement, verifier la transaction
$txn = $viva->transactions->getV2('transaction-uuid');

// 5. Rembourser si necessaire
$viva->transactions->cancel('transaction-uuid', amount: 500); // 5,00 EUR

// 6. Paiement Apple Pay natif
$token = $viva->nativeCheckout->createChargeToken(1500, $applePayData, 'applepay');
$txn = $viva->nativeCheckout->createTransaction($token['chargeToken'], 1500);

// 7. Rapport MT940
$report = $viva->dataServices->mt940('2026-03-18');
```

### Ou trouver les credentials

| Credential    | Emplacement dans le Dashboard Viva                        |
|---------------|-----------------------------------------------------------|
| Merchant ID   | Settings > API Access > Merchant ID                       |
| API Key       | Settings > API Access > API Key                           |
| Client ID     | Settings > API Access > OAuth Credentials > Client ID     |
| Client Secret | Settings > API Access > OAuth Credentials > Client Secret |

### Test de connexion

```php
if ($viva->testConnection()) {
    echo 'Connexion OK';
}
```

---

## Reference API complete

### 1. Orders -- Smart Checkout

Creez des ordres de paiement et redirigez les clients vers le checkout Viva Wallet.
**API Legacy** -- Basic Auth -- PascalCase.

| Methode | Endpoint | Description |
|---------|----------|-------------|
| `create()` | `POST /api/orders` | Creer un ordre de paiement |
| `get()` | `GET /api/orders/{orderCode}` | Recuperer le statut d'un ordre |
| `cancel()` | `DELETE /api/orders/{orderCode}` | Annuler un ordre non paye |
| `checkoutUrl()` | -- | Generer l'URL de checkout |

```php
// Creer un ordre
$order = $viva->orders->create(
    amount: 1500,                        // EUR 15.00 (centimes)
    customerDescription: 'Consultation', // affiche au client
    merchantReference: 'session_123',    // reference interne
    sourceCode: '1234',                  // source de paiement
    allowRecurring: true,                // tokeniser la carte
    preauth: false,                      // pre-autorisation
    maxInstallments: 3,                  // paiement en 3 fois max
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

---

### 2. Transactions

Consultation, remboursement, capture de pre-autorisation et paiements recurrents.
**API Legacy** (Basic Auth) sauf `getV2()` qui utilise l'**API New** (Bearer OAuth2).

| Methode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| `get()` | `GET /api/transactions/{id}` | Basic | Details complets (PascalCase) |
| `getV2()` | `GET /checkout/v2/transactions/{id}` | Bearer | Details legers (camelCase) |
| `listByDate()` | `GET /api/transactions?date=` | Basic | Lister par date |
| `cancel()` | `DELETE /api/transactions/{id}` | Basic | Annuler / rembourser |
| `capture()` | `POST /api/transactions/{id}` | Basic | Capturer un preauth |
| `recurring()` | `POST /api/transactions/{id}` | Basic | Paiement recurrent |

```php
// Details d'une transaction (Legacy -- reponse complete PascalCase)
$txn = $viva->transactions->get('transaction-uuid');

// Details d'une transaction (New API -- reponse legere camelCase)
// Recommande par Viva pour verifier les paiements Smart Checkout
$txn = $viva->transactions->getV2('transaction-uuid');

// Lister les transactions du jour
$transactions = $viva->transactions->listByDate('2026-03-18');

// Remboursement total
$refund = $viva->transactions->cancel('transaction-uuid');

// Remboursement partiel (EUR 5.00)
$refund = $viva->transactions->cancel('transaction-uuid', amount: 500);

// Remboursement avec source
$refund = $viva->transactions->cancel('transaction-uuid', amount: 500, sourceCode: '1234');

// Capturer une pre-autorisation
$viva->transactions->capture('preauth-uuid', amount: 1500);

// Paiement recurrent (utilise le token de la transaction initiale)
$viva->transactions->recurring('initial-txn-uuid', amount: 1500);
$viva->transactions->recurring('initial-txn-uuid', amount: 1500, sourceCode: '1234');
```

> **Note** : `cancel()` le meme jour = annulation (void). `cancel()` le jour suivant = remboursement (refund).

---

### 3. Sources de paiement

Gestion des sources de paiement (payment sources) pour configurer les redirections checkout.
**API Legacy** -- Basic Auth -- PascalCase.

| Methode | Endpoint | Description |
|---------|----------|-------------|
| `list()` | `GET /api/sources` | Lister les sources |
| `create()` | `POST /api/sources` | Creer une source |

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

---

### 4. Wallets -- Portefeuilles

Soldes, transferts entre wallets, gestion avancee via Account API.
Melange d'**API Legacy** (transferts) et **API New** (liste, creation, transactions).

| Methode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| `list()` | `GET /api/wallets` | Bearer | Liste des portefeuilles avec soldes |
| `balance()` | -- | Bearer | Solde agrege (available, pending, reserved) |
| `transfer()` | `POST /api/wallets/transfer` | Basic | Transfert entre wallets |
| `listDetailed()` | `GET /walletaccounts/v1/wallets` | Bearer | Liste enrichie (IBAN, SWIFT) |
| `create()` | `POST /walletaccounts/v1/wallets` | Bearer | Creer un sous-compte |
| `update()` | `POST /walletaccounts/v1/wallets/{id}` | Bearer | Renommer un wallet |
| `searchTransactions()` | `GET /walletaccounts/v1/transactions` | Bearer | Rechercher les transactions compte |
| `getTransaction()` | `GET /walletaccounts/v1/transactions/{id}` | Bearer | Details transaction compte |

```php
// Liste des portefeuilles
$wallets = $viva->wallets->list();

// Solde agrege
$balance = $viva->wallets->balance();
// => ['available' => 1500.50, 'pending' => 200.00, 'reserved' => 0.00, 'currency' => 'EUR']

// Transfert entre portefeuilles (API Legacy)
$viva->wallets->transfer(
    amount: 5000,                        // 50,00 EUR
    sourceWalletId: 'wallet-uuid-source',
    targetWalletId: 'wallet-uuid-target',
    description: 'Transfert mensuel',
);

// Liste detaillee via Account API (IBAN, friendlyName)
$detailed = $viva->wallets->listDetailed();

// Creer un sous-compte
$viva->wallets->create(friendlyName: 'Epargne', currencyCode: 'EUR');

// Renommer un portefeuille
$viva->wallets->update(walletId: 12345, friendlyName: 'Nouveau nom');

// Rechercher les transactions compte
$txns = $viva->wallets->searchTransactions([
    'date_from' => '2026-03-01',
    'date_to'   => '2026-03-18',
    'walletId'  => 12345,
]);

// Details d'une transaction compte
$txn = $viva->wallets->getTransaction('transaction-uuid');
```

---

### 5. BankAccounts -- Comptes bancaires / SEPA

Lier des IBAN, consulter les options de transfert et executer des virements SEPA.
**API New** -- Bearer OAuth2 -- camelCase.

| Methode | Endpoint | Description |
|---------|----------|-------------|
| `link()` | `POST /banktransfers/v1/bankaccounts` | Lier un IBAN |
| `list()` | `GET /banktransfers/v1/bankaccounts` | Lister les comptes lies |
| `get()` | `GET /banktransfers/v1/bankaccounts/{id}` | Details d'un compte |
| `transferOptions()` | `GET /banktransfers/v1/bankaccounts/{id}/instructiontypes` | Options de transfert |
| `feeCommand()` | `POST /banktransfers/v1/bankaccounts/{id}/fees` | Calculer les frais |
| `send()` | `POST /banktransfers/v1/bankaccounts/{id}:send` | Executer un virement SEPA |

```php
// Lier un compte bancaire (validation IBAN automatique)
$result = $viva->bankAccounts->link(
    iban: 'FR7630006000011234567890189',
    beneficiaryName: 'Jean Dupont',
    friendlyName: 'Compte principal',
);
// => ['bankAccountId' => 'ba-uuid-123', 'isVivaIban' => false]

// Lister les comptes bancaires lies
$accounts = $viva->bankAccounts->list();

// Recuperer un compte specifique
$account = $viva->bankAccounts->get('ba-uuid-123');

// Consulter les options de transfert (SEPA standard, instant, SHA, OUR)
$options = $viva->bankAccounts->transferOptions('ba-uuid-123');

// Calculer les frais avant de transferer
$fees = $viva->bankAccounts->feeCommand(
    bankAccountId: 'ba-uuid-123',
    amount: 50000,                       // 500,00 EUR
    walletId: 'wallet-uuid',
    isInstant: false,
    instructionType: 'SHA',
);
// => ['bankCommandId' => 'cmd-uuid', 'fee' => 150]

// Executer le virement SEPA
$transfer = $viva->bankAccounts->send(
    bankAccountId: 'ba-uuid-123',
    amount: 50000,
    walletId: 'wallet-uuid',
    bankCommandId: $fees['bankCommandId'],  // optionnel
    description: 'Virement mensuel',
);
// => ['commandId' => 'cmd-uuid', 'isInstant' => false, 'fee' => 150]
```

---

### 6. NativeCheckout -- Apple Pay / Google Pay

Paiements natifs Apple Pay et Google Pay en deux etapes : generation d'un charge token puis execution de la transaction.
**API New** -- Bearer OAuth2 -- camelCase.

| Methode | Endpoint | Description |
|---------|----------|-------------|
| `createChargeToken()` | `POST /nativecheckout/v2/chargetokens` | Generer un token depuis les donnees Apple/Google Pay |
| `createTransaction()` | `POST /nativecheckout/v2/transactions` | Executer la transaction avec le charge token |

```php
// Etape 1 : Generer un charge token a partir des donnees Apple Pay
$token = $viva->nativeCheckout->createChargeToken(
    amount: 1500,                        // 15,00 EUR
    paymentData: $applePayEncryptedData, // donnees chiffrees du wallet
    paymentMethod: 'applepay',           // 'applepay' ou 'googlepay'
    sourceCode: '1234',                  // optionnel
);
// => ['chargeToken' => 'ctok_abc123', 'redirectToACSForm' => null]

// Etape 2 : Executer la transaction
$txn = $viva->nativeCheckout->createTransaction(
    chargeToken: $token['chargeToken'],
    amount: 1500,
    currencyCode: 978,                   // EUR (defaut)
    merchantTrns: 'order_456',           // reference interne
    customerTrns: 'Achat en ligne',      // description client
    preauth: false,                      // pre-autorisation
    tipAmount: 0,                        // pourboire
);
// => ['transactionId' => 'uuid', 'statusId' => 'F', 'amount' => 1500, 'orderCode' => 123]

// Google Pay
$token = $viva->nativeCheckout->createChargeToken(
    amount: 2000,
    paymentData: $googlePayData,
    paymentMethod: 'googlepay',
);
$txn = $viva->nativeCheckout->createTransaction($token['chargeToken'], 2000);
```

#### Methodes de paiement

| Methode | `paymentMethod` | `paymentMethodId` |
|---------|-----------------|-------------------|
| Apple Pay | `'applepay'` | 10 |
| Google Pay | `'googlepay'` | 11 |

---

### 7. DataServices -- MT940 / Reporting

Rapports MT940, abonnements webhook pour les fichiers de transactions, et generation de fichiers a la demande.
**API New** -- Bearer OAuth2 -- camelCase.

| Methode | Endpoint | Description |
|---------|----------|-------------|
| `mt940()` | `GET /dataservices/v1/mt940?date=` | Releve MT940 pour une date |
| `createSubscription()` | `POST /dataservices/v1/webhooks/subscriptions` | Creer un abonnement webhook |
| `updateSubscription()` | `PUT /dataservices/v1/webhooks/subscriptions/{id}` | Modifier un abonnement |
| `deleteSubscription()` | `DELETE /dataservices/v1/webhooks/subscriptions/{id}` | Supprimer un abonnement |
| `listSubscriptions()` | `GET /dataservices/v1/webhooks/subscriptions/` | Lister les abonnements |
| `requestFile()` | `POST /dataservices/v1/file-request` | Demander la generation d'un fichier |

```php
// Recuperer le releve MT940 du jour
$report = $viva->dataServices->mt940('2026-03-18');

// Creer un abonnement webhook pour les fichiers de transactions
$sub = $viva->dataServices->createSubscription(
    url: 'https://example.com/webhooks/viva-files',
    eventType: 'SaleTransactionsFileGenerated',
);
// => ['subscriptionId' => 'sub-uuid', 'url' => '...', 'eventType' => '...']

// Modifier un abonnement
$viva->dataServices->updateSubscription(
    subscriptionId: 'sub-uuid',
    url: 'https://example.com/webhooks/new-url',
);

// Lister tous les abonnements
$subscriptions = $viva->dataServices->listSubscriptions();

// Supprimer un abonnement
$viva->dataServices->deleteSubscription('sub-uuid');

// Demander la generation d'un fichier de transactions
$viva->dataServices->requestFile('2026-03-18');
```

---

### 8. Account -- Compte marchand

Informations du compte et soldes.
**API New** -- Bearer OAuth2.

| Methode | Endpoint | Description |
|---------|----------|-------------|
| `info()` | `GET /api/accounts/{merchantId}` | Informations du compte |
| `wallets()` | `GET /api/wallets` | Portefeuilles du compte |

```php
// Informations du compte marchand
$info = $viva->account->info();
// => ['merchantId' => '...', 'businessName' => '...', 'email' => '...', ...]

// Portefeuilles du compte
$wallets = $viva->account->wallets();
```

---

### 9. Webhooks

Verification et parsing des webhooks Viva Wallet. Pas d'authentification cote SDK.

| Methode | Description |
|---------|-------------|
| `verificationResponse()` | Generer la reponse au GET de verification |
| `parse()` | Parser un evenement POST |
| `isKnownEvent()` | Verifier si un EventTypeId est connu |
| `eventTypeIds()` | Liste des EventTypeId supportes |
| `Webhooks::EVENTS` | Constante avec les 21 types d'evenements |

```php
// 1. Verification du webhook (repondre au GET de Viva)
$response = $viva->webhooks->verificationResponse('your-verification-key');
return response()->json($response); // Laravel
// => {"StatusCode": 0, "Key": "your-verification-key"}

// 2. Parser un evenement webhook (POST)
$event = $viva->webhooks->parse($request->getContent());
// => ['event_type' => 'transaction.payment.created', 'event_type_id' => 1796, 'event_data' => [...]]

match ($event['event_type']) {
    'transaction.payment.created'   => handlePayment($event['event_data']),
    'transaction.refund.created'    => handleRefund($event['event_data']),
    'transaction.payment.cancelled' => handleCancellation($event['event_data']),
    'transaction.preauth.created'   => handlePreauth($event['event_data']),
    'transaction.preauth.completed' => handleCapture($event['event_data']),
    'transaction.failed'            => handleFailure($event['event_data']),
    'account.connected'             => handleAccountConnected($event['event_data']),
    'transfer.created'              => handleTransfer($event['event_data']),
    'sale.transactions.file'        => handleFileReady($event['event_data']),
    default => null,
};

// 3. Verifier si un evenement est connu
$viva->webhooks->isKnownEvent(1796); // true
$viva->webhooks->isKnownEvent(9999); // false

// 4. Acceder a la constante EVENTS
use QrCommunication\VivaMerchant\Resources\Webhooks;
$allEvents = Webhooks::EVENTS;
// => [1796 => 'transaction.payment.created', 1797 => 'transaction.refund.created', ...]
```

---

## Architecture

```
VivaClient (point d'entree)
|-- orders          -> Orders          (Legacy API, Basic Auth)
|-- transactions    -> Transactions    (Legacy API, Basic Auth + New API)
|-- sources         -> Sources         (Legacy API, Basic Auth)
|-- wallets         -> Wallets         (Legacy + New API, Mixed Auth)
|-- bankAccounts    -> BankAccounts    (New API, Bearer OAuth2)
|-- nativeCheckout  -> NativeCheckout  (New API, Bearer OAuth2)
|-- dataServices    -> DataServices    (New API, Bearer OAuth2)
|-- webhooks        -> Webhooks        (pas d'auth -- parsing local)
|-- account         -> Account         (New API, Bearer OAuth2)
```

### Structure du code

```
src/
|-- VivaClient.php              # Point d'entree principal (9 ressources)
|-- Config.php                  # Configuration (credentials, URLs)
|-- HttpClient.php              # Client HTTP (Guzzle, OAuth2, Basic Auth)
|-- Enums/
|   |-- Environment.php         # DEMO / PRODUCTION
|   |-- Currency.php            # 12 devises ISO 4217
|   |-- TransactionStatus.php   # 7 statuts de transaction
|-- Exceptions/
|   |-- VivaException.php       # Exception de base
|   |-- ApiException.php        # Erreurs HTTP generales
|   |-- AuthenticationException.php  # OAuth2 / 401
|   |-- ValidationException.php      # Validation / 422
|-- Resources/
    |-- Orders.php              # Legacy API -- Smart Checkout
    |-- Transactions.php        # Legacy + New API -- transactions
    |-- Sources.php             # Legacy API -- sources de paiement
    |-- Wallets.php             # Mixed -- portefeuilles + Account API
    |-- BankAccounts.php        # New API -- IBAN + virements SEPA
    |-- NativeCheckout.php      # New API -- Apple Pay / Google Pay
    |-- DataServices.php        # New API -- MT940, webhooks data, fichiers
    |-- Account.php             # New API -- info compte
    |-- Webhooks.php            # Verification + parsing webhooks (21 types)
```

---

## Les deux APIs Viva Wallet

Viva Wallet expose **deux APIs distinctes** avec des conventions et authentifications differentes.
Le SDK gere automatiquement le routage -- vous n'avez pas a vous en preoccuper.

### API Legacy (Basic Auth)

| Propriete | Valeur |
|-----------|--------|
| **Host production** | `www.vivapayments.com` |
| **Host demo** | `demo.vivapayments.com` |
| **Auth** | Basic Auth (MerchantID:APIKey) |
| **Convention** | PascalCase (`Amount`, `SourceCode`, `IsPreAuth`) |
| **Resources** | Orders, Transactions (sauf getV2), Sources, Wallets (transfer) |

### API New (Bearer OAuth2)

| Propriete | Valeur |
|-----------|--------|
| **Host production** | `api.vivapayments.com` |
| **Host demo** | `demo-api.vivapayments.com` |
| **Auth** | Bearer token OAuth2 (auto-refresh) |
| **Convention** | camelCase (`amount`, `sourceCode`, `isPreAuth`) |
| **Resources** | Transactions (getV2), Wallets (list, detailed, create, update, transactions), BankAccounts, NativeCheckout, DataServices, Account |

### Authentification automatique

Le SDK gere l'authentification de maniere transparente :
- Les tokens OAuth2 sont caches en memoire et renouveles 60 secondes avant expiration
- Les credentials Basic Auth sont envoyes avec chaque requete Legacy API
- Aucune gestion manuelle de tokens necessaire

```php
// Forcer le renouvellement du token OAuth2
$viva->invalidateToken();
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
    echo "Auth echouee : {$e->getMessage()}";

} catch (ValidationException $e) {
    // Donnees invalides (HTTP 422)
    echo "Validation : " . json_encode($e->errors);

} catch (ApiException $e) {
    // Erreur API Viva (HTTP 4xx/5xx)
    echo "Erreur API [{$e->httpStatus}] : {$e->getMessage()}";
    echo "Code erreur : {$e->getErrorCode()}";
    echo "Texte erreur : {$e->getErrorText()}";
    echo "Reponse brute : " . json_encode($e->responseBody);
}
```

### Hierarchie des exceptions

```
RuntimeException
  |-- VivaException
        |-- ApiException             (erreurs HTTP generales -- 4xx/5xx)
        |-- AuthenticationException  (OAuth2 / 401)
        |-- ValidationException      (validation / 422 -- avec $e->errors)
```

Toutes les exceptions exposent : `$e->httpStatus`, `$e->responseBody`, `$e->getErrorCode()`, `$e->getErrorText()`.

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

| Code | Label           | `isSuccessful()` | `isPending()` | `isFailed()` |
|------|-----------------|:-----------------:|:--------------:|:-------------:|
| `F`  | Finalized       | true              | false          | false         |
| `A`  | Pending         | false             | true           | false         |
| `C`  | Clearing        | false             | true           | false         |
| `E`  | Error           | false             | false          | true          |
| `M`  | Reversed        | false             | false          | true          |
| `X`  | Requires Action | false             | false          | false         |
| `R`  | Refunded        | false             | false          | false         |

### Currency

```php
use QrCommunication\VivaMerchant\Enums\Currency;

$eur = Currency::EUR;
$eur->value;  // 978 (ISO 4217)
$eur->iso();  // 'EUR'

$usd = Currency::fromIso('USD');
$usd->value;  // 840
```

Devises supportees : `EUR` (978), `GBP` (826), `USD` (840), `PLN` (985), `RON` (946), `BGN` (975), `CZK` (203), `HRK` (191), `HUF` (348), `DKK` (208), `SEK` (752), `NOK` (578).

### Environment

```php
use QrCommunication\VivaMerchant\Enums\Environment;

$env = Environment::DEMO;
$env->apiUrl();      // https://demo-api.vivapayments.com
$env->legacyUrl();   // https://demo.vivapayments.com
$env->accountsUrl(); // https://demo-accounts.vivapayments.com
$env->checkoutUrl(); // https://demo.vivapayments.com/web/checkout
```

| Environnement | Dashboard              | API Legacy              | API New                      | Accounts                         |
|---------------|------------------------|-------------------------|------------------------------|----------------------------------|
| Demo          | demo.vivapayments.com  | demo.vivapayments.com   | demo-api.vivapayments.com    | demo-accounts.vivapayments.com   |
| Production    | www.vivapayments.com   | www.vivapayments.com    | api.vivapayments.com         | accounts.vivapayments.com        |

```php
// Instanciation via string
$viva = new VivaClient(..., environment: 'demo');

// Instanciation via enum
$viva = new VivaClient(..., environment: Environment::PRODUCTION);
```

---

## Evenements Webhook (21 types)

Le SDK reconnait les 21 types d'evenements webhook Viva Wallet.

| EventTypeId | `event_type`                          | Description                           |
|-------------|---------------------------------------|---------------------------------------|
| 1796        | `transaction.payment.created`         | Paiement cree                         |
| 1797        | `transaction.refund.created`          | Remboursement cree                    |
| 1798        | `transaction.payment.cancelled`       | Paiement annule                       |
| 1799        | `transaction.reversal.created`        | Annulation creee                      |
| 1800        | `transaction.preauth.created`         | Pre-autorisation creee                |
| 1801        | `transaction.preauth.completed`       | Pre-autorisation finalisee            |
| 1802        | `transaction.preauth.cancelled`       | Pre-autorisation annulee              |
| 1810        | `pos.session.created`                 | Session POS creee                     |
| 1811        | `pos.session.failed`                  | Session POS echouee                   |
| 1812        | `transaction.price.calculated`        | Prix calcule                          |
| 1813        | `transaction.failed`                  | Transaction echouee                   |
| 1819        | `account.connected`                   | Compte connecte (ISV)                 |
| 1820        | `account.verification.status.changed` | Statut de verification modifie        |
| 1821        | `account.transaction.created`         | Transaction de compte creee           |
| 1822        | `command.bank.transfer.created`       | Virement bancaire cree                |
| 1823        | `command.bank.transfer.executed`      | Virement bancaire execute             |
| 1824        | `transfer.created`                    | Transfert cree                        |
| 1825        | `obligation.created`                  | Obligation creee                      |
| 1826        | `obligation.captured`                 | Obligation capturee                   |
| 1827        | `order.updated`                       | Ordre mis a jour                      |
| 1828        | `sale.transactions.file`              | Fichier de transactions de vente pret |

Les evenements inconnus sont resolus en `unknown.{eventTypeId}`.

---

## Apple Pay / Google Pay (Native Pay)

Le flux Native Checkout se deroule en 2 etapes :

```
1. Client choisit Apple Pay / Google Pay dans votre app
2. Le wallet mobile fournit les donnees de paiement chiffrees
3. SDK : createChargeToken() -> token a usage unique
4. SDK : createTransaction() -> transaction finalisee
```

```php
// Apple Pay
$token = $viva->nativeCheckout->createChargeToken(
    amount: 1500,
    paymentData: $applePayEncryptedData,
    paymentMethod: 'applepay',
);

$txn = $viva->nativeCheckout->createTransaction(
    chargeToken: $token['chargeToken'],
    amount: 1500,
    merchantTrns: 'order_789',
);

// Google Pay
$token = $viva->nativeCheckout->createChargeToken(
    amount: 2000,
    paymentData: $googlePayData,
    paymentMethod: 'googlepay',
);

$txn = $viva->nativeCheckout->createTransaction(
    chargeToken: $token['chargeToken'],
    amount: 2000,
);
```

### Pre-autorisation native

```php
$txn = $viva->nativeCheckout->createTransaction(
    chargeToken: $token['chargeToken'],
    amount: 1500,
    preauth: true,
);

// Capturer plus tard via Transactions
$viva->transactions->capture($txn['transactionId'], amount: 1500);
```

---

## Virements SEPA (Bank Transfers)

Workflow complet pour un virement SEPA :

```php
// 1. Lier un IBAN
$account = $viva->bankAccounts->link(
    iban: 'FR7630006000011234567890189',
    beneficiaryName: 'Jean Dupont',
);

// 2. Verifier les options de transfert
$options = $viva->bankAccounts->transferOptions($account['bankAccountId']);

// 3. Calculer les frais
$fees = $viva->bankAccounts->feeCommand(
    bankAccountId: $account['bankAccountId'],
    amount: 50000,
    walletId: 'wallet-uuid',
    isInstant: false,
    instructionType: 'SHA',
);

// 4. Executer le virement
$transfer = $viva->bankAccounts->send(
    bankAccountId: $account['bankAccountId'],
    amount: 50000,
    walletId: 'wallet-uuid',
    bankCommandId: $fees['bankCommandId'],
    description: 'Virement fournisseur',
);
```

---

## Data Services / MT940

### MT940 (releve bancaire)

```php
$report = $viva->dataServices->mt940('2026-03-18');
```

### Abonnements webhook pour fichiers de transactions

```php
// S'abonner pour recevoir les fichiers generes
$sub = $viva->dataServices->createSubscription(
    url: 'https://example.com/webhooks/viva-files',
    eventType: 'SaleTransactionsFileGenerated',
);

// Demander la generation d'un fichier
$viva->dataServices->requestFile('2026-03-18');

// Lister les abonnements
$subs = $viva->dataServices->listSubscriptions();

// Mettre a jour
$viva->dataServices->updateSubscription('sub-uuid', 'https://new-url.com/hook');

// Supprimer
$viva->dataServices->deleteSubscription('sub-uuid');
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

### Montants de declin (test)

| Montant (centimes) | Resultat           |
|--------------------|--------------------|
| 9920               | Stolen card        |
| 9951               | Insufficient funds |
| 9954               | Expired card       |
| 9957               | Not permitted      |

### Workflow de test complet

```php
// 1. Creer un ordre
$order = $viva->orders->create(amount: 1500, customerDescription: 'Test');
echo "Checkout : {$order['checkout_url']}\n";

// 2. Payer manuellement dans le navigateur avec la carte de test

// 3. Verifier la transaction via New API
$txn = $viva->transactions->getV2('transaction-uuid-from-callback');

// 4. Lister les transactions du jour
$txns = $viva->transactions->listByDate(date('Y-m-d'));
foreach ($txns as $txn) {
    echo "{$txn['TransactionId']} -- {$txn['Amount']} EUR\n";
}

// 5. Rembourser
$viva->transactions->cancel($txns[0]['TransactionId']);
```

---

## Documentation API interactive

La documentation complete de l'API est disponible au format OpenAPI 3.1 :

- **Documentation interactive (ReDoc)** : [qrcommunication.github.io/sdk-php-viva-merchant](https://qrcommunication.github.io/sdk-php-viva-merchant/)
- **Specification OpenAPI** : [`docs/openapi.yaml`](docs/openapi.yaml)

---

## Integration AI (Claude, Cursor, Copilot, Codex)

Ce SDK inclut des fichiers d'instructions automatiquement detectes par les assistants AI :

| Outil | Fichier | Detection |
|-------|---------|-----------|
| **Claude Code** | [`CLAUDE.md`](CLAUDE.md) | Automatique |
| **Cursor** | [`.cursorrules`](.cursorrules) | Automatique |
| **GitHub Copilot** | [`.github/copilot-instructions.md`](.github/copilot-instructions.md) | Automatique |
| **OpenAI Codex** | [`AGENTS.md`](AGENTS.md) | Automatique |
| **Gemini** | [`CLAUDE.md`](CLAUDE.md) | Manuel (copier dans le contexte) |

Ces fichiers fournissent a l'assistant AI :
- L'architecture du SDK et le pattern Resource (9 ressources)
- Le routing entre Legacy API (Basic Auth) et New API (Bearer)
- Les conventions (PascalCase vs camelCase, montants en centimes)
- Les pieges Viva Wallet a eviter
- Des exemples de code complets

---

## Licence

MIT -- voir le fichier [LICENSE](LICENSE) pour les details.

---

<p align="center">
  Developpe par <a href="https://qrcommunication.com"><strong>QrCommunication</strong></a>
</p>
