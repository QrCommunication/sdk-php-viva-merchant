# Viva Wallet Merchant — Findings prod-tested (2026-05-04)

> Reference compilée à partir de tests réels sur compte marchand Viva
> production (compte ISV propre QR Communication, Merchant ID
> `0119432c-543d-488e-8c22-ec821313759b`). Ce document complète la doc
> Viva officielle avec les comportements réels observés.

---

## ✅ Endpoints qui marchent en prod (Basic Auth `merchantId:apiKey`)

Les endpoints suivants ont été testés sur le compte propre et fonctionnent
parfaitement sans configuration spéciale côté Viva :

| Endpoint | Méthode SDK | Notes |
|----------|-------------|-------|
| `POST /api/orders` | `$viva->orders->create($amount, ...)` | OrderCode retourné, success: true |
| `GET /api/orders/{orderCode}` | `$viva->orders->get($orderCode)` | Statut ordre |
| `POST /api/transactions/{txnId}` | `$viva->transactions->capture($txnId, $amount)` | Capture preauth |
| `DELETE /api/transactions/{txnId}` | `$viva->transactions->cancel($txnId, ...)` | Refund/void selon date |
| `POST /api/transactions/{initialTxnId}` | `$viva->transactions->recurring(...)` | Charge récurrent |
| `GET /api/transactions?date=Y-m-d` | `$viva->transactions->listByDate($date)` | Liste journalière |
| `GET /api/wallets` | `$viva->wallets->list()` | IBAN, soldes, currency code |
| `POST /api/sources` | `$viva->sources->create(...)` | Création Source Smart Checkout |
| `GET /api/sources` | `$viva->sources->list()` | Liste Sources existantes |
| `POST /api/messages/config` | `$viva->messages->register($eventTypeId, $url)` | Webhooks merchant-level |

**Exemple test prod réel (1 EUR sur compte ISV propre)** :

```php
$viva = new VivaClient(
    merchantId: '0119432c-543d-488e-8c22-ec821313759b',
    apiKey: '...',
    clientId: 'isv-xxx.apps.vivapayments.com',
    clientSecret: '...',
    environment: 'production',
);

$order = $viva->orders->create(amount: 100, customerDescription: 'Test 1 EUR');
// → OrderCode: 5852662680962392, Success: true
// → Checkout URL: https://www.vivapayments.com/web/checkout?ref=5852662680962392

$wallets = $viva->wallets->list();
// → [{ Iban: GR720570..., WalletId: 5273617183734, IsPrimary: true,
//     Amount: 17.89, Available: 17.89, CurrencyCode: 978 }, ...]
```

---

## 🔑 Authentification — Basic Auth simple suffit

Pour le compte propre du merchant (pas un compte connecté sub-merchant via
ISV), l'authentification utilise simplement :

```
Authorization: Basic base64(merchantId:apiKey)
```

Pas besoin de :
- Bearer OAuth token (utilisé pour les endpoints `/checkout/v2/*`,
  `/platforms/v1/*`, `/isv/v1/*` côté ISV)
- Composite Basic Auth (utilisé uniquement par les ISV pour opérer sur
  des connected merchants via `viva-isv-sdk`)

Le SDK merchant gère cela automatiquement via `Config::merchantId` /
`Config::apiKey` injectés au constructeur.

### OAuth Bearer pour endpoints "New API"

Certains endpoints du SDK (`Wallets::listDetailed()`,
`BankAccounts::*`, `Transactions::getV2()`, `NativeCheckout::*`) utilisent
le Bearer token OAuth obtenu via `client_credentials` grant. Le SDK le gère
automatiquement (lazy + retry token expired).

---

## 🚨 Différence importante avec viva-isv-sdk

Si vous gérez un **compte ISV** (Marketplace platform), utilisez
`qrcommunication/viva-isv-sdk` à la place — ce SDK-ci (`viva-merchant-sdk`)
est pour les **comptes propres** où le merchant gère ses propres paiements.

