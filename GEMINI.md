# GEMINI.md — Luminara CRM (Antigravity & Gemini Agent Guide)

Follow [AGENTS.md](./AGENTS.md) and [CLAUDE.md](./CLAUDE.md) for all architecture, localization, testing, and agent-execution rules.

## Key Module Boundaries (`packages/Webkul/*`)
- **`Webkul\Ledger`**: Append-only RFC 8785 JCS canonical Data Blocks (`data_blocks`), `ledger_heads` concurrency lock, RFC 6962 binary Merkle Epochs (`ledger_epochs`), Ed25519 + ES256 cryptographic receipts, and pluggable `AnchorDriver` (`LocalSignedAnchorDriver` default; `TonAnchorDriver` in `Webkul\Tma`).
- **`Webkul\Agent`**: 26-action transport-agnostic execution spine (`ActionExecutor` with savepoint `dry_run`, TOCTOU re-checks, idempotency, and Edit-to-Sign), **Agent Passports**, **Edit-to-Teach Playbook Memory** (`business_identity`, `pricing_guardrails`, `communication_voice`, `active_priorities`), MCP v2 (`tools`, `resources`, `prompts`), `luminara:act --json` CLI, outbound HMAC webhooks, `/llms.txt`, `/.well-known/agent.json`, and Owner Simple Mode (`/admin/agent/briefing`).
- **`Webkul\Tma`**: Stateless Telegram Mini App (`/tma`) authenticated via `Telegram.WebApp.initData` HMAC-SHA256, WebAuthn/Biometric Passkey signing (`user_passkeys`), paired Telegram Bot intake webhook (`/api/tma/bot/webhook`), and `TonAnchorDriver`.
- **`Webkul\Security` / `Webkul\Experiment` / `Webkul\Gourwest`**: Enterprise MFA, immutable security audit log, Bayesian propensity scoring, and 7-country Golden Record customer hub.
