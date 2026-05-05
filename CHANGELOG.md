# Changelog

All notable changes to `qrcommunication/viva-merchant-sdk` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.5.3] - 2026-05-05

### Fixed

- **`Wallets::list()`** — the Legacy `/api/wallets` endpoint can return three
  shapes (`{Wallets: [...]}`, direct list `[...]`, single object `{...}`),
  but the previous fallback `$result['Wallets'] ?? $result['wallets'] ?? [$result]`
  produced a **double-nested array** when the response was already a list.
  Symptom: `Wallets::balance()` (which iterates `list()`) saw one element
  that was itself an array → all balance fields evaluated to 0 even when
  the merchant had funds.

  Reproduced on production merchant `0119432c-…` :
  - Real `Available` for primary wallet: `17.89 EUR`
  - `wallets->balance()` returned `0.00 EUR` (silent zero)

  Fix: detect each of the three shapes explicitly with `array_is_list()`
  and unwrap correctly. `balance()` now aggregates the real values.

  Drop-in fix, no public API change.

---

## [1.5.2] - 2026-05-05

### Fixed

- **`Wallets::list()`**, **`Account::wallets()`**, **`Account::info()`** — these
  three Resource methods hit Legacy API endpoints (`/api/wallets`,
  `/api/accounts/{merchantId}`) but were calling `HttpClient::get()` instead
  of `HttpClient::legacyGet()`. The result: requests were routed to the
  **New API host** (`api.vivapayments.com` / `demo-api.vivapayments.com`)
  with **Bearer OAuth2** instead of the **Legacy host**
  (`www.vivapayments.com` / `demo.vivapayments.com`) with **Basic Auth**.
  Every wallet/balance/account-info call returned `HTTP 404`.

  Reproduced in production on merchant `0119432c-…`:
  - `curl https://www.vivapayments.com/api/wallets` (Basic Auth) → `HTTP 200`
  - SDK `wallets->list()` → `HTTP 404` (because routed to `api.vivapayments.com`)

  Fixed by switching the three calls to `legacyGet()`, which routes through
  `Config::legacyUrl()` and uses Basic Auth as documented in `CLAUDE.md`.

  Impact for downstream applications:
  - `wallets->list()` and `wallets->balance()` now return real data
  - `account->info()` and `account->wallets()` now return real data
  - No public API/signature change, drop-in fix

### Internal

- Updated PHPDoc on `Wallets` and `Account` Resources to reflect the actual
  API surface (Legacy vs New) per endpoint, matching the SDK `CLAUDE.md`
  routing table.

---

## [1.5.1] - 2026-05-04

### Added

- **`skill/references/prod-findings.md`** — Référence consolidée des
  comportements observés en production sur compte ISV propre QR Communication
  (Merchant `0119432c-...`) : endpoints qui marchent (Basic Auth simple),
  authentification, différences avec `viva-isv-sdk`, Sources Smart Checkout,
  webhook signature handshake, codes d'erreur, test cards.

- **Section "Production gotchas"** ajoutée à `skill/SKILL.md` — référence
  rapide des endpoints testés, du split avec `viva-isv-sdk`, des subtilités
  webhook (verification key, IPs Azure, signature HMAC), test amounts pour
  trigger les déclines.

- **Roadmap des endpoints non encore couverts** documentée dans
  `prod-findings.md` (Data Services Search, Acquiring v1 cards/refunds,
  POS Cloud Terminal `/ecr/v1/*`, RF Code, Issuing API).

### Documented

- **EventTypeIds étendus** documentés (au-delà des 21 actuellement trackés
  dans `Webhooks::EVENTS`) : 768, 769, 1802, 1803, 2054, 4865, 5632, 5633,
  8448 + Sale Transactions (signed). Voir `prod-findings.md` § "EventTypeIds".

- **SubTypeIds 2054 (Account Transaction Created)** complets — incluant le
  range complet des fees, payIn, payOut, clearance, wallet ops, et obligations.
  SubTypeId 183 = `IsvAcquiringCommission` (commission ISV créditée).
  SubTypeIds 200/201/202/203 = TransfersPlatform* (marketplace settlements).