**Cas d'usage typiques de viva-merchant-sdk** :
- E-commerce qui encaisse ses propres ventes
- SaaS qui facture ses utilisateurs (sans split)
- Marketplace côté ISV : pour gérer le compte ISV propre (pas les sub-merchants)

**Cas d'usage de viva-isv-sdk** :
- ISV/Plateforme qui veut créer des sub-merchants
- Split payments avec `isvAmount`
- Composite Basic Auth pour opérer au nom d'un connected merchant

Les deux SDK peuvent cohabiter dans la même app : utiliser
`viva-merchant-sdk` pour le compte propre + `viva-isv-sdk` pour les
sub-merchants connectés.

---

## 💳 Sources Smart Checkout — création vs config UI

`Sources::create()` fonctionne sur le compte propre Viva (Basic Auth simple).
Les success/failure URLs peuvent être passés directement :

```php
$source = $viva->sources->create(
    name: 'PratiConnect Subscriptions',
    sourceCode: 'pcsb',
    domain: 'praticonnect.com',
    pathSuccess: '/payment/success',
    pathFail: '/payment/failure',
);
```

Les Sources existantes peuvent être listées et utilisées dans
`Orders::create($amount, sourceCode: 'pcsb', ...)` pour distinguer plusieurs
flux de paiement (abonnement, achat ponctuel, etc.) avec des URLs de retour
distinctes.

⚠️ **Sur les comptes connectés ISV** (sub-merchants), `POST /api/sources` via
composite auth retourne HTTP 400 silencieux. Viva attribue automatiquement
une Source par défaut au connected merchant lors du KYB — pas besoin de la
créer manuellement, et `IsvOrders::create()` l'utilise sans `sourceCode`.

---

## 🔔 Webhooks merchant-level — handshake et signature

### Verification handshake

Avant de pouvoir enregistrer des webhooks via
`$viva->messages->register($eventTypeId, $url)`, votre endpoint webhook
**DOIT** répondre à un GET sur l'URL avec :

```json
{ "Key": "<verification-key>" }
```

La `verification-key` est récupérable via l'admin Viva
(Settings → API Access → Webhooks) ou via `GET /api/messages/config/{key}`
selon les comptes. Sans ça, Viva refuse l'enregistrement.

### Signature HMAC entrante

Webhooks Viva incluent un header `X-Viva-Signature` = HMAC-SHA256 du body
brut signé avec la verification-key :

```php
$expected = hash_hmac('sha256', $rawPayload, $verificationKey);
if (! hash_equals($expected, $request->header('X-Viva-Signature'))) {
    return response()->json(['error' => 'Invalid signature'], 401);
}
```

⚠️ Viva fait des appels périodiques sur l'URL webhook **sans signature**
depuis IPs Azure (`51.138.x.x`, `20.54.x.x`). **Renvoyer 200 sur les calls
sans signature** ou logger en warning sans bloquer (sinon Viva considère
le webhook comme cassé).

---

## 📊 Wallets et IBAN

`Wallets::list()` (Legacy API) retourne pour chaque wallet :

```json
{
  "Iban": "GR720570...",          // IBAN bancaire Viva
  "WalletId": 5273617183734,
  "IsPrimary": true,
  "Amount": 17.89,                // solde brut
  "Available": 17.89,             // solde disponible
  "Overdraft": 0.00,
  "FriendlyName": "Primary",
  "CurrencyCode": "978"           // ISO 4217 numeric (978 = EUR)
}
```

Le `CurrencyCode` est numérique (ISO 4217 numeric) côté Legacy API. Pour
le code alphabétique (EUR/USD/GBP), utiliser
`Currency::fromIso((int) $code)` du SDK.

`Wallets::listDetailed()` (New API, Bearer) retourne plus de champs (SWIFT,
holder name, bank info) mais nécessite le scope OAuth.

`Wallets::transfer()` fonctionne entre wallets propres si "Allow transfers
between accounts" est activé dans Settings → API Access.

---

## 💰 Bank Accounts (SEPA out)

