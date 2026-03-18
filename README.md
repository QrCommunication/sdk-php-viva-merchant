# Viva Wallet Merchant SDK for PHP

[![Version 1.3.0](https://img.shields.io/badge/version-1.3.0-blue.svg)](https://github.com/qrcommunication/sdk-php-viva-merchant/releases)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2%2B-777BB4.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Packagist](https://img.shields.io/badge/Packagist-qrcommunication%2Fviva--merchant--sdk-orange.svg)](https://packagist.org/packages/qrcommunication/viva-merchant-sdk)

SDK PHP complet pour l'API Viva Wallet marchand. Gestion des paiements, ordres Smart Checkout, transactions, remboursements, paiements récurrents, portefeuilles, virements SEPA, comptes bancaires, sources de paiement et webhooks.

> **Ce SDK couvre les opérations marchands standard.** Pour les opérations ISV (comptes connectés, composite auth), voir [`sdk-php-viva-isv`](https://github.com/qrcommunication/sdk-php-viva-isv).

---

## Table des matières

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Référence API complète](#référence-api-complète)
  - [Orders (Smart Checkout)](#1-orders--smart-checkout)
  - [Transactions](#2-transactions)
  - [Sources de paiement](#3-sources-de-paiement)
  - [Wallets (Portefeuilles)](#4-wallets--portefeuilles)
  - [BankAccounts (Comptes bancaires)](#5-bankaccounts--comptes-bancaires)
  - [Account (Compte marchand)](#6-account--compte-marchand)
  - [Webhooks](#7-webhooks)
- [Architecture](#architecture)
- [Les deux APIs Viva Wallet](#les-deux-apis-viva-wallet)
- [Gestion des erreurs](#gestion-des-erreurs)
- [Enums](#enums)
- [Événements Webhook](#événements-webhook)
- [Test en sandbox](#test-en-sandbox)
- [Documentation API interactive](#documentation-api-interactive)
- [Intégration AI](#intégration-ai-claude-cursor-copilot-codex)
- [Licence](#licence)

---

## Installation

```bash
composer require qrcommunication/viva-merchant-sdk
```

**Prérequis** : PHP 8.2+ avec `ext-json` et `ext-curl`.

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

// 2. Créer un ordre de paiement (montant en centimes)
$order = $viva->orders->create(
    amount: 1500,                                // 15,00 EUR
    customerDescription: 'Consultation',
);
// => ['order_code' => 1234567890, 'checkout_url' => 'https://...']

// 3. Rediriger le client vers le checkout
header('Location: ' . $order['checkout_url']);

// 4. Après paiement, vérifier la transaction
$txn = $viva->transactions->getV2('transaction-uuid');

// 5. Rembourser si nécessaire
$viva->transactions->cancel('transaction-uuid', amount: 500); // 5,00 EUR
```

### Où trouver les credentials

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

## Référence API complète

### 1. Orders — Smart Checkout

Créez des ordres de paiement et redirigez les clients vers le checkout Viva Wallet.
**API Legacy** — Basic Auth — PascalCase.

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `create()` | `POST /api/orders` | Créer un ordre de paiement |
| `get()` | `GET /api/orders/{orderCode}` | Récupérer le statut d'un ordre |
| `cancel()` | `DELETE /api/orders/{orderCode}` | Annuler un ordre non payé |
| `checkoutUrl()` | — | Générer l'URL de checkout |

```php
// Créer un ordre
$order = $viva->orders->create(
    amount: 1500,                        // EUR 15.00 (centimes)
    customerDescription: 'Consultation', // affiché au client
    merchantReference: 'session_123',    // référence interne
    sourceCode: '1234',                  // source de paiement
    allowRecurring: true,                // tokeniser la carte
    preauth: false,                      // pré-autorisation
    maxInstallments: 3,                  // paiement en 3 fois max
);
echo $order['order_code'];    // 1234567890
echo $order['checkout_url'];  // https://demo.vivapayments.com/web/checkout?ref=1234567890

// Récupérer le statut d'un ordre
$status = $viva->orders->get(1234567890);

// Annuler un ordre non payé
$viva->orders->cancel(1234567890);

// Générer l'URL de checkout pour un ordre existant
$url = $viva->orders->checkoutUrl(1234567890);
```

---

### 2. Transactions

Consultation, remboursement, capture de pré-autorisation et paiements récurrents.
**API Legacy** (Basic Auth) sauf `getV2()` qui utilise l'**API New** (Bearer OAuth2).

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| `get()` | `GET /api/transactions/{id}` | Basic | Détails complets (PascalCase) |
| `getV2()` | `GET /checkout/v2/transactions/{id}` | Bearer | Détails légers (camelCase) |
| `listByDate()` | `GET /api/transactions?date=` | Basic | Lister par date |
| `cancel()` | `DELETE /api/transactions/{id}` | Basic | Annuler / rembourser |
| `capture()` | `POST /api/transactions/{id}` | Basic | Capturer un preauth |
| `recurring()` | `POST /api/transactions/{id}` | Basic | Paiement récurrent |

```php
// Détails d'une transaction (Legacy — réponse complète PascalCase)
$txn = $viva->transactions->get('transaction-uuid');

// Détails d'une transaction (New API — réponse légère camelCase)
// Recommandé par Viva pour vérifier les paiements Smart Checkout
$txn = $viva->transactions->getV2('transaction-uuid');

// Lister les transactions du jour
$transactions = $viva->transactions->listByDate('2026-03-18');

// Remboursement total
$refund = $viva->transactions->cancel('transaction-uuid');

// Remboursement partiel (EUR 5.00)
$refund = $viva->transactions->cancel('transaction-uuid', amount: 500);

// Remboursement avec source
$refund = $viva->transactions->cancel('transaction-uuid', amount: 500, sourceCode: '1234');

// Capturer une pré-autorisation
$viva->transactions->capture('preauth-uuid', amount: 1500);

// Paiement récurrent (utilise le token de la transaction initiale)
$viva->transactions->recurring('initial-txn-uuid', amount: 1500);
$viva->transactions->recurring('initial-txn-uuid', amount: 1500, sourceCode: '1234');
```

> **Note** : `cancel()` le même jour = annulation (void). `cancel()` le jour suivant = remboursement (refund).

---

### 3. Sources de paiement

Gestion des sources de paiement (payment sources) pour configurer les redirections checkout.
**API Legacy** — Basic Auth — PascalCase.

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `list()` | `GET /api/sources` | Lister les sources |
| `create()` | `POST /api/sources` | Créer une source |

```php
// Lister les sources configurées
$sources = $viva->sources->list();

// Créer une source de paiement
$viva->sources->create(
    name: 'Mon site web',
    sourceCode: '1234',
    domain: 'example.com',
    pathSuccess: '/payment/success',
    pathFail: '/payment/failed',
);
```

---

### 4. Wallets — Portefeuilles

Soldes, transferts entre wallets, gestion avancée via Account API.
Mélange d'**API Legacy** (transferts) et **API New** (liste, création, transactions).

| Méthode | Endpoint | Auth | Description |
|---------|----------|------|-------------|
| `list()` | `GET /api/wallets` | Bearer | Liste des portefeuilles avec soldes |
| `balance()` | — | Bearer | Solde agrégé (available, pending, reserved) |
| `transfer()` | `POST /api/wallets/transfer` | Basic | Transfert entre wallets |
| `listDetailed()` | `GET /walletaccounts/v1/wallets` | Bearer | Liste enrichie (IBAN, SWIFT) |
| `create()` | `POST /walletaccounts/v1/wallets` | Bearer | Créer un sous-compte |
| `update()` | `PATCH /walletaccounts/v1/wallets/{id}` | Bearer | Renommer un wallet |
| `searchTransactions()` | `GET /walletaccounts/v1/transactions` | Bearer | Rechercher les transactions compte |
| `getTransaction()` | `GET /walletaccounts/v1/transactions/{id}` | Bearer | Détails transaction compte |

```php
// Liste des portefeuilles
$wallets = $viva->wallets->list();

// Solde agrégé
$balance = $viva->wallets->balance();
// => ['available' => 1500.50, 'pending' => 200.00, 'reserved' => 0.00, 'currency' => 'EUR']

// Transfert entre portefeuilles (API Legacy)
$viva->wallets->transfer(
    amount: 5000,                        // 50,00 EUR
    sourceWalletId: 'wallet-uuid-source',
    targetWalletId: 'wallet-uuid-target',
    description: 'Transfert mensuel',
);

// Liste détaillée via Account API (IBAN, friendlyName)
$detailed = $viva->wallets->listDetailed();

// Créer un sous-compte
$viva->wallets->create(friendlyName: 'Épargne', currencyCode: 'EUR');

// Renommer un portefeuille
$viva->wallets->update(walletId: 12345, friendlyName: 'Nouveau nom');

// Rechercher les transactions compte
$txns = $viva->wallets->searchTransactions([
    'date_from' => '2026-03-01',
    'date_to'   => '2026-03-18',
    'walletId'  => 12345,
]);

// Détails d'une transaction compte
$txn = $viva->wallets->getTransaction('transaction-uuid');
```

---

### 5. BankAccounts — Comptes bancaires

Lier des IBAN, consulter les options de transfert et exécuter des virements SEPA.
**API New** — Bearer OAuth2 — camelCase.

| Méthode | Endpoint | Description |
|---------|----------|-------------|
| `link()` | `POST /banktransfers/v1/bankaccounts` | Lier un IBAN |
| `list()` | `GET /banktransfers/v1/bankaccounts` | Lister les comptes liés |
| `get()` | `GET /banktransfers/v1/bankaccounts/{id}` | Détails d'un compte |
| `transferOptions()` | `GET /banktransfers/v1/bankaccounts/{id}/instructiontypes` | Options de transfert |
| `feeCommand()` | `POST /banktransfers/v1/bankaccounts/{id}/fees` | Calculer les frais |
| `send()` | `POST /banktransfers/v1/bankaccounts/{id}:send` | Exécuter un virement SEPA |

```php
// Lier un compte bancaire (validation IBAN automatique)
$result = $viva->bankAccounts->link(
    iban: 'FR7630006000011234567890189',
    beneficiaryName: 'Jean Dupont',
    friendlyName: 'Compte principal',
);
// => ['bankAccountId' => 'ba-uuid-123', 'isVivaIban' => false]

// Lister les comptes bancaires liés
$accounts = $viva->bankAccounts->list();

// Récupérer un compte spécifique
$account = $viva->bankAccounts->get('ba-uuid-123');

// Consulter les options de transfert (SEPA standard, instant, SHA, OUR)
$options = $viva->bankAccounts->transferOptions('ba-uuid-123');

// Calculer les frais avant de transférer
$fees = $viva->bankAccounts->feeCommand(
    bankAccountId: 'ba-uuid-123',
    amount: 50000,                       // 500,00 EUR
    walletId: 'wallet-uuid',
    isInstant: false,
    instructionType: 'SHA',
);
// => ['bankCommandId' => 'cmd-uuid', 'fee' => 150]

// Exécuter le virement SEPA
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

### 6. Account — Compte marchand

Informations du compte et soldes.
**API New** — Bearer OAuth2.

| Méthode | Endpoint | Description |
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

### 7. Webhooks

Vérification et parsing des webhooks Viva Wallet. Pas d'authentification côté SDK.

| Méthode | Description |
|---------|-------------|
| `verificationResponse()` | Générer la réponse au GET de vérification |
| `parse()` | Parser un événement POST |

```php
// 1. Vérification du webhook (répondre au GET de Viva)
$response = $viva->webhooks->verificationResponse('your-verification-key');
return response()->json($response); // Laravel
// => {"StatusCode": 0, "Key": "your-verification-key"}

// 2. Parser un événement webhook (POST)
$event = $viva->webhooks->parse($request->getContent());
// => ['event_type' => 'transaction.payment.created', 'event_data' => [...]]

match ($event['event_type']) {
    'transaction.payment.created'   => handlePayment($event['event_data']),
    'transaction.refund.created'    => handleRefund($event['event_data']),
    'transaction.payment.cancelled' => handleCancellation($event['event_data']),
    'transaction.preauth.created'   => handlePreauth($event['event_data']),
    'transaction.preauth.completed' => handleCapture($event['event_data']),
    default => null,
};
```

---

## Architecture

```
VivaClient (point d'entrée)
├── orders          → Orders          (Legacy API, Basic Auth)
├── transactions    → Transactions    (Legacy API, Basic Auth + New API)
├── sources         → Sources         (Legacy API, Basic Auth)
├── wallets         → Wallets         (Legacy + New API, Mixed Auth)
├── bankAccounts    → BankAccounts    (New API, Bearer OAuth2)
├── webhooks        → Webhooks        (pas d'auth — parsing local)
└── account         → Account         (New API, Bearer OAuth2)
```

### Structure du code

```
src/
├── VivaClient.php              # Point d'entrée principal
├── Config.php                  # Configuration (credentials, URLs)
├── HttpClient.php              # Client HTTP (Guzzle, OAuth2, Basic Auth)
├── Enums/
│   ├── Environment.php         # DEMO / PRODUCTION
│   ├── Currency.php            # 12 devises ISO 4217
│   └── TransactionStatus.php   # 7 statuts de transaction
├── Exceptions/
│   ├── VivaException.php       # Exception de base
│   ├── ApiException.php        # Erreurs HTTP générales
│   ├── AuthenticationException.php  # OAuth2 / 401
│   └── ValidationException.php      # Validation / 422
└── Resources/
    ├── Orders.php              # Legacy API — Smart Checkout
    ├── Transactions.php        # Legacy + New API — transactions
    ├── Sources.php             # Legacy API — sources de paiement
    ├── Wallets.php             # Mixed — portefeuilles + Account API
    ├── BankAccounts.php        # New API — IBAN + virements SEPA
    ├── Account.php             # New API — info compte
    └── Webhooks.php            # Vérification + parsing webhooks
```

---

## Les deux APIs Viva Wallet

Viva Wallet expose **deux APIs distinctes** avec des conventions et authentifications différentes.
Le SDK gère automatiquement le routage — vous n'avez pas à vous en préoccuper.

### API Legacy (Basic Auth)

| Propriété | Valeur |
|-----------|--------|
| **Host production** | `www.vivapayments.com` |
| **Host demo** | `demo.vivapayments.com` |
| **Auth** | Basic Auth (MerchantID:APIKey) |
| **Convention** | PascalCase (`Amount`, `SourceCode`, `IsPreAuth`) |
| **Resources** | Orders, Transactions (sauf getV2), Sources, Wallets (transfer) |

### API New (Bearer OAuth2)

| Propriété | Valeur |
|-----------|--------|
| **Host production** | `api.vivapayments.com` |
| **Host demo** | `demo-api.vivapayments.com` |
| **Auth** | Bearer token OAuth2 (auto-refresh) |
| **Convention** | camelCase (`amount`, `sourceCode`, `isPreAuth`) |
| **Resources** | Transactions (getV2), Wallets (list, detailed, create, update, transactions), BankAccounts, Account |

### Authentification automatique

Le SDK gère l'authentification de manière transparente :
- Les tokens OAuth2 sont cachés en mémoire et renouvelés 60 secondes avant expiration
- Les credentials Basic Auth sont envoyés avec chaque requête Legacy API
- Aucune gestion manuelle de tokens nécessaire

```php
// Forcer le renouvellement du token OAuth2
$viva->invalidateToken();
```

---

## Gestion des erreurs

Le SDK lance des exceptions typées pour chaque type d'erreur.

```php
use QrCommunication\VivaMerchant\Exceptions\AuthenticationException;
use QrCommunication\VivaMerchant\Exceptions\ApiException;
use QrCommunication\VivaMerchant\Exceptions\ValidationException;

try {
    $order = $viva->orders->create(amount: 1500);
} catch (AuthenticationException $e) {
    // Credentials invalides (HTTP 401)
    echo "Auth échouée : {$e->getMessage()}";

} catch (ValidationException $e) {
    // Données invalides (HTTP 422)
    echo "Validation : " . json_encode($e->errors);

} catch (ApiException $e) {
    // Erreur API Viva (HTTP 4xx/5xx)
    echo "Erreur API [{$e->httpStatus}] : {$e->getMessage()}";
    echo "Code erreur : {$e->getErrorCode()}";
    echo "Texte erreur : {$e->getErrorText()}";
    echo "Réponse brute : " . json_encode($e->responseBody);
}
```

### Hiérarchie des exceptions

```
RuntimeException
  └── VivaException
        ├── ApiException             (erreurs HTTP générales — 4xx/5xx)
        ├── AuthenticationException  (OAuth2 / 401)
        └── ValidationException      (validation / 422 — avec $e->errors)
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

Devises supportées : `EUR` (978), `GBP` (826), `USD` (840), `PLN` (985), `RON` (946), `BGN` (975), `CZK` (203), `HRK` (191), `HUF` (348), `DKK` (208), `SEK` (752), `NOK` (578).

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

## Événements Webhook

| EventTypeId | `event_type`                     | Description                  |
|-------------|----------------------------------|------------------------------|
| 1796        | `transaction.payment.created`    | Paiement créé                |
| 1797        | `transaction.refund.created`     | Remboursement créé           |
| 1798        | `transaction.payment.cancelled`  | Paiement annulé              |
| 1799        | `transaction.reversal.created`   | Annulation créée             |
| 1800        | `transaction.preauth.created`    | Pré-autorisation créée       |
| 1801        | `transaction.preauth.completed`  | Pré-autorisation finalisée   |
| 1802        | `transaction.preauth.cancelled`  | Pré-autorisation annulée     |
| 1810        | `pos.session.created`            | Session POS créée            |
| 1811        | `pos.session.failed`             | Session POS échouée          |

---

## Test en sandbox

### Carte de test

| Champ        | Valeur                |
|--------------|-----------------------|
| Numéro       | `4111 1111 1111 1111` |
| CVV          | `111`                 |
| Expiration   | N'importe quelle date future |
| 3DS password | `Secret!33`           |

### Montants de déclin (test)

| Montant (centimes) | Résultat           |
|--------------------|--------------------|
| 9951               | Insufficient funds |
| 9954               | Expired card       |
| 9920               | Stolen card        |

### Workflow de test complet

```php
// 1. Créer un ordre
$order = $viva->orders->create(amount: 1500, customerDescription: 'Test');
echo "Checkout : {$order['checkout_url']}\n";

// 2. Payer manuellement dans le navigateur avec la carte de test

// 3. Vérifier la transaction via New API
$txn = $viva->transactions->getV2('transaction-uuid-from-callback');

// 4. Lister les transactions du jour
$txns = $viva->transactions->listByDate(date('Y-m-d'));
foreach ($txns as $txn) {
    echo "{$txn['TransactionId']} — {$txn['Amount']} EUR\n";
}

// 5. Rembourser
$viva->transactions->cancel($txns[0]['TransactionId']);
```

---

## Documentation API interactive

La documentation complète de l'API est disponible au format OpenAPI 3.1 :

- **Documentation interactive (ReDoc)** : [qrcommunication.github.io/sdk-php-viva-merchant](https://qrcommunication.github.io/sdk-php-viva-merchant/)
- **Spécification OpenAPI** : [`docs/openapi.yaml`](docs/openapi.yaml)

---

## Intégration AI (Claude, Cursor, Copilot, Codex)

Ce SDK inclut des fichiers d'instructions automatiquement détectés par les assistants AI :

| Outil | Fichier | Détection |
|-------|---------|-----------|
| **Claude Code** | [`CLAUDE.md`](CLAUDE.md) | Automatique |
| **Cursor** | [`.cursorrules`](.cursorrules) | Automatique |
| **GitHub Copilot** | [`.github/copilot-instructions.md`](.github/copilot-instructions.md) | Automatique |
| **OpenAI Codex** | [`AGENTS.md`](AGENTS.md) | Automatique |
| **Gemini** | [`CLAUDE.md`](CLAUDE.md) | Manuel (copier dans le contexte) |

Ces fichiers fournissent à l'assistant AI :
- L'architecture du SDK et le pattern Resource
- Le routing entre Legacy API (Basic Auth) et New API (Bearer)
- Les conventions (PascalCase vs camelCase, montants en centimes)
- Les pièges Viva Wallet à éviter
- Des exemples de code complets

---

## Licence

MIT — voir le fichier [LICENSE](LICENSE) pour les détails.

---

<p align="center">
  Développé par <a href="https://qrcommunication.com"><strong>QrCommunication</strong></a>
</p>
