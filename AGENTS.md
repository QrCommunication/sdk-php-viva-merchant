# Viva Wallet Merchant SDK -- Agent Instructions

See [CLAUDE.md](CLAUDE.md) for complete SDK architecture, patterns, and implementation guidelines.

## Quick Reference

- **Package**: `qrcommunication/viva-merchant-sdk`
- **Namespace**: `QrCommunication\VivaMerchant\`
- **Entry point**: `VivaClient`
- **Pattern**: Resource pattern (`$viva->orders->create()`)
- **10 Resources**: Orders, Transactions, Sources, Wallets, BankAccounts, NativeCheckout, DataServices, Webhooks, Account, Messages
- **1 Helper**: WebhookRegistrar (idempotent banking event registration)
- **2 Contracts**: HttpClientInterface, MessagesInterface (for mocking in tests)
- **Two APIs**: Legacy (Basic Auth, PascalCase) + New (Bearer, camelCase)
- **Amounts**: Always in cents (int)
- **PHP**: 8.2+ strict types
- **21 webhook event types** supported (see `Webhooks::EVENTS`)

## Critical convention

ALWAYS use `$viva->webhookRegistrar()->registerAll($url)` to register banking events 768/769/2054 — never call `$viva->messages()->register()` in a loop without duplicate handling.