`BankAccounts::link($iban, $beneficiaryName)` permet d'enregistrer un IBAN
externe pour faire des virements sortants (payouts).

⚠️ Le link nécessite que l'IBAN soit dans la même devise que le wallet
source. Pour cross-currency, il faut passer par le SWIFT path (non géré
automatiquement par Viva → frais bancaires de change).

`BankAccounts::feeCommand()` permet de prévisualiser les frais avant
`send()` — recommandé pour afficher le montant net au merchant avant
exécution.

---

## 🛒 Smart Checkout flow complet (testé prod)

```php
// 1. Créer un ordre (Legacy API)
$order = $viva->orders->create(
    amount: 1500,                              // 15.00 EUR
    customerDescription: 'Consultation',
    merchantReference: 'sess_'.now()->timestamp,
    sourceCode: 'pcsb',                        // optional, default Source si omit
    allowRecurring: true,                      // tokenize la carte
    preauth: false,
);

// 2. Rediriger le client vers le checkout URL
header('Location: '.$order['checkout_url']);

// 3. Au retour, lire l'event 1796 sur le webhook
//    OU vérifier via getV2() pour debug
$txn = $viva->transactions->getV2($transactionId);
// → ['merchant_trns', 'amount', 'state_id', 'tx_id', ...]
```

---

## 📝 Codes d'erreur Viva courants

| HTTP | ErrorCode (body) | Cause | Action |
|------|------------------|-------|--------|
| 401 | — | API key incorrecte ou inactive | Vérifier credentials |
| 403 | — | Endpoint pas activé sur le compte | Ticket support Viva |
| 404 | — | Resource introuvable OU endpoint déprécié | Vérifier path + UUID |
| 422 | divers | Validation payload | Vérifier champs requis |
| 1301 | 1301 | Carte refusée | Action client |
| 9023 | 9023 | Solde insuffisant | Action client |
| 3732 | 3732 | Webhook duplicate | Idempotent — ignorer |
| 9951 | 10051 | Test : insufficient funds | Test only |
| 9954 | 10054 | Test : expired card | Test only |
| 9920 | 10020 | Test : stolen card | Test only |

---

## 📚 Sources

- Tests prod 2026-05-04 sur PratiConnect avec compte ISV propre QR Communication
- Compte testé : Merchant `0119432c-...`, Wallet primary 17.89 EUR
- Doc Viva crawled : `/home/rony/doc-crawler/viva-docs-old/` (418 pages)
- SDK officiel : `qrcommunication/viva-merchant-sdk` v1.4.0+

---

## 🔍 Endpoints Viva non encore couverts par le SDK (à roadmapper)

D'après l'analyse exhaustive de la doc Viva crawlée (483 pages, 2026-03-11),
les endpoints suivants existent côté Viva mais ne sont **pas encore exposés**
par `viva-merchant-sdk` :

### Wallets / Banking (New API)

- `GET  /walletaccounts/v1/wallets` (déjà couvert via `Wallets::listDetailed()`)
- `POST /walletaccounts/v1/wallets` (créer wallet — `Wallets::create()`)
- `PATCH /walletaccounts/v1/wallets/{walletId}` (rename — `Wallets::update()`)
- `POST /transfers/v1/bankaccounts` (déjà couvert via `BankAccounts::link()`)
- `GET  /transfers/v1/bankaccounts` (déjà couvert via `BankAccounts::list()`)
- `GET  /transfers/v2/bankaccounts/{bankAccountId}/instructiontypes?amount={a}` ✅
- `POST /transfers/v1/bankaccounts/{bankAccountId}/fees` ✅
- `POST /transfers/v1/bankaccounts/{bankAccountId}:send` ✅
- `GET  /transfers/v1/persons/clients?mobile={m}&countryCode={cc}` ⚠️ NOT YET
- `POST /transfers/v1/wallets/{sourceWalletId}:send` ⚠️ NOT YET (wallet→wallet OTP)

### Data Services / Reconciliation

