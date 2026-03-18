# Viva Wallet Merchant SDK — Agent Instructions

See [CLAUDE.md](CLAUDE.md) for complete SDK architecture, patterns, and implementation guidelines.

## Quick Reference

- **Package**: `qrcommunication/viva-merchant-sdk`
- **Namespace**: `QrCommunication\VivaMerchant\`
- **Entry point**: `VivaClient`
- **Pattern**: Resource pattern (`$viva->orders->create()`)
- **Two APIs**: Legacy (Basic Auth, PascalCase) + New (Bearer, camelCase)
- **Amounts**: Always in cents (int)
- **PHP**: 8.2+ strict types
