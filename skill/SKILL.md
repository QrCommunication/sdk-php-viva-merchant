---
name: sdk-viva-merchant
description: Use when working with Viva Wallet payments, Smart Checkout, transactions, SEPA transfers, Apple/Google Pay, wallets, or projects importing qrcommunication/viva-merchant-sdk (composer). Covers all merchant-side payment operations.
---

# SDK Viva Wallet Merchant — Référence complète

SDK PHP pour toutes les opérations marchands Viva Wallet : paiements Smart Checkout, transactions (get/cancel/capture/recurring), virements SEPA, portefeuilles, Apple Pay / Google Pay (Native Checkout), rapports MT940, sources de paiement et webhooks.

## Package

| Langage | Package | Repo |
|---------|---------|------|
| PHP 8.2+ | `qrcommunication/viva-merchant-sdk` | `QrCommunication/sdk-php-viva-merchant` |

## Installation

```bash
composer require qrcommunication/viva-merchant-sdk
```

## Quick Start

```php
use QrCommunication\VivaMerchant\VivaClient;

$viva = new VivaClient(
    merchantId: 'your-merchant-uuid',        // Settings > API Access > Merchant ID
    apiKey: 'your-api-key',                  // Settings > API Access > API Key
    clientId: 'xxx.apps.vivapayments.com',   // Settings > API Access > OAuth Client ID
    clientSecret: 'your-client-secret',      // Settings > API Access > OAuth Client Secret
    environment: 'demo',                     // 'demo' ou 'production'
);

// Le SDK gère l'auth automatiquement (lazy OAuth2 + Basic Auth)
$order = $viva->orders->create(amount: 1500, customerDescription: 'Consultation');
echo $order['checkout_url']; // Rediriger le client ici
```

## Architecture — 9 Resources, 34+ méthodes

```
VivaClient (point d'entrée, lazy auth)
├── orders          → Orders          (Legacy API, Basic Auth)
│   ├── create(amount, customerDescription?, merchantReference?, sourceCode?, allowRecurring?, preauth?, maxInstallments?)
│   │   → ['order_code' => int, 'checkout_url' => string]
│   ├── get(orderCode) → array (statut de l'ordre)
│   ├── cancel(orderCode) → array
│   └── checkoutUrl(orderCode) → string
│
├── transactions    → Transactions    (Legacy + New API)
│   ├── get(transactionId) → array (Legacy, PascalCase — données complètes)
│   ├── getV2(transactionId) → array (New API, camelCase — vérifie Smart Checkout)
│   ├── listByDate(date) → array[] (format Y-m-d)
│   ├── cancel(transactionId, amount?, sourceCode?) → array (même jour=void, sinon=refund)
│   ├── capture(transactionId, amount) → array (capture preauth)
│   └── recurring(initialTransactionId, amount, sourceCode?) → array (paiement récurrent)
│
├── sources         → Sources         (Legacy API, Basic Auth)
│   ├── list() → array[]
│   └── create(name, sourceCode, domain?, pathSuccess?, pathFail?) → array
│
├── wallets         → Wallets         (Legacy + Account API)
│   ├── list() → array[] (wallets basiques)
│   ├── balance() → ['available' => float, 'pending' => float, 'reserved' => float, 'currency' => string]
│   ├── transfer(amount, sourceWalletId, targetWalletId, description?) → array
│   ├── listDetailed() → array[] (Account API — IBAN, SWIFT, soldes)
│   ├── create(friendlyName, currencyCode?) → array
│   ├── update(walletId, friendlyName) → array
│   ├── searchTransactions(filters?) → array[]
│   └── getTransaction(transactionId) → array
│
├── bankAccounts    → BankAccounts    (New API, Bearer)
│   ├── link(iban, beneficiaryName, friendlyName?) → ['bankAccountId' => string, 'isVivaIban' => bool]
│   ├── list() → array[]
│   ├── get(bankAccountId) → array
│   ├── transferOptions(bankAccountId) → array (instant SEPA, SHA/OUR)
│   ├── feeCommand(bankAccountId, amount, walletId, isInstant?, instructionType?) → ['bankCommandId' => string, 'fee' => int]
│   └── send(bankAccountId, amount, walletId, bankCommandId?, description?) → ['commandId' => string, 'isInstant' => bool, 'fee' => int]
│
├── nativeCheckout  → NativeCheckout  (New API, Bearer)
│   ├── createChargeToken(amount, paymentData, paymentMethod?, sourceCode?, dynamicDescriptor?) → ['chargeToken' => string]
│   └── createTransaction(chargeToken, amount, currencyCode?, sourceCode?, merchantTrns?, customerTrns?, preauth?, tipAmount?, installments?) → array
│
├── dataServices    → DataServices    (New API, Bearer)
│   ├── mt940(date) → array (rapport bancaire)
│   ├── createSubscription(url, eventType?) → ['subscriptionId' => string]
│   ├── updateSubscription(subscriptionId, url, eventType?) → array
│   ├── deleteSubscription(subscriptionId) → array
│   ├── listSubscriptions() → array[]
│   └── requestFile(date) → ['requestId' => string]
│
├── webhooks        → Webhooks        (pas d'auth — parsing)
│   ├── verificationResponse(verificationKey) → ['StatusCode' => 0, 'Key' => string]
│   ├── parse(rawBody) → ['event_type' => string, 'event_type_id' => int, 'event_data' => array]
│   ├── isKnownEvent(eventTypeId) → bool (static)
│   ├── eventTypeIds() → int[]
│   └── EVENTS (const) → array<int, string> (21 événements)
│
└── account         → Account         (New API, Bearer)
    ├── info() → array (merchantId, businessName, email, country)
    └── wallets() → array
```

