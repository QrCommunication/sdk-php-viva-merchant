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

## Resources (9)

| Resource | API | Auth | Endpoints |
|----------|-----|------|-----------|
| Orders | Legacy | Basic | create, get, cancel, checkoutUrl |
| Transactions | Legacy+New | Mixed | get, getV2, listByDate, cancel, capture, recurring |
| Sources | Legacy | Basic | list, create |
| Wallets | Legacy+New | Mixed | list, balance, transfer, listDetailed, create, update, searchTransactions, getTransaction |
| BankAccounts | New | Bearer | link, list, get, transferOptions, feeCommand, send |
| NativeCheckout | New | Bearer | createChargeToken, createTransaction |
| DataServices | New | Bearer | mt940, createSubscription, updateSubscription, deleteSubscription, listSubscriptions, requestFile |
| Account | New | Bearer | info, wallets |
| Webhooks | None | None | verificationResponse, parse, isKnownEvent, eventTypeIds + EVENTS constant (21 types) |

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

1. **Ne JAMAIS envoyer `scope=` dans le token request** -> `invalid_scope`.
2. **Bearer token sur Legacy API** -> 401. Legacy = Basic Auth uniquement.
3. **`IsPreAuth` (PascalCase)** dans Legacy API, pas `preauth` (camelCase).
4. **cancel() le meme jour** = void. **cancel() jour suivant** = refund.
5. **Capture preauth** necessite "Allow recurring payments and pre-auth captures via API" active.
6. **NativeCheckout** : paymentMethodId 10 = Apple Pay, 11 = Google Pay.

## Conventions de code

- PHP 8.2+ strict types
- Tous les montants en **centimes** (int)
- Retours types arrays avec cles documentees en PHPDoc
- Guzzle 7.8+ comme client HTTP
- PSR-4 autoloading : `QrCommunication\VivaMerchant\`

## Carte de test (demo)

- Numero : `4111111111111111`, CVV : `111`, 3DS : `Secret!33`
- Montants de declin : 9920 (stolen), 9951 (insufficient funds), 9954 (expired), 9957 (not permitted)
