# Viva Wallet Merchant SDK — PHP

[![Version 1.4.0](https://img.shields.io/badge/version-1.4.0-blue.svg)](https://github.com/qrcommunication/sdk-php-viva-merchant/releases)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--merchant--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-merchant-sdk)

SDK PHP complet pour l'intégration Viva Wallet. Couvre **10 ressources** : Orders, Transactions, Sources, Wallets, BankAccounts, NativeCheckout, DataServices, Account, Webhooks et Messages (abonnements webhook).

**PHP 8.2+** requis. Compatible Laravel, Symfony, ou tout projet PHP.

> **Ce SDK couvre les opérations marchands standard.** Pour les opérations ISV (comptes connectés, composite auth), voir [`sdk-php-viva-isv`](https://github.com/qrcommunication/sdk-php-viva-isv).

---

## Table des matières

- [Installation](#installation)
- [Démarrage rapide](#démarrage-rapide)
- [Référence des ressources](#référence-des-ressources)
  - [1. Orders](#1-orders--vivaorders)
  - [2. Transactions](#2-transactions--vivatransactions)
  - [3. Sources](#3-sources--vivasources)
  - [4. Wallets](#4-wallets--vivawallets)
  - [5. BankAccounts](#5-bankaccounts--vivabankaccounts)
  - [6. NativeCheckout](#6-nativecheckout--vivanativecheckout)
  - [7. DataServices](#7-dataservices--vivadataservices)
  - [8. Webhooks](#8-webhooks--vivawebhooks)
  - [9. Account](#9-account--vivaaccount)
  - [10. Messages](#10-messages--vivamessages)
- [Enregistrement des webhooks banking](#enregistrement-des-webhooks-banking)
- [Architecture](#architecture)
- [Enums](#enums)
- [Gestion d'erreurs](#gestion-derreurs)
- [Webhooks — Guide d'intégration](#webhooks--guide-dintégration)
- [Carte de test](#carte-de-test)
- [Documentation interactive](#documentation-interactive)
- [Intégration IA](#intégration-ia)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-merchant-sdk
```

**Prérequis** : PHP 8.2+ avec `ext-json` et `ext-curl`.

---

## Démarrage rapide

```php
use QrCommunication\VivaMerchant\VivaClient;

$viva = new VivaClient(
    merchantId: 'votre-merchant-uuid',
    apiKey: 'votre-api-key',
    clientId: 'xxx.apps.vivapayments.com',
    clientSecret: 'votre-client-secret',
    environment: 'demo', // ou 'production'
);

// Créer un ordre de paiement
$order = $viva->orders->create(amount: 1500, customerDescription: 'Consultation');
// Rediriger le client vers $order['checkout_url']

// Vérifier une transaction après paiement
$txn = $viva->transactions->getV2('transaction-uuid');

// Rembourser
$viva->transactions->cancel('transaction-uuid', amount: 500);

// Capturer une pré-autorisation
$viva->transactions->capture('preauth-uuid', amount: 1500);

// Charge récurrente
$viva->transactions->recurring('initial-txn-uuid', amount: 1500);

// Apple Pay / Google Pay
$token = $viva->nativeCheckout->createChargeToken(1500, $applePayData);
$txn = $viva->nativeCheckout->createTransaction($token['chargeToken'], 1500);

// Tester la connexion
$viva->testConnection(); // true ou false
```

---

## Référence des ressources

### 1. Orders — `$viva->orders`

Ordres de paiement Smart Checkout.

#### `create()` — Créer un ordre

```php
$order = $viva->orders->create(
    amount: 1500,                         // Centimes (15,00 EUR)
    customerDescription: 'Consultation',  // Affiché au client
    merchantReference: 'session_123',     // Référence interne
    allowRecurring: true,                 // Tokeniser la carte
    preauth: false,                       // Pré-autorisation ?
    maxInstallments: 3,                   // Paiement en 3x
);

echo $order['order_code'];   // 1234567890
echo $order['checkout_url']; // https://demo.vivapayments.com/web/checkout?ref=1234567890
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `amount` | `int` | **requis** | Montant en centimes |
| `customerDescription` | `?string` | `null` | Texte affiché au client |
| `merchantReference` | `?string` | `null` | Référence interne (exports) |
| `sourceCode` | `?string` | `null` | Source de paiement |
| `allowRecurring` | `bool` | `false` | Autoriser les charges récurrentes |
| `preauth` | `bool` | `false` | Pré-autorisation uniquement |
| `maxInstallments` | `int` | `0` | Nombre max de versements |

**Retour :** `array{order_code: int, checkout_url: string}`

#### `get()` — Statut d'un ordre

```php
$order = $viva->orders->get(orderCode: 1234567890);
```

#### `cancel()` — Annuler un ordre non payé

```php
$viva->orders->cancel(orderCode: 1234567890);
```

#### `checkoutUrl()` — URL de checkout (sans appel API)

```php
$url = $viva->orders->checkoutUrl(orderCode: 1234567890);
// 'https://demo.vivapayments.com/web/checkout?ref=1234567890'
```

---

### 2. Transactions — `$viva->transactions`

Consultation, remboursement, capture et paiements récurrents.

#### `get()` — Détails complets (Legacy API, PascalCase)

```php
$txn = $viva->transactions->get('transaction-uuid');
echo $txn['Transactions'][0]['Amount'];
echo $txn['Transactions'][0]['StatusId'];
```

#### `getV2()` — Détails légers (New API, camelCase)

```php
$txn = $viva->transactions->getV2('transaction-uuid');
echo $txn['email'];
echo $txn['amount'];      // En EUR (pas en centimes)
echo $txn['statusId'];    // 'F'
echo $txn['orderCode'];
```

Recommandé pour vérifier les paiements Smart Checkout.

#### `listByDate()` — Lister par date

```php
$transactions = $viva->transactions->listByDate('2026-03-18');

foreach ($transactions as $txn) {
    echo $txn['TransactionId'] . ' — ' . $txn['Amount'] . "\n";
}
```

**Retour :** `array<int, array<string, mixed>>`

#### `cancel()` — Annuler / Rembourser

```php
// Remboursement total
$result = $viva->transactions->cancel('transaction-uuid');

// Remboursement partiel (5,00 EUR)
$result = $viva->transactions->cancel('transaction-uuid', amount: 500);

echo $result['TransactionId']; // UUID du remboursement
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `transactionId` | `string` | **requis** | UUID de la transaction |
| `amount` | `?int` | `null` | Centimes (`null` = total) |
| `sourceCode` | `?string` | `null` | Source de paiement |

Même jour = annulation (void). Jour passé = remboursement (refund).

#### `capture()` — Capturer une pré-autorisation

```php
$result = $viva->transactions->capture('preauth-uuid', amount: 1500);
```

Lève `ApiException` si la capture échoue.

#### `recurring()` — Charge récurrente

```php
$result = $viva->transactions->recurring(
    initialTransactionId: 'initial-txn-uuid',
    amount: 1500,
    sourceCode: '1234', // optionnel
);
```

Prérequis : l'ordre initial doit avoir été créé avec `allowRecurring: true`.

---

### 3. Sources — `$viva->sources`

Gestion des sources de paiement (domaines autorisés, URLs de redirection).

#### `list()` — Lister les sources

```php
$sources = $viva->sources->list();

foreach ($sources as $source) {
    echo $source['Name'] . ' — ' . $source['SourceCode'] . "\n";
}
```

#### `create()` — Créer une source

```php
$source = $viva->sources->create(
    name: 'Mon site web',
    sourceCode: '1234',
    domain: 'www.example.com',
    pathSuccess: '/paiement/succes',
    pathFail: '/paiement/echec',
);
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `name` | `string` | **requis** | Nom d'affichage |
| `sourceCode` | `string` | **requis** | Code à 4 chiffres |
| `domain` | `?string` | `null` | Domaine du site |
| `pathSuccess` | `?string` | `null` | Redirection succès |
| `pathFail` | `?string` | `null` | Redirection échec |

---

### 4. Wallets — `$viva->wallets`

Portefeuilles (sous-comptes), soldes et transferts.

#### `list()` — Lister les portefeuilles

```php
$wallets = $viva->wallets->list();
```

#### `balance()` — Solde agrégé

```php
$balance = $viva->wallets->balance();
echo $balance['available'];  // 150.50
echo $balance['pending'];    // 25.00
echo $balance['reserved'];   // 0.00
echo $balance['currency'];   // 'EUR'
```

**Retour :** `array{available: float, pending: float, reserved: float, currency: string}`

#### `transfer()` — Transfert entre wallets

```php
$viva->wallets->transfer(
    amount: 5000,                        // 50,00 EUR
    sourceWalletId: 'source-uuid',
    targetWalletId: 'target-uuid',
    description: 'Transfert mensuel',
);
```

Prérequis : « Allow transfers between accounts » dans Settings > API Access.

#### `listDetailed()` — Liste enrichie (IBAN, SWIFT)

```php
$wallets = $viva->wallets->listDetailed();

foreach ($wallets as $wallet) {
    echo $wallet['iban'] . ' — ' . $wallet['amount'] . "\n";
    echo $wallet['isPrimary'] ? 'Principal' : 'Secondaire';
}
```

#### `create()` — Créer un portefeuille

```php
$viva->wallets->create(friendlyName: 'Compte secondaire', currencyCode: 'EUR');
```

#### `update()` — Renommer un portefeuille

```php
$viva->wallets->update(walletId: 12345, friendlyName: 'Nouveau nom');
```

#### `searchTransactions()` — Rechercher les transactions de compte

```php
$transactions = $viva->wallets->searchTransactions([
    'date_from' => '2026-03-01',
    'date_to' => '2026-03-18',
    'walletId' => 12345,
]);
```

#### `getTransaction()` — Détails d'une transaction de compte

```php
$txn = $viva->wallets->getTransaction('transaction-uuid');
```

---

### 5. BankAccounts — `$viva->bankAccounts`

Comptes bancaires IBAN et virements SEPA.

#### `link()` — Lier un IBAN

```php
$result = $viva->bankAccounts->link(
    iban: 'FR7630006000011234567890189',
    beneficiaryName: 'Jean Dupont',
    friendlyName: 'Compte principal',
);

echo $result['bankAccountId']; // UUID
echo $result['isVivaIban'];    // false
```

#### `transferOptions()` — Options de transfert

```php
$options = $viva->bankAccounts->transferOptions('bank-account-uuid');
```

#### `feeCommand()` — Calculer les frais avant virement

```php
$fees = $viva->bankAccounts->feeCommand(
    bankAccountId: 'bank-account-uuid',
    amount: 10000,                    // 100,00 EUR
    walletId: 'source-wallet-uuid',
    isInstant: true,                  // SEPA instantané
    instructionType: 'SHA',           // Frais partagés
);

echo $fees['bankCommandId']; // À passer à send()
echo $fees['fee'];           // Frais en centimes
```

#### `send()` — Exécuter un virement SEPA

```php
$result = $viva->bankAccounts->send(
    bankAccountId: 'bank-account-uuid',
    amount: 10000,
    walletId: 'source-wallet-uuid',
    bankCommandId: 'fee-command-uuid',  // Optionnel
    description: 'Virement mensuel',
);

echo $result['commandId']; // UUID du virement
echo $result['isInstant']; // true/false
echo $result['fee'];       // Frais en centimes
```

#### `list()` — Lister les comptes liés

```php
$accounts = $viva->bankAccounts->list();
```

#### `get()` — Détails d'un compte lié

```php
$account = $viva->bankAccounts->get('bank-account-uuid');
```

---

### 6. NativeCheckout — `$viva->nativeCheckout`

Paiements Apple Pay et Google Pay.

#### `createChargeToken()` — Générer un token de charge

```php
$token = $viva->nativeCheckout->createChargeToken(
    amount: 1500,
    paymentData: $applePayPaymentDataString,
    paymentMethod: 'applepay', // ou 'googlepay'
    sourceCode: '1234',
);

echo $token['chargeToken'];       // Token à passer à createTransaction()
echo $token['redirectToACSForm']; // Formulaire 3DS (si applicable)
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `amount` | `int` | **requis** | Montant en centimes |
| `paymentData` | `string` | **requis** | Données Apple Pay / Google Pay |
| `paymentMethod` | `string` | `'applepay'` | `'applepay'` ou `'googlepay'` |
| `sourceCode` | `?string` | `null` | Source de paiement |
| `dynamicDescriptor` | `?string` | `null` | Descripteur dynamique |

#### `createTransaction()` — Exécuter la transaction

```php
$txn = $viva->nativeCheckout->createTransaction(
    chargeToken: $token['chargeToken'],
    amount: 1500,
    currencyCode: 978,             // EUR (ISO 4217 numérique)
    merchantTrns: 'ref_123',
    customerTrns: 'Consultation',
);

echo $txn['transactionId']; // UUID
echo $txn['statusId'];      // 'F' = finalisée
echo $txn['amount'];        // 1500
echo $txn['orderCode'];     // Code de l'ordre
```

| Paramètre | Type | Défaut | Description |
|-----------|------|--------|-------------|
| `chargeToken` | `string` | **requis** | Token de `createChargeToken()` |
| `amount` | `int` | **requis** | Montant en centimes |
| `currencyCode` | `int` | `978` | ISO 4217 numérique |
| `sourceCode` | `?string` | `null` | Source de paiement |
| `merchantTrns` | `?string` | `null` | Référence interne |
| `customerTrns` | `?string` | `null` | Description client |
| `preauth` | `bool` | `false` | Pré-autorisation ? |
| `tipAmount` | `int` | `0` | Pourboire en centimes |
| `installments` | `?int` | `null` | Nombre de versements |

---

### 7. DataServices — `$viva->dataServices`

Rapports MT940 et souscriptions webhook pour les fichiers de données.

#### `mt940()` — Rapport MT940

```php
$report = $viva->dataServices->mt940('2026-03-18');
```

#### `createSubscription()` — Créer une souscription webhook

```php
$sub = $viva->dataServices->createSubscription(
    url: 'https://example.com/webhooks/viva-files',
    eventType: 'SaleTransactionsFileGenerated',
);
echo $sub['subscriptionId'];
```

#### `updateSubscription()` — Mettre à jour

```php
$viva->dataServices->updateSubscription(
    subscriptionId: 'sub-uuid',
    url: 'https://example.com/webhooks/new-url',
    eventType: null, // null = conserver l'actuel
);
```

#### `deleteSubscription()` — Supprimer

```php
$viva->dataServices->deleteSubscription('sub-uuid');
```

#### `listSubscriptions()` — Lister

```php
$subs = $viva->dataServices->listSubscriptions();

foreach ($subs as $sub) {
    echo $sub['subscriptionId'] . ' → ' . $sub['url'] . "\n";
}
```

#### `requestFile()` — Demander la génération d'un fichier

```php
$viva->dataServices->requestFile('2026-03-18');
```

Déclenche la génération asynchrone. Utilisez une souscription webhook pour être notifié.

---

### 8. Webhooks — `$viva->webhooks`

Vérification et parsing des webhooks Viva Wallet. Aucun appel API — tout est local.

#### `verificationResponse()` — Répondre au GET de vérification

```php
public function verify()
{
    return response()->json(
        $viva->webhooks->verificationResponse('votre-verification-key')
    );
    // => {"StatusCode": 0, "Key": "votre-verification-key"}
}
```

#### `parse()` — Parser un webhook POST

```php
public function handle(Request $request)
{
    $event = $viva->webhooks->parse($request->getContent());

    echo $event['event_type'];     // 'transaction.payment.created'
    echo $event['event_type_id'];  // 1796
    echo $event['event_data'];     // Données de l'événement

    match ($event['event_type']) {
        'transaction.payment.created' => $this->handlePayment($event['event_data']),
        'transaction.refund.created'  => $this->handleRefund($event['event_data']),
        default => null,
    };
}
```

**Retour :** `array{event_type: string, event_type_id: int, event_data: array<string, mixed>}`

Lève `InvalidArgumentException` si le JSON est invalide.

#### `isKnownEvent()` — Vérifier un ID d'événement

```php
$viva->webhooks->isKnownEvent(1796); // true
$viva->webhooks->isKnownEvent(9999); // false
```

#### `eventTypeIds()` — Lister les IDs connus

```php
$ids = $viva->webhooks->eventTypeIds();
// [1796, 1797, 1798, ..., 1828]
```

#### 21 types d'événements supportés

| ID | Type |
|----|------|
| 1796 | `transaction.payment.created` |
| 1797 | `transaction.refund.created` |
| 1798 | `transaction.payment.cancelled` |
| 1799 | `transaction.reversal.created` |
| 1800 | `transaction.preauth.created` |
| 1801 | `transaction.preauth.completed` |
| 1802 | `transaction.preauth.cancelled` |
| 1810 | `pos.session.created` |
| 1811 | `pos.session.failed` |
| 1812 | `transaction.price.calculated` |
| 1813 | `transaction.failed` |
| 1819 | `account.connected` |
| 1820 | `account.verification.status.changed` |
| 1821 | `account.transaction.created` |
| 1822 | `command.bank.transfer.created` |
| 1823 | `command.bank.transfer.executed` |
| 1824 | `transfer.created` |
| 1825 | `obligation.created` |
| 1826 | `obligation.captured` |
| 1827 | `order.updated` |
| 1828 | `sale.transactions.file` |

---

### 9. Account — `$viva->account`

Informations du compte marchand.

#### `info()` — Informations du compte

```php
$info = $viva->account->info();
echo $info['merchantId'];
echo $info['businessName'];
echo $info['email'];
```

#### `wallets()` — Portefeuilles du compte

```php
$wallets = $viva->account->wallets();
```

---

### 10. Messages — `$viva->messages()`

Gestion des abonnements webhook via `/api/messages/config` (Legacy API, Basic Auth).

> Utilisez `$viva->webhookRegistrar()->registerAll(...)` pour l'enregistrement idempotent des events banking. `messages()` donne un accès bas niveau si besoin.

#### `register()` — Créer un abonnement webhook

```php
$sub = $viva->messages()->register(
    eventTypeId: 768,                                  // Bank Transfer Created
    callbackUrl: 'https://example.com/webhooks/viva',
);
// => ['Id' => 'sub-uuid', 'Active' => true]
```

#### `list()` — Lister les abonnements

```php
$subscriptions = $viva->messages()->list();
foreach ($subscriptions as $sub) {
    echo $sub['Id'] . ' → EventTypeId: ' . $sub['EventTypeId'];
}
```

#### `delete()` — Supprimer un abonnement

```php
$viva->messages()->delete('sub-uuid-here');
```

---

## Enregistrement des webhooks banking

Les events **768** (Bank Transfer Created), **769** (Bank Transfer Executed) et **2054** (Account Transaction Created) doivent être enregistrés par chaque merchant via `/api/messages/config` (pas via les webhooks ISV).

Utilisez `WebhookRegistrar` pour un enregistrement **idempotent** : les erreurs "duplicate" sont silencieusement transformées en `already_exists`.

```php
// Enregistrer les 3 events banking en une ligne
$results = $viva->webhookRegistrar()->registerAll(
    callbackUrl: 'https://example.com/webhooks/viva',
);
// => ['768' => 'registered', '769' => 'registered', '2054' => 'already_exists']

// Ou un sous-ensemble d'events
$results = $viva->webhookRegistrar()->registerAll(
    callbackUrl: 'https://example.com/webhooks/viva',
    events: [768, 769],
);
```

Statuts possibles dans le tableau retourné :

| Statut | Signification |
|--------|--------------|
| `registered` | Abonnement créé avec succès |
| `already_exists` | Déjà enregistré (Viva a retourné 400 duplicate) |
| `error:{message}` | Échec inattendu (503, réseau, etc.) |

> **Convention cross-SDK** : `WebhookRegistrar::BANKING_EVENTS` et `registerAll()` ont les mêmes noms dans `sdk-php-viva-isv`. La différence est l'absence de `$connectedMerchantId` — ce SDK parle au nom du merchant lui-même.

---

## Architecture

```
src/
├── VivaClient.php          # Point d'entrée — instancie les 10 ressources + 1 helper
├── Config.php              # Configuration (URLs par environnement)
├── HttpClient.php          # Client HTTP Guzzle (OAuth2 auto, Basic Auth)
├── Contracts/
│   ├── HttpClientInterface.php   # Interface pour HttpClient (mocking)
│   └── MessagesInterface.php     # Interface pour Messages (mocking)
├── Enums/
│   ├── Environment.php     # demo | production
│   ├── Currency.php        # Codes ISO 4217 numériques
│   └── TransactionStatus.php  # F, A, C, E, M, X, R
├── Exceptions/
│   ├── VivaException.php          # Classe de base (RuntimeException)
│   ├── ApiException.php           # Erreur API (4xx, 5xx)
│   ├── AuthenticationException.php # Échec OAuth2 (401)
│   └── ValidationException.php    # Validation (422)
├── Helpers/
│   └── WebhookRegistrar.php  # Enregistrement idempotent des events banking
└── Resources/
    ├── Orders.php           # Smart Checkout
    ├── Transactions.php     # Get, list, cancel, capture, recurring
    ├── Sources.php          # Sources de paiement
    ├── Wallets.php          # Portefeuilles, soldes, transferts
    ├── BankAccounts.php     # IBAN, virements SEPA
    ├── NativeCheckout.php   # Apple Pay, Google Pay
    ├── DataServices.php     # MT940, souscriptions webhook
    ├── Webhooks.php         # Vérification et parsing local
    ├── Account.php          # Infos du compte
    └── Messages.php         # Abonnements webhook (/api/messages/config)
```

L'authentification est gérée automatiquement par `HttpClient` :
- **Legacy API** (Basic Auth) — utilisée par Orders, Transactions, Sources
- **New API** (Bearer OAuth2) — utilisée par Wallets, BankAccounts, NativeCheckout, DataServices, Account

Le token OAuth2 est mis en cache en mémoire et rafraîchi automatiquement avant expiration.

---

## Enums

### `Environment`

```php
use QrCommunication\VivaMerchant\Enums\Environment;

$env = Environment::DEMO;
$env = Environment::PRODUCTION;
$env = Environment::from('demo');

$env->value;         // 'demo'
$env->apiUrl();      // 'https://demo-api.vivapayments.com'
$env->legacyUrl();   // 'https://demo.vivapayments.com'
$env->checkoutUrl(); // 'https://demo.vivapayments.com/web/checkout'
$env->accountsUrl(); // 'https://demo-accounts.vivapayments.com'
```

### `Currency`

```php
use QrCommunication\VivaMerchant\Enums\Currency;

Currency::EUR->value;     // 978
Currency::EUR->iso();     // 'EUR'
Currency::fromIso('GBP'); // Currency::GBP (826)
```

Devises supportées : EUR (978), GBP (826), USD (840), PLN (985), RON (946), BGN (975), CZK (203), HRK (191), HUF (348), DKK (208), SEK (752), NOK (578).

### `TransactionStatus`

```php
use QrCommunication\VivaMerchant\Enums\TransactionStatus;

$status = TransactionStatus::from('F');
$status->isSuccessful();  // true
$status->isPending();     // false
$status->isFailed();      // false
$status->label();         // 'Finalized'
```

| Valeur | Constante | `isSuccessful()` | `isPending()` | `isFailed()` |
|--------|-----------|:-:|:-:|:-:|
| `F` | `FINALIZED` | oui | | |
| `A` | `PENDING` | | oui | |
| `C` | `CLEARING` | | oui | |
| `E` | `ERROR` | | | oui |
| `M` | `MANUALLY_REVERSED` | | | oui |
| `X` | `REQUIRES_ACTION` | | | |
| `R` | `REFUNDED` | | | |

---

## Gestion d'erreurs

Toutes les exceptions héritent de `VivaException` qui étend `RuntimeException`.

```
RuntimeException
└── VivaException
    ├── ApiException
    ├── AuthenticationException
    └── ValidationException
```

```php
use QrCommunication\VivaMerchant\Exceptions\ApiException;
use QrCommunication\VivaMerchant\Exceptions\AuthenticationException;
use QrCommunication\VivaMerchant\Exceptions\ValidationException;
use QrCommunication\VivaMerchant\Exceptions\VivaException;

try {
    $order = $viva->orders->create(amount: 1500);
} catch (AuthenticationException $e) {
    // Identifiants invalides — httpStatus = 401
    echo $e->getMessage();
} catch (ValidationException $e) {
    // Erreur de validation — httpStatus = 422
    foreach ($e->errors as $field => $messages) {
        echo "$field: " . implode(', ', $messages);
    }
} catch (ApiException $e) {
    // Erreur API générale — 400, 404, 500, etc.
    echo $e->httpStatus;
    echo $e->getErrorCode();
    echo $e->getErrorText();
    print_r($e->responseBody);
} catch (VivaException $e) {
    // Toute autre erreur SDK
}
```

### Propriétés et méthodes de `VivaException`

| Membre | Type | Description |
|--------|------|-------------|
| `$httpStatus` | `int` | Code HTTP de la réponse |
| `$responseBody` | `?array` | Corps JSON décodé de la réponse |
| `getErrorCode()` | `?int` | Code d'erreur Viva (`ErrorCode`) |
| `getErrorText()` | `?string` | Message d'erreur Viva (`ErrorText`, `message`, ou `detail`) |

---

## Webhooks — Guide d'intégration

### 1. Configurer le webhook dans le Dashboard Viva

1. Aller dans **Settings > API Access > Webhooks**
2. Ajouter l'URL de votre endpoint
3. Noter la **clé de vérification**

### 2. Gérer la vérification (GET)

```php
// Route GET /webhooks/viva
public function verify()
{
    return response()->json(
        $viva->webhooks->verificationResponse('votre-clé')
    );
}
```

### 3. Recevoir les événements (POST)

```php
// Route POST /webhooks/viva
public function handle(Request $request)
{
    $event = $viva->webhooks->parse($request->getContent());

    match ($event['event_type']) {
        'transaction.payment.created'   => $this->onPayment($event['event_data']),
        'transaction.refund.created'    => $this->onRefund($event['event_data']),
        'transaction.preauth.created'   => $this->onPreauth($event['event_data']),
        'transaction.preauth.completed' => $this->onCapture($event['event_data']),
        default => logger()->info('Webhook ignoré : ' . $event['event_type']),
    };

    return response()->json(['status' => 'ok']);
}
```

### 4. Enregistrement des webhooks banking par API (optionnel)

Les events **768**, **769** et **2054** (virements et transactions de compte) ne peuvent pas être configurés depuis le Dashboard Viva pour les marchands standard. Ils doivent être enregistrés **par API** via `/api/messages/config`.

```php
// Enregistrement idempotent au démarrage de l'application
$results = $viva->webhookRegistrar()->registerAll(
    callbackUrl: config('services.viva.webhook_url'),
);

// $results = ['768' => 'registered', '769' => 'registered', '2054' => 'already_exists']
// Les 'already_exists' sont silencieux — safe à relancer à chaque boot.
```

> **Distinction ISV vs merchant** : dans le SDK ISV (`sdk-php-viva-isv`), ces events sont enregistrés au niveau de l'ISV pour un `connectedMerchantId`. Ici, ils s'appliquent directement au compte marchand authentifié.

---

## Carte de test

Pour l'environnement `demo` :

| Champ | Valeur |
|-------|--------|
| Numéro de carte | `4111 1111 1111 1111` |
| Expiration | Toute date future |
| CVV | `111` |
| 3DS | Pas de 3DS en demo |

---

## Documentation interactive

La documentation interactive (ReDoc) est disponible en ligne :

**[https://qrcommunication.github.io/sdk-php-viva-merchant/](https://qrcommunication.github.io/sdk-php-viva-merchant/)**

Elle détaille chaque classe, méthode, paramètre et type de retour du SDK.

---

## Intégration IA

Ce SDK inclut un **skill détaillé** (`skill/SKILL.md`) automatiquement détecté par les assistants IA. Il fournit la référence complète des 9 resources, 34+ méthodes, enums, exceptions et patterns d'implémentation.

| Outil | Fichier | Détection |
|-------|---------|-----------|
| **Claude Code** | `CLAUDE.md` + `skill/SKILL.md` | Automatique |
| **Cursor** | `.cursorrules` | Automatique |
| **GitHub Copilot** | `.github/copilot-instructions.md` | Automatique |
| **OpenAI Codex** | `AGENTS.md` | Automatique |

```php
// Un agent IA peut construire cet appel à partir de :
// "Crée un paiement de 25 EUR pour une consultation"
$order = $viva->orders->create(
    amount: 2500,
    customerDescription: 'Consultation',
);
```

---

## Licence

MIT — [QrCommunication](https://qrcommunication.com)