## Les 2 APIs Viva Wallet

Le SDK route automatiquement vers la bonne API — le développeur n'a pas besoin de savoir.

| API | Host prod | Host demo | Auth | Params |
|-----|-----------|-----------|------|--------|
| **Legacy** | `www.vivapayments.com` | `demo.vivapayments.com` | Basic Auth (MerchantID:APIKey) | **PascalCase** |
| **New** | `api.vivapayments.com` | `demo-api.vivapayments.com` | Bearer OAuth2 | **camelCase** |

Le HttpClient interne gère :
- `legacyGet/Post/DeleteUrl()` → Legacy API avec Basic Auth
- `get/post/put/delete()` → New API avec Bearer OAuth2
- Auth OAuth2 lazy : s'authentifie au premier appel, refresh 60s avant expiration

## Montants

**TOUJOURS en centimes** (int). `1500` = 15,00 EUR. Le SDK ne fait aucune conversion.

## Enums

```php
use QrCommunication\VivaMerchant\Enums\TransactionStatus;
use QrCommunication\VivaMerchant\Enums\Currency;
use QrCommunication\VivaMerchant\Enums\Environment;

TransactionStatus::from('F')->isSuccessful();  // true
TransactionStatus::from('E')->isFailed();      // true
TransactionStatus::from('A')->isPending();     // true
TransactionStatus::FINALIZED->label();         // 'Finalized'

Currency::EUR->value;        // 978
Currency::fromIso('GBP');    // Currency::GBP

Environment::DEMO->apiUrl();      // 'https://demo-api.vivapayments.com'
Environment::DEMO->legacyUrl();   // 'https://demo.vivapayments.com'
Environment::DEMO->checkoutUrl(); // 'https://demo.vivapayments.com/web/checkout'
```

## Exceptions

```
VivaException (RuntimeException)
├── ApiException              → Erreur HTTP 4xx/5xx
├── AuthenticationException   → OAuth2 invalide (401)
└── ValidationException       → Erreur validation (422, avec ->errors)

Propriétés communes :
  $e->httpStatus      → int (code HTTP)
  $e->responseBody    → ?array (réponse JSON décodée)
  $e->getErrorCode()  → ?int (ErrorCode Viva)
  $e->getErrorText()  → ?string (ErrorText Viva)
```

## 21 Webhooks

```php
Webhooks::EVENTS = [
    1796 => 'transaction.payment.created',
    1797 => 'transaction.refund.created',
    1798 => 'transaction.payment.cancelled',
    1799 => 'transaction.reversal.created',
    1800 => 'transaction.preauth.created',
    1801 => 'transaction.preauth.completed',
    1802 => 'transaction.preauth.cancelled',
    1810 => 'pos.session.created',
    1811 => 'pos.session.failed',
    1812 => 'transaction.price.calculated',
    1813 => 'transaction.failed',
    1819 => 'account.connected',
    1820 => 'account.verification.status.changed',
    1821 => 'account.transaction.created',
    1822 => 'command.bank.transfer.created',
    1823 => 'command.bank.transfer.executed',
    1824 => 'transfer.created',
    1825 => 'obligation.created',
    1826 => 'obligation.captured',
    1827 => 'order.updated',
    1828 => 'sale.transactions.file',
];
```

## Patterns d'implémentation courants