- **OAuth scopes** explicites de la doc Viva publique :
  - `urn:viva:payments:core:api:redirectcheckout`
  - `urn:viva:payments:biservices:publicapi`
  - `urn:viva:payments:biservices:internalapi`
  Note : ne JAMAIS passer `scope=...` dans `/connect/token` — Viva dérive
  depuis les credentials.

- **Test amounts pour décliner** — table complète des montants .9905-.9996
  qui retournent des EventIds 10005-10096 (insufficient funds, expired,
  pickup, etc.) — utile pour les tests E2E.

- **5 paires de credentials Viva distinctes** documentées :
  Smart Checkout, ISV, Reseller API, POS APIs, Account Transactions.

### Notes

Cette version capitalise sur :
1. Tests prod réels du compte ISV propre (PratiConnect 2026-05-04).
2. Analyse exhaustive de la doc Viva crawled (483 pages couvrant la totalité
   du portail developer.viva.com).

Aucune breaking change. Uniquement de la documentation enrichie + roadmap.

---

## [1.5.0] - 2026-05-01

### Added

- **`Resources/Messages`** — Gestion des abonnements webhook via `/api/messages/config` (Legacy API, Basic Auth).
  Méthodes : `register(eventTypeId, callbackUrl)`, `list()`, `delete(messageId)`.
  Nécessaire pour les events banking 768/769/2054 non configurables depuis le Dashboard Viva.

- **`Helpers/WebhookRegistrar`** — Helper idempotent pour l'enregistrement des events banking.
  `registerAll(callbackUrl, ?events)` enregistre les 3 events (768, 769, 2054) et transforme
  silencieusement les erreurs 400 duplicate en statut `already_exists`. Safe à relancer à chaque boot.
  Constante `BANKING_EVENTS` alignée avec `sdk-php-viva-isv` pour cohérence cross-SDK.

- **`Contracts/HttpClientInterface`** — Interface extraite de `HttpClient` (final).
  Permet le mocking dans les tests unitaires. `HttpClient` implémente cette interface.

- **`Contracts/MessagesInterface`** — Interface extraite de `Messages` (final).
  `WebhookRegistrar` dépend de `MessagesInterface` (Dependency Inversion). Permet le mocking.

- **`HttpClient::legacyDeletePath(string $path)`** — Nouvelle méthode : DELETE sur Legacy API
  à partir d'un path (compose l'URL complète en interne). Complément de `legacyDeleteUrl(fullUrl)`.

- **`VivaClient::messages()`** — Accès lazy à la Resource `Messages`.

- **`VivaClient::webhookRegistrar()`** — Accès lazy au helper `WebhookRegistrar`.

- **`phpstan.neon`** — Configuration PHPStan level 6 avec `treatPhpDocTypesAsCertain: false`
  (nécessaire pour les tableaux à clés string dynamiques, conforme recommandation PHPStan).

- **Tests unitaires** :
  - `tests/Unit/Resources/MessagesTest.php` — 5 tests couvrant register, list, delete.
  - `tests/Unit/Helpers/WebhookRegistrarTest.php` — 8 tests couvrant registerAll (success,
    duplicate 400, error_code 1100, subset d'events, mixed).

### Changed

- `VivaClient` : 2 nouvelles méthodes lazy (`messages()`, `webhookRegistrar()`). Zero breaking change —
  les propriétés publiques `readonly` existantes sont inchangées.
- `HttpClient` : implémente `HttpClientInterface`. Zero breaking change — aucune signature modifiée.
- Documentation (`README.md`, `CLAUDE.md`, `AGENTS.md`, `skill/SKILL.md`) : section "10. Messages",
  section "Enregistrement des webhooks banking", mise à jour Architecture, Pièges à éviter.

---

## [1.3.5] — 2026-03-18

Version initiale documentée. SDK couvrant 9 Resources : Orders, Transactions, Sources, Wallets,
BankAccounts, NativeCheckout, DataServices, Webhooks, Account.
