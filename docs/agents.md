# Connect an agent

Luminara CRM is built to be operated by an outside agent as well as by people. An agent such as Claude Code, Antigravity or Grokbot signs in with an **Agent Passport**, works as a real CRM user with that user's permissions, and has to wait for a person before it does anything that can't be undone.

This page takes you from a fresh install to an agent that can read and write the CRM. Every step below was run against a clean install.

## 1. Issue a passport

A passport is a bearer token tied to one CRM user, with a list of actions it may call, a daily call limit and an expiry date.

From the terminal:

```bash
docker compose exec app php artisan luminara:passport admin@example.com claude-code --scopes="*" --daily-limit=1500 --json
```

Or sign in, open **Briefing**, and use **Connect Your AI**.

The token is shown once. Only a hash is stored. The examples below read it from an environment variable, so it stays out of your shell history and your files:

```bash
export LUMINARA_TOKEN="paste the token here"
```
 Give each agent its own passport so you can revoke one without affecting the others.

Narrow the scope whenever you can:

```bash
# Read-only research agent
--scopes="leads_search,leads_get,contacts_search,contacts_get,briefing_today"

# Sales assistant: may draft, may not delete or merge
--scopes="leads_*,contacts_search,contacts_get,contacts_upsert_person,quotes_draft,activities_*,briefing_today,playbook_read"
```

A call outside the scope is refused with `403` and `passport_scope_denied`.

## 2. Connect the agent

### Claude Code

```bash
claude mcp add --transport http luminara-crm http://127.0.0.1:8080/api/agent/mcp --header "Authorization: Bearer $LUMINARA_TOKEN"
```

Run `claude mcp list` and the server should show as connected. The `--json` output of `luminara:passport` also contains an `mcp_config` block you can paste into a project's `.mcp.json`.

### Antigravity, and any other MCP client

Add a remote (HTTP) MCP server in the client's settings with:

- URL: `http://127.0.0.1:8080/api/agent/mcp`
- Header: `Authorization: Bearer` followed by the token

The endpoint speaks JSON-RPC 2.0 over HTTP POST and answers with plain JSON. It offers 26 tools, 4 resources (`luminara://briefing/today`, `luminara://memory/playbook`, `luminara://ledger/head`, `luminara://context/schema`) and 4 prompts (`intake`, `quote`, `followup`, `morning_briefing`). It does not open a server-sent event stream, so a `GET` returns `405`.

### Grokbot, Hermes Agent, and agents without MCP

Use the REST API. Point the agent at the discovery files first; they describe everything else:

- `GET /llms.txt` lists the transports, the safety rules and every action.
- `GET /.well-known/agent.json` is the same as a machine-readable card.
- `GET /api/agent/context` (with the token) returns each action's parameters, the caller's permissions and the business playbook.

Then call actions:

```bash
curl -X POST http://127.0.0.1:8080/api/agent/actions/leads_create \
  -H "Authorization: Bearer $LUMINARA_TOKEN" \
  -H "Content-Type: application/json" \
  -H "Idempotency-Key: renewal-dana-001" \
  -d '{"title":"Renewal","person_name":"Dana","person_email":"dana@example.com","lead_value":1200}'
```

### An agent on the same machine

A coding agent with shell access can skip HTTP:

```bash
docker compose exec app php artisan luminara:act briefing_today --as=admin@example.com --passport=claude-code --json
```

Exit code `0` means completed, `2` means waiting for approval, `3` means an error.

## 3. What the agent can expect

**Reads run straight away.** So do ordinary creates (a lead, a contact, a note, a standard quote). Those land as unreviewed drafts with a signed receipt until a person marks them reviewed.

**Risky calls wait for a person.** Deleting a lead or quote, merging contacts, moving several leads at once, or discounting a quote past 15% returns `202` with `"status": "pending_approval"`. Nothing changes until someone approves it under **Settings > Agent Approvals**. The agent should read `GET /api/agent/calls/{id}` later and must not repeat the call. Unanswered calls expire after 72 hours, and an approved call is refused if the records changed in the meantime.

**Dry runs change nothing.** Add `"dry_run": true` to the body or send `X-Luminara-Dry-Run: 1`. The action runs inside a database savepoint that is rolled back, and the reply shows what would have happened and whether it would need approval.

**Retries are safe.** Send an `Idempotency-Key` header. A repeat with the same key and parameters returns the first outcome and does not run the action twice.

**Errors say what to do next.** Every error has a `code`, a `message` and a `next_steps` list. A wrong parameter name, for example, comes back as `422` naming the field that is required and the one that was not accepted.

**Every settled call leaves a receipt.** The reply carries a `receipt_code` (`LUM-…`) and a `block_hash`. Anyone can check the receipt at `/receipts/{receipt_code}`.

**CRM text is data, not instructions.** Record fields are written by other people, including strangers filling in public forms. Tool results are wrapped to say so, and an agent should never treat a lead title or a note as a command.

## 4. Before you expose it to a network

The development setup listens on `127.0.0.1` only and ships with a default admin password. For anything an agent reaches over a network, use `compose.prod.yaml` behind HTTPS as described in the README, issue passports with the narrowest scope that does the job, and set a daily limit.