- `POST /dataservices/v1/accounttransactions/Search` ⚠️ NOT YET
  - Auth : Bearer + scope `urn:viva:payments:biservices:publicapi` + header `PersonId`
  - Filtres : `DateFrom`, `DateTo`, `WalletId`, `TypeIds[]`, `SubTypeIds[]`,
    `ExcludedSubTypeIds[]`, `IsExpired`, `AmountFrom`, `AmountTo`
  - **Idéal pour reconcilier sans dépendre des webhooks 768/769/2054**
- `GET  /dataservices/v1/accounttransactions/{accountTransactionId}` ⚠️ NOT YET
- `POST /dataservices/v1/webhooks/subscriptions` (déjà couvert via `DataServices`)

### Acquiring (cartes / refunds avancés)

- `POST /acquiring/v1/cards/tokens` ⚠️ NOT YET (génère `ct_*` réutilisable)
- `POST /acquiring/v1/transactions/{txnId}:rebate` ⚠️ NOT YET (loyalty/incentive)
- `POST /acquiring/v1/transactions/{txnId}:fastrefund` ⚠️ NOT YET (OCT rapide)
- `DELETE /acquiring/v1/transactions/{txnId}?reverseTransfers=&refundPlatformFee=` ⚠️
  (cancel marketplace tx avec params)

### POS Cloud Terminal (`/ecr/v1/*` — credentials POS APIs séparées)

Ces endpoints sont actuellement uniquement dans `viva-isv-sdk`
(`EcrTerminals`) en variante ISV (`/ecr/isv/v1/*`). Pour les marchands
self-service POS, on pourrait ajouter ces endpoints dans `viva-merchant-sdk` :

- `POST /ecr/v1/devices:search`
- `POST /ecr/v1/transactions:sale`
- `POST /ecr/v1/transactions:refund`
- `POST /ecr/v1/transactions:rebate`
- `POST /ecr/v1/transactions:fast-refund`
- `GET  /ecr/v1/sessions/{sessionId}`
- `GET  /ecr/v1/sessions?date=Y-m-d`
- `GET  /ecr/v1/sessions:abort/{sessionId}` (note: GET sur abort, à vérifier)

⚠️ Ces endpoints utilisent un **5e jeu de credentials** ("POS APIs Credentials")
distinct de Smart Checkout, ISV, Reseller, et Account Transactions.

### Native Checkout — variantes Apple Pay / Google Pay / MB WAY

- `POST /nativecheckout/v2/sibspagamentos/mbreference/sessions` ⚠️ NOT YET (PT MB REF)
- `POST /nativecheckout/v2/sibspagamentos/mbwayid/sessions` ⚠️ NOT YET (PT MB WAY)
- Apple Pay : `POST /nativecheckout/v2/chargetokens` accepte un `paymentToken`
  Apple directement (pas de `expirationYear/holderName/etc.`).
- ⚠️ La variante **ISV n'est PAS supportée** pour Apple Pay (doc explicite).

### RF Code (Grèce — référence virement bancaire)

- `POST /web2/checkout/v2/paymentsessions` (host `www.vivapayments.com`)
  ⚠️ NOT YET — génère un `RF769115659900000000xxxxx` 20-digit Greek RF code
  après création d'un `/checkout/v2/orders`.

### Issuing API (white-label card issuing)

Disponible uniquement chez certains banking partners — paths gated, doc
non publique. À implémenter sur demande :
- Authorization request, Authorization advice, Pre-authorization,
  Clearing file, Card statuses, Funds release policy.

---

## 🎟️ EventTypeIds additionnels (à ajouter aux Webhooks::EVENTS)

D'après la doc Viva, les EventTypeIds suivants existent en plus des 7 actuellement
trackés par le SDK (1796/1797/1798/1799/8193/8194/8448) :

