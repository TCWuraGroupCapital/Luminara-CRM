# CLAUDE.md — Luminara CRM (Human-Agentic Business OS)

Read [AGENTS.md](./AGENTS.md) first for mandatory repository conventions (`packages/Webkul/*` modularity, 10-locale translation invariant, `CHANGELOG.md` rules, and Pint/PHPStan/Pest gates).

## Operating Luminara as an Autonomous Agent

Luminara is built natively for autonomous agents (Claude Code, Antigravity, Hermes Agent, Grokbot) alongside human operators ("the agent drafts in pencil, people sign in ink" — see [docs/design.md](./docs/design.md)).

### 1. Terminal CLI (Fastest for Local / Workspace Agents)
```bash
# Preview any action safely inside a rolled-back savepoint (exit 0, zero DB writes)
php artisan luminara:act leads_create --params='{"title":"Renewal","person_name":"Dana","person_email":"dana@example.com"}' --as=admin@example.com --dry-run --json

# Execute an action with idempotency (exit 0 = completed, exit 2 = pending_approval, exit 3 = error)
php artisan luminara:act quotes_draft --params='{"subject":"Q4 Supply","person_id":1,"items":[{"product_id":1,"quantity":10,"price":180}]}' --as=admin@example.com --idempotency-key=q4-001 --json

# Issue a scoped Agent Passport with daily budget and push webhook
php artisan luminara:passport admin@example.com claude-code --scopes="leads_*,contacts_*,quotes_draft,activities_*,briefing_today,playbook_*,ledger_*"

# Seal and cryptographically verify the Data Block & Merkle Epoch chain
php artisan luminara:ledger-seal
php artisan luminara:ledger-verify --json
```

### 2. HTTP REST, MCP & A2A Discovery
- **Discovery**: `GET /llms.txt` and `GET /.well-known/agent.json`
- **Context & Playbook Rules**: `GET /api/agent/context` (Bearer token)
- **MCP (JSON-RPC 2.0)**: `POST /api/agent/mcp` (`tools/*`, `resources/*`, `prompts/*`)
- **Speculative Dry-Run**: Pass `"dry_run": true` or header `X-Luminara-Dry-Run: 1` on `POST /api/agent/actions/{action}`
- **Verified Receipts**: `GET /receipts/{receipt_code}` or `GET /api/ledger/blocks/{hash}/proof`

### 3. Quality Gates Before Finishing Any Code Change
```bash
./vendor/bin/pint --test
./vendor/bin/phpstan analyse --memory-limit=2G
php artisan test --compact
npm run test:js
bash bin/validate-skills.sh
```
