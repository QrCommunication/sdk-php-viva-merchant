# Viva Wallet Merchant SDK -- AI Instructions

> Ce fichier est automatiquement detecte par Claude Code, Cursor, Copilot et Codex.

## SDK Overview

Package PHP `qrcommunication/viva-merchant-sdk` pour l'API Viva Wallet cote marchand.
Pattern **Resource** : `$viva->orders->create()`, `$viva->transactions->get()`, etc.

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
|-- webhooks        -> Webhooks        (pas d'auth)
|-- account         -> Account         (New API, Bearer OAuth2)
```

## Les 2 APIs Viva Wallet

| API | Host | Auth | Params |
|-----|------|------|--------|
| **Legacy** | `www.vivapayments.com` | Basic Auth (MerchantID:APIKey) | **PascalCase** (`Amount`, `SourceCode`) |
| **New** | `api.vivapayments.com` | Bearer OAuth2 token | **camelCase** (`amount`, `sourceCode`) |

**CRITIQUE** : ne jamais melanger les casses. Legacy = PascalCase, New = camelCase.

## Routing HTTP interne

- `HttpClient::legacyGet/Post/DeleteUrl()` -> Legacy API avec Basic Auth
- `HttpClient::get/post/put/delete()` -> New API avec Bearer token
- L'auth OAuth2 est lazy + auto-refresh (60s avant expiration)

## Instanciation

```php
$viva = new VivaClient(
    merchantId: 'uuid',        // Basic Auth username
    apiKey: 'key',             // Basic Auth password
    clientId: 'xxx.apps.vivapayments.com',  // OAuth2
    clientSecret: 'secret',    // OAuth2
    environment: 'demo',       // ou 'production'
);
```

## Patterns d'implementation

### Creer un ordre Smart Checkout
```php
$order = $viva->orders->create(
    amount: 1500,              // centimes (EUR 15.00)
    customerDescription: 'Description visible au client',
    merchantReference: 'ref_interne',
    allowRecurring: true,      // tokenise la carte
    preauth: false,
);
// Retourne : ['order_code' => int, 'checkout_url' => string]
```

### Capturer un preauth
```php
$viva->transactions->capture('preauth-txn-uuid', amount: 1500);
```

### Paiement recurrent
```php
$viva->transactions->recurring('initial-txn-uuid', amount: 1500);
```

### Remboursement
```php
$viva->transactions->cancel('txn-uuid');              // total
$viva->transactions->cancel('txn-uuid', amount: 500); // partiel
```

### Paiement Apple Pay / Google Pay (NativeCheckout)
```php
// Etape 1 : Generer un charge token
$token = $viva->nativeCheckout->createChargeToken(
    amount: 1500,
    paymentData: $applePayData,
    paymentMethod: 'applepay',    // ou 'googlepay'
);

// Etape 2 : Executer la transaction
$txn = $viva->nativeCheckout->createTransaction(
    chargeToken: $token['chargeToken'],
    amount: 1500,
);
```

### Data Services (MT940, fichiers)
```php
// Releve MT940
$report = $viva->dataServices->mt940('2026-03-18');

// Abonnement webhook pour fichiers
$sub = $viva->dataServices->createSubscription('https://example.com/hook');

// Demander la generation d'un fichier
$viva->dataServices->requestFile('2026-03-18');
```

### Webhooks
```php
// GET de verification
return $viva->webhooks->verificationResponse('your-key');

// POST evenement
$event = $viva->webhooks->parse($rawBody);
// => ['event_type' => 'transaction.payment.created', 'event_type_id' => 1796, 'event_data' => [...]]

// 21 types d'evenements supportes (voir Webhooks::EVENTS)
```

## Enums

- `TransactionStatus::from('F')->isSuccessful()` -> true
- `Currency::EUR->value` -> 978
- `Environment::DEMO->apiUrl()` -> `https://demo-api.vivapayments.com`

## Exceptions

```
VivaException (RuntimeException)
|-- ApiException             -> Erreur HTTP (4xx/5xx)
|-- AuthenticationException  -> OAuth2 invalide (401)
|-- ValidationException      -> Validation (422, avec ->errors)
```

Toutes exposent : `$e->httpStatus`, `$e->responseBody`, `$e->getErrorCode()`, `$e->getErrorText()`.

## Pieges a eviter

1. **Ne JAMAIS envoyer `scope=` dans le token request** -> `invalid_scope`. Laisser Viva attribuer les scopes par defaut.
2. **Bearer token sur Legacy API** -> 401. Legacy = Basic Auth uniquement.
3. **`IsPreAuth` (PascalCase)** dans Legacy API, pas `preauth` (camelCase).
4. **cancel() le meme jour** = void (annulation). **cancel() jour suivant** = refund (remboursement).
5. **Capture preauth** necessite "Allow recurring payments and pre-auth captures via API" active dans Settings > API Access.
6. **NativeCheckout** : `paymentMethodId` 10 = Apple Pay, 11 = Google Pay. Ne pas confondre.

## Conventions de code

- PHP 8.2+ strict types
- Tous les montants en **centimes** (int)
- Retours types arrays avec cles documentees en PHPDoc
- Guzzle 7.8+ comme client HTTP
- PSR-4 autoloading : `QrCommunication\VivaMerchant\`

## Carte de test (demo)

- Numero : `4111111111111111`, CVV : `111`, 3DS : `Secret!33`
- Montants de declin : 9920 (stolen), 9951 (insufficient funds), 9954 (expired), 9957 (not permitted)