| EventTypeId | Name | Level | Notes |
|-------------|------|-------|-------|
| **1802** | Transaction POS ECR Session Created | merchant | ECR Cloud Terminal session OK → tx créée |
| **1803** | Transaction POS ECR Session Failed | merchant | ECR session failed/cancelled/timeout/abort |
| **4865** | Order Updated | merchant | Order cancelled (Smart Checkout cancel button OU `DELETE /api/orders/{code}`) |
| **5632** | Obligation Created | marketplace (DEPRECATED) | Marketplace seller obligation created — anciennement |
| **5633** | Obligation Captured | marketplace (DEPRECATED) | Seller obligation paid |
| **768**  | Command Bank Transfer Created | merchant | Outgoing bank transfer to external IBAN created |
| **769**  | Command Bank Transfer Executed | merchant | Bank transfer executed |
| **2054** | Account Transaction Created | merchant | Wallet/bank balance change (any reason) — voir SubTypeIds |
| **8448** | Transfer Created | marketplace | Marketplace transfer made |
| (no ID, signed) | Sale Transactions (file generated) | merchant | Async file via Data Services |

### SubTypeIds importants pour event 2054 (Account Transaction Created)

Subset utile pour reconcilier les flux :

- **20-25** PayIn (Cash, Dias, Card, Voucher, SmartMoney, Iban) — top-ups
- **30-32** PayOut (Iban, Card, DirectDebit) — payouts
- **80-87** Clearance* — clearance / settlement
- **140-160** Wallet* — opérations internes wallet
- **183** **IsvAcquiringCommission** — commission ISV créditée
- **200** TransfersPlatformAmountSettlement
- **201** TransfersPlatformFeeSettlement
- **202** TransfersPlatformAmountClearance
- **203** TransfersPlatformFeeClearance

Source : `webhooks-for-payments-account-transaction-created.md` lignes 127-228
de la doc Viva crawlée (3/2026).

---

## 🔑 OAuth scopes documentés

Les seuls scopes nommés explicitement dans la doc Viva publique :

| Scope | Usage |
|-------|-------|
| `urn:viva:payments:core:api:redirectcheckout` | Smart Checkout / Payment API |
| `urn:viva:payments:biservices:publicapi` | Account Transactions Search (Data Services) — identity tokens |
| `urn:viva:payments:biservices:internalapi` | Same Data Services — access tokens (paired with `PersonId` header) |

Autres scopes (`urn:viva:payments:isv:*`, `urn:viva:payments:account:*`,
`urn:viva:payments:core:api:acquiring`, etc.) ne sont **pas nommés** dans
la doc — ils sont dérivés des credentials utilisées pour `/connect/token`.

**Implication SDK** : ne pas tenter de passer `scope=...` dans le body du
`/connect/token` — Viva dérive depuis les credentials. Juste consommer le
`scope` field de la réponse pour vérifier les permissions accordées.

---

## 💡 Test amounts pour décliner / passer en 3DS

Doc officielle Viva — utile pour les tests E2E :

| Amount (cents) | EventId | Cause |
|----------------|---------|-------|
| 9906 | 10006 | General error |
| 9907 | 10007 | Pickup card |
| 9914 | 10014 | Invalid card |
| 9941 | 10041 | Pickup card (lost) |
| 9954 | 10054 | Expired card |
| 9970 | 10070 | Call issuer |
| 9905 | 10005 | Decline (do not honor) |
| 9920 | 10020 | Decline (stolen) |
| 9951 | 10051 | Insufficient funds |
| 9957 | 10057 | Card not permitted |
| 9961 | 10061 | Withdrawal limit exceeded |
| 9979 | 10079 | Restricted card |
| 9996 | 10096 | System error |

Carte 3DS challenge : `5188 3400 0000 0060` → popup avec options Y/N/A/R/U.

---

## 📚 Source de cet audit complet

Rapport généré par sub-agent automatisé sur `/home/rony/doc-crawler/viva-docs-old/`
(crawl de 2026-03-11, 483 pages, 0 OpenAPI spec — la doc Viva utilise Redoc
client-side qui n'a pas été capturé). Combiné avec les tests prod réels
de PratiConnect (compte ISV `0119432c-...`).