### Flux Smart Checkout complet
```php
// 1. Créer l'ordre
$order = $viva->orders->create(amount: 5000, customerDescription: 'Séance', merchantReference: 'session_42');

// 2. Rediriger le client
return redirect($order['checkout_url']);

// 3. Webhook reçu (POST)
$event = $viva->webhooks->parse($request->getContent());
if ($event['event_type'] === 'transaction.payment.created') {
    $txnId = $event['event_data']['TransactionId'];
    // 4. Vérifier la transaction
    $txn = $viva->transactions->getV2($txnId);
    if ($txn['statusId'] === 'F') { /* Paiement confirmé */ }
}
```

### Preauth + Capture
```php
$order = $viva->orders->create(amount: 10000, preauth: true);
// ... client paie via checkout ...
// Plus tard :
$viva->transactions->capture($preauthTxnId, amount: 8500); // capturer 85€ sur les 100€ pré-autorisés
```

### Virement SEPA
```php
// 1. Lier un IBAN
$account = $viva->bankAccounts->link('FR7630006000011234567890189', 'Jean Dupont', 'Compte principal');
// 2. Vérifier les options
$options = $viva->bankAccounts->transferOptions($account['bankAccountId']);
// 3. Exécuter le virement
$result = $viva->bankAccounts->send($account['bankAccountId'], amount: 50000, walletId: 'wallet-uuid');
```

### Apple Pay
```php
// 1. Charge token depuis le paymentData Apple Pay
$token = $viva->nativeCheckout->createChargeToken(amount: 1500, paymentData: $applePayJsonString, paymentMethod: 'applepay');
// 2. Transaction
$txn = $viva->nativeCheckout->createTransaction(chargeToken: $token['chargeToken'], amount: 1500);
```

## Pièges à éviter

| Piège | Conséquence | Le SDK gère |
|-------|-------------|-------------|
| Bearer token sur Legacy API | 401 | Oui — routing automatique |
| `scope=` dans le token request | `invalid_scope` | Oui — jamais envoyé |
| PascalCase sur New API | Champs ignorés silencieusement | Oui — casse correcte par resource |
| cancel() même jour vs lendemain | void vs refund | Non — comportement Viva normal |
| Capture sans "Allow recurring" activé | Échec silencieux | Non — à activer dans Settings |
| paymentMethodId 10 vs 11 | Apple vs Google Pay | Le SDK expose `paymentMethod: 'applepay'\|'googlepay'` |

## Carte de test (demo)

| Champ | Valeur |
|-------|--------|
| Numéro | `4111111111111111` |
| CVV | `111` |
| Expiration | N'importe quelle date future |
| 3DS password | `Secret!33` |

### Montants de déclin

| Centimes | Raison |
|----------|--------|
| 9951 | Insufficient funds |
| 9954 | Expired card |
| 9920 | Stolen card |
| 9957 | Card not permitted |
| 9961 | Withdrawal limit exceeded |
| 9906 | General error |
| 9914 | Invalid card |

## Structure du projet

```
src/
├── VivaClient.php              (point d'entrée, 9 resources)
├── Config.php                   (credentials + environment)
├── HttpClient.php               (Guzzle, dual auth, lazy OAuth2)
├── Resources/
│   ├── Orders.php               (create, get, cancel, checkoutUrl)
│   ├── Transactions.php         (get, getV2, listByDate, cancel, capture, recurring)
│   ├── Sources.php              (list, create)
│   ├── Wallets.php              (list, balance, transfer, listDetailed, create, update, searchTransactions, getTransaction)
│   ├── BankAccounts.php         (link, list, get, transferOptions, feeCommand, send)
│   ├── NativeCheckout.php       (createChargeToken, createTransaction)
│   ├── DataServices.php         (mt940, createSubscription, updateSubscription, deleteSubscription, listSubscriptions, requestFile)
│   ├── Webhooks.php             (verificationResponse, parse, isKnownEvent, eventTypeIds, EVENTS)
│   └── Account.php              (info, wallets)
├── Enums/
│   ├── Environment.php          (DEMO, PRODUCTION + URLs)
│   ├── TransactionStatus.php    (F, A, C, E, M, X, R + helpers)
│   └── Currency.php             (EUR, GBP, USD... + fromIso())
└── Exceptions/
    ├── VivaException.php        (base, httpStatus, responseBody, getErrorCode/Text)
    ├── ApiException.php         (4xx/5xx)
    ├── AuthenticationException.php (401)
    └── ValidationException.php  (422, ->errors)
```
