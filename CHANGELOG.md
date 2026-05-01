# Changelog

All notable changes to `qrcommunication/viva-merchant-sdk` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Versions follow [Semantic Versioning](https://semver.org/).

---

## [1.4.0] - 2026-05-01

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
