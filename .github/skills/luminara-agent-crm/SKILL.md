---
name: luminara-agent-crm
description: Use when connecting external AI agents (Claude Code, Antigravity, Grokbot, Hermes Agent) to Luminara CRM over REST (/api/agent), MCP v2 (/api/agent/mcp), or CLI (luminara:act --json), or when developing Agent Passports, savepoint dry_run previews, Edit-to-Teach Playbook Memory, Verifiable Data Blocks (Webkul\Ledger), or Telegram Mini App WebAuthn Passkeys (Webkul\Tma).
license: MIT
---

# Luminara Agentic CRM & Verifiable Ledger Skill (`luminara-agent-crm`)

## Purpose

Guides AI agents (Claude Code, Antigravity, Grokbot, Hermes Agent) and developers operating or extending Luminara CRM's three agentic packages:

- `Webkul\Ledger` — Append-only RFC 8785 canonical Data Blocks (`LUM-<ULID>` receipts), Ed25519 + ES256 signatures, and hourly RFC 6962 binary Merkle Epochs.
- `Webkul\Agent` — 26-action transport-agnostic CRM execution engine (`ActionExecutor`), Agent Passports, savepoint `dry_run`, Edit-to-Teach Playbook Memory, MCP v2, CLI, and Morning Briefing (`/admin/agent/briefing`).
- `Webkul\Tma` — Stateless Telegram Mini App (`/tma`) with `Telegram.WebApp.initData` HMAC-SHA256 verification, call-bound WebAuthn biometric Passkeys (`ES256` & `Ed25519`), paired Telegram Bot intake webhook, and `TonAnchorDriver`.

Preserve the strict one-way dependency DAG: `Webkul\Ledger` ← `Webkul\Agent` ← `Webkul\Tma`. Never modify core Krayin CRM files.

---

## 1. Discovery & Connection Transports

1. **Machine Manifests**:
   - `GET /llms.txt` — Human- and LLM-readable capability guide.
   - `GET /.well-known/agent.json` — Structured JSON manifest of transports, auth headers, and verification endpoints.
2. **Scoped Agent Passports**:
   - Issue via UI (`/admin/agent/briefing`) or CLI:
     ```bash
     php artisan luminara:passport admin@example.com claude-code --scopes="*" --daily-limit=1500
     ```
   - Pass `Authorization: Bearer <token>` on REST and MCP requests. Passports enforce `allowed_actions` glob scopes, daily call budgets (including `dry_run`), `auto_approve_value_ceiling`, and trust modes (`standard`, `always_confirm`, `earned_auto`).
3. **REST API (`/api/agent`)**:
   - `GET /api/agent/context` — Returns workspace schema, playbook rules, and actions permitted for the authenticated user + passport.
   - `POST /api/agent/actions/{action}` — Executes or stages an action (`Idempotency-Key` header supported; `X-Luminara-Dry-Run: 1` runs inside a rolled-back DB savepoint).
   - `GET /api/agent/calls/{id}` — Polls a `pending_approval` call until signed or rejected.
   - `GET /api/agent/events?since_id=0` — Incremental feed of settled calls and receipts.
4. **Model Context Protocol v2 (`POST /api/agent/mcp`, JSON-RPC `2.0`, `protocolVersion` `2025-06-18`)**:
   - `tools/list`, `tools/call` — Exposes permitted CRM actions with `<untrusted_crm_data>` prompt-injection fencing.
   - `resources/list`, `resources/read` — Reads `luminara://briefing/today`, `luminara://memory/playbook`, `luminara://ledger/head`, and `luminara://context/schema` (filtered by caller ACL and passport scope).
   - `prompts/list`, `prompts/get` — Structured workflow prompts (`intake`, `quote`, `followup`, `morning_briefing`).
5. **Headless CLI**:
   ```bash
   php artisan luminara:act briefing_today --as=admin@example.com --passport=claude-code --json
   php artisan luminara:act intake_capture --as=admin@example.com --params='{"raw_text":"Met Nadia at Horizon — $14k annual plan"}' --dry-run --json
   ```

---

## 2. 26 Atomic CRM Actions & Friction Tiers

- **Silent Reads**: `leads_search`, `leads_get`, `contacts_search`, `contacts_get`, `products_search`, `quotes_search`, `quotes_get`, `activities_list`, `briefing_today`, `playbook_read`, `ledger_verify`, `ledger_history`.
- **Pencil Drafts (Auto-Execute + `reviewed_at` Queue + `LUM-*` Receipt)**: `intake_capture`, `leads_create`, `leads_update`, `contacts_upsert_person`, `contacts_upsert_organization`, `activities_log_note`, `activities_schedule`, `activities_complete`, `leads_move_stage` (single lead), `quotes_draft` (`<= 15%` discount).
- **Signature-Gated (`202 pending_approval` + TOCTOU Snapshot Re-Check)**: `leads_move_stage` (`> 1` lead), `quotes_draft` (`> 15%` discount), `contacts_merge_persons`, `playbook_propose_rule`, `leads_delete`, `quotes_delete` (or any call exceeding the passport's `auto_approve_value_ceiling` / `always_confirm` mode).

---

## 3. Verifiable Receipts & Passkey Co-Signing

- Every settled write and CRM entity mutation appends a JCS-canonical `DataBlock` with a `LUM-<ULID>` receipt.
- Verify any receipt publicly at `GET /receipts/{receiptCode}` or `GET /api/ledger/blocks/{codeOrHash}/proof` (zero PII exposed publicly; scoped `entity_summary` returned only to authorized, MFA-verified viewers).
- Maintain chain health via `php artisan luminara:ledger-seal` (scheduled hourly) and `php artisan luminara:ledger-verify`.
- In `/tma` or `Settings > Other Settings > Telegram Mini App`, owners enroll WebAuthn Passkeys (`ES256` or `Ed25519` via CBOR `attestationObject`) and co-sign approvals with call-bound challenges (`call_id`), recording their hardware signature directly on the resulting `DataBlock`.
