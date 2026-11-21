# CHANGELOG for 2.2

This changelog consists of the bug & security fixes and new features being included in the releases listed below.

## **v2.2.9 (Upcoming)**

* [security] Added `ContentSecurityPolicy` middleware (`SECURITY_CSP=enforce|report-only|off`) sending a default `Content-Security-Policy` on HTML pages while allowing Telegram WebApp framing/script on `/tma*` and preserving embeddable `/web-forms/forms/*/form.html`.

* [feature] Added `Webkul\Privacy`: personal-data export and erasure for contacts (Australian Privacy Principle 11.2 / GDPR Art. 17), with attested ledger redaction.
    * The person page's Privacy panel (new `contacts.persons.privacy` permission; erasing needs the person's name typed) and the CLI (`luminara:privacy:export`, `luminara:privacy:erase --confirm="<name>"`) export a contact's full record and erase personal fields, emails, attachments, standalone activities, custom attributes, customer facts, deal-title mentions and agent call payloads while keeping deals and quotes linked to `Erased person #id`.
    * `LedgerRedactor` attests redacted `DataBlock` payloads with a signed `ledger.redaction` block (`height`, `block_hash`, `payload_sha256`) before scrubbing personal fields, so `LedgerVerifier` continues to verify the full hash chain and Merkle proofs.
    * Strings are present in all 10 locales.

* [feature] Releases without downtime: `bin/release.sh` with the new `compose.prod.rolling.yaml`.
    * The script backs up and verifies the database, starts the new version next to the old one, waits until it reports healthy, stops the old one gracefully, replaces the worker and the scheduler and migrates every workspace. If the new version fails to start, it is removed and the old one keeps serving.
    * The image that was serving is kept as `<project>:rollback-previous`. The same script applies changed settings.
    * `compose.prod.rolling.yaml` removes the app's host port, so two app containers can run at once. It is for installs behind the Cloudflare tunnel; `compose.prod.yaml` alone still publishes `127.0.0.1:APP_PORT`.
    * `compose.prod.yaml`: compiled Blade views now live inside each container (`VIEW_COMPILED_PATH`), not on the shared storage volume; the app gets 60 seconds to finish requests when it stops; its health check runs every 3 seconds while it starts.
    * A release may only add tables and columns, because the new version migrates the main database while the old one still serves. `docs/deployment-cloudflare-github.md` has the rules.

* [fixed] The queue worker and the scheduler in `compose.prod.yaml` now stop on `SIGTERM`. The Apache base image stops containers with `SIGWINCH`, which PHP ignores, so every restart killed the worker after 10 seconds, in the middle of whatever job it was running. The worker now finishes its job first (up to 100 seconds).

* [enhancement] Added `docs/deployment-oracle-free-tier.md` and `bin/server-bootstrap.sh`, for moving a running production stack to a free Oracle Cloud Ampere (ARM) server in Australia with no change of address. The script prepares Ubuntu with Docker Engine, automatic security updates and swap, and opens no inbound ports, because the Cloudflare tunnel connects outwards. The guide covers the server settings, a native ARM build, copying the database and storage volumes, switching the tunnel without two live copies, checks and rollback.

* [feature] Added `Webkul\Legal`: `/terms`, `/privacy`, `/legal/dpa` and `/legal/subprocessors`, behind `LUMINARA_LEGAL_PAGES` (off by default).
    * Pages render the operator's own Markdown from `storage/app/legal` (`terms.md`, `privacy.md`, `dpa.md`, `subprocessors.md`). No legal text ships with the code, and a missing file answers 404.
    * Raw HTML in a text is escaped and unsafe links are dropped.
    * The sign-in page links to the pages that exist.
    * While on and `terms.md` exists, workspace sign-up requires a ticked acceptance. The landlord `workspaces` table records `terms_version` (the `LUMINARA_LEGAL_VERSION` label plus a hash of the Terms and Privacy Policy as accepted) and `terms_accepted_at`.
    * Strings are present in all 10 locales.

* [release] **1.0.0-beta.3** ships per-business email (below): each business sends through its own SMTP account and reads replies from its own mailbox, and a hosted business can no longer send through the operator's mailbox.

* [feature] Each business sends its own email. Settings > Configuration > Email > Outgoing mail takes the business's SMTP account (server, port, username, encrypted password, sender name and address), with a "Send test email" button that mails the signed-in person.
    * Composed emails and replies, quotes, automation emails and campaigns go out through that account, from the business's own address. Replies to composed emails go back to that address.
    * A hosted business (any workspace other than the main one) without an account of its own cannot send. It never falls back to the server's `MAIL_*` mailbox. A single-business install, and the main business of a multi-business one, keep using `MAIL_*` until they save an account.
    * A hosted business's mail server must resolve to public addresses only and use port 25, 465, 587 or 2525. The connection goes to the checked address, so the name cannot be re-pointed at the server's own network in between.
    * A composed email that fails to send now stays in the outbox with the reason shown. Before, the failure was hidden and the screen said it was sent.
    * Each business reads replies from its own IMAP mailbox (Settings > Configuration > Email > IMAP): `inbound-emails:process` now runs for every workspace every five minutes, and only reads a mailbox the business saved itself, never the server's `IMAP_*` values.
    * [security] The IMAP settings screen no longer pre-fills the username and password from `IMAP_USERNAME` / `IMAP_PASSWORD`. The screen prints its values into the page, so every hosted business could read the server's mailbox login.
    * The browser tests' workflow writes mail to the log (`MAIL_MAILER=log`), since a failed send is no longer reported as sent.
    * Strings are present in all 10 locales.

* [feature] Off-site backups and a restore drill.
    * The optional `backup-sync` service in `compose.prod.yaml` (`--profile offsite`, pinned to `rclone/rclone:1.68.2`) copies `storage/app` to any S3-compatible bucket every hour. That covers the database backups of every workspace and the uploaded files.
    * Files are encrypted on the host first (`rclone crypt`), so the bucket sees neither names nor contents. The service only ever copies, so a deletion on the host cannot remove the off-site copy, and off-site archives are pruned after `BACKUP_OFFSITE_RETENTION_DAYS` (35).
    * New `luminara:backup-restore --database=<scratch> [--migrate]` verifies a backup, restores it into a database that holds no data and checks every table's row count against the signed manifest. It refuses the database it runs against and any database with existing rows, so it can never overwrite live data.
    * `docs/deployment-cloudflare-github.md` §4.1–4.2 has the setup, the quarterly drill and a drill log. The new keys are listed in `.env.example`.

* [fixed] `compose.prod.yaml` now passes `MAIL_FROM_NAME` (default "Luminara CRM") to the app, worker and scheduler. Before, production mail went out with no sender name, so inboxes showed only the bare address.

* [release] **1.0.0-beta.2** ships Workspaces (one database per business, sign-up, email confirmation, `/w/{slug}` public links and the operator commands below), the Workbench mobile menu, error pages that return their real status code, and the production tunnel fix. The README documents hosting several businesses on one install.

* [feature] Added `Webkul\Workspace`: sign-up and one separate database per business, behind `LUMINARA_WORKSPACES` (off by default, so a single-business install is unchanged). With it on, `/signup` (`LUMINARA_SIGNUP=off|invite|open`, rate-limited, capped by `LUMINARA_WORKSPACE_LIMIT`) reserves a workspace and a queued job creates its database, runs the migrations and seeders, and installs the owner as administrator in place of the seeded default account. Sign-in looks the email up in a directory in the main database, checks the password inside that business's database, and binds the session to the workspace only for the account that was actually authenticated; every later request switches the default connection, the cache prefix and the `local`/`public` disks to that workspace before route bindings, two-factor checks or controllers run. Because the whole database is switched, leads, contacts, products, pipelines, settings, agent memory, agent history, API tokens and the ledger are separate without any per-query filter. Teammates the owner adds in **Settings > Users** are registered in the directory automatically, and an email address can belong to one business only. API clients name their workspace in the `X-Luminara-Workspace` header, which only selects the database their bearer token is checked against. Queued jobs run in the workspace that dispatched them, `LUMINARA_WORKSPACE=<slug> php artisan …` runs any command inside one, and `luminara:workspace:create`, `luminara:workspace:list` and `luminara:workspace:run "<command>"` cover provisioning and fleet-wide commands such as `migrate --force`; the hourly ledger seal and daily backup are repeated for every workspace. The database user needs ``GRANT ALL ON `<DB_DATABASE>\_ws\_%`.*``. Strings are present in all 10 locales.

* [feature] Workspaces: public pages now work for every business. Web forms (script, page and submission), receipt pages, the ledger proof API, experiment assignment and tracking, and Gourwest webhooks each gain a second address, `/w/{slug}/...`, that names the business; links to them generated inside a workspace use it automatically, including links built with `url()`. A request that arrives this way is always served as a signed-out visitor and leaves the browser's own session untouched, so someone signed in to one business can open another's form without their identity being looked up in the wrong database. The Telegram bot and mini app still serve the main workspace only (one bot per deployment).

* [feature] Workspaces: sign-up can require a confirmed email address (`LUMINARA_SIGNUP_VERIFY_EMAIL`; on by default for `open` sign-up, off for `invite`). Nothing is built until the owner opens the emailed link, which is valid for 24 hours and stored only as a hash; signing up again with the same address replaces the unconfirmed attempt, and `luminara:workspace:prune` (hourly) removes those never confirmed. It needs working outgoing mail. New operator commands: `luminara:workspace:suspend {slug}` (and `--resume`) ends every session of a business and blocks sign-in while keeping its data; `luminara:workspace:delete {slug} --confirm={slug}` permanently removes a suspended business's database, files and sign-ins.

* [fixed] With `APP_DEBUG=false`, HTML error pages were sent with status `200` whatever the error. They now carry the status they show (401, 403, 404, 500 or 503), so monitors, crawlers and webhook senders can tell a failure from a success. JSON error responses already did.

* [enhancement] Workspace administrators see a short "first steps" card on the dashboard for the first two weeks (add a lead, invite the team, turn on two-factor sign-in, publish a web form, connect an agent), which they can hide. The mobile menu drawer now matches the Workbench sidebar: labelled groups, the same quiet active state and 44px rows. The sign-in page tagline no longer names a single customer. All new strings are present in all 10 locales.

* [enhancement] Redesigned the admin shell and the Leads board ("Workbench", described in `docs/design.md`, prototype in `docs/prototype/v3/index.html`). The sidebar is now full height with the logo, labelled groups (Overview, Sales, Agent, with Help, Settings and Configuration pinned to the bottom), inline sub-menus for Mail and Contacts, and a collapse button beside the logo; menu items added by other packages fall into the Sales group automatically. The header sits beside the sidebar and keeps its 60px height, so sticky page headers are unaffected. The stock grey palette is tinted toward the brand hue in light and dark, the admin font is IBM Plex Sans (IBM Plex Sans Arabic for `ar` and `fa`), buttons are flatter, and keyboard focus is always visible. The Leads board gains an "Open pipeline, to scale" strip computed from the stage totals the board already loads, stage columns with a colour swatch, count and value, and cleaner lead cards with the value in the card header. New strings are present in all 10 locales. No routes, permissions or data change.

* [security] In `compose.prod.yaml` the Cloudflare tunnel container is now only on the `tunnel` network. It was also on `egress`, where Docker could resolve `app` to an untrusted address, so Apache ignored `CF-Connecting-IP` and `X-Forwarded-Proto`: the app built `http://` links (including the agent discovery card), sent no HSTS header, and saw every visitor as one IP address, which made login lockout and rate limits shared by everyone. The tunnel network already has outbound internet access.

* [release] Luminara has its own release number, starting with **1.0.0-beta.1**, the first public beta. `Core::LUMINARA_VERSION` and `core()->luminaraVersion()` report it on the agent discovery card, `/api/agent/context`, the MCP `serverInfo` and the admin header; `core()->version()` and `krayin-crm:version` still report the Krayin base (2.2.8). The README gains CI and licence badges, a comparison with typical CRM AI, and a beta status section with the known limits.

* [enhancement] Added `docs/agents.md`, a guide to connecting an outside agent (Claude Code, Antigravity, Grokbot or anything that speaks MCP over HTTP or plain REST), written from a run against a clean install. `luminara:passport --json` now includes `"type": "http"` in its `mcp_config`, so the block can be pasted into a Claude Code `.mcp.json` unchanged.

* [enhancement] Added `CONTRIBUTING.md` and README screenshots. Security reports now go through GitHub private vulnerability reporting only; the email address was removed from `SECURITY.md` and the issue template. Reworded a few passages in the README, `docs/design.md` and the v3 prototype that only made sense inside the team.

* [fixed] The Playwright workflow installs only Chromium, the one browser the suite runs, and retries the install up to three times. A run had failed in every shard because the Ubuntu package mirrors timed out during setup.

* [fixed] `/.well-known/agent.json` now writes its endpoint URLs with plain slashes (`/api/agent/mcp`) instead of JSON-escaped ones (`\/api\/agent\/mcp`). The content is the same to a JSON parser; the Docker smoke test and anyone reading the card with `grep` or by eye now see ordinary URLs.

* [security] The Docker image now applies Debian security upgrades while it builds. The Trivy gate had been silently skipped because its action tag no longer resolved; once it ran it reported 160 fixable HIGH and CRITICAL findings in the base image's Apache and kernel-header packages. A rebuilt production image scans clean.

* [fixed] CI is green on a fresh repository. The Playwright sign-in fixture now waits for the post-login redirect and opens the dashboard, so the first test in a shard no longer races it or starts on Briefing; the theme and notification specs target the sidebar logo and the header's notification feed after the Workbench redesign. PHPStan is given a throwaway `APP_KEY` in CI, the Trivy action is pinned to the v0.36.0 commit (the `0.30.0` tag no longer exists), and `.gitleaks.toml` allowlists the two ledger tests that build a PEM from the published RFC 7515 example key.

* [enhancement] Added `bin/public-export.sh` and `.public-exclude`. The script builds the public snapshot from a private commit without touching the working tree: it drops the listed private paths, points repository links at the public repository, stops if private wording is still present, and records the result as one commit on a local branch. Nothing is pushed by the script.

* [fixed] Test isolation is now enforced without breaking CI. `phpunit.xml` previously hard-coded `DB_DATABASE=luminara_crm_test` via `<env force="true">`, which was both ineffective locally (Laravel's immutable env repository reads `$_SERVER` first) and would have overridden the `krayin` database CI configures in `.env`. Isolation now lives in `tests/bootstrap.php`: it only redirects an exported `DB_DATABASE=luminara_crm` to `luminara_crm_test` (honouring `LUMINARA_TEST_DB`), and leaves CI untouched. `tests/TestCase.php` fails closed if the live demo database is ever resolved. The demo database was checked afterwards and contains no test residue.

* [feature] Added **Customer Facts Memory** behind `LUMINARA_AGENT_MEMORY` (off by default). When enabled, three `AgentAction` classes — `memory_recall` (read-only), `memory_remember_fact` and `memory_retract_fact` (both `APPROVAL_ALWAYS`) — appear over REST, MCP, the CLI and `/api/agent/context` with no transport code. Facts are short (<=280 characters), human-approved records about a person or organisation, scoped by `bouncer()->scopeToAuthorizedUsers`, filterable by category, expiry, `team`/`private` visibility and status (`active`/`superseded`/`retracted`), and never hard-deleted. Every approved row records the proposing user, the signer and the originating `agent_action_calls.id` through a new `ActionCallContext` scope opened by the executor (dry runs open no scope). Hard validation blocks obvious payment-card, government-identifier and health details before they reach the approval queue, and rejects exact duplicates. The consumer loop is closed: `contacts_get` returns `customer_facts`, and the Assistant injects approved facts into the model as a clearly labelled `<approved_customer_facts>` data block in a user message, never into the system instructions. Recall deliberately uses plain word matching rather than MySQL FULLTEXT so facts remain visible inside a rolled-back dry-run savepoint. With the flag off, the action registry, prompts and responses are byte-for-byte unchanged; the migration only adds an unused table.

* [feature] Bring your own keys from the admin panel. **Settings > Configuration > AI Assistant** takes an API key, model and optional API address for the in-app Assistant and the Desk, and **Settings > Configuration > Telegram** takes a bot token, bot username and webhook secret for `Webkul\Tma`. Keys and secrets are encrypted at rest by `CoreConfigEncryptor`, and all strings are translated into the 10 locales. The server's environment still wins, so `.env`-configured deployments are unchanged. For the Assistant, a `LUMINARA_ASSISTANT_API_KEY` in `.env` makes the key, model and address all come from `.env`, so an address typed into settings can never receive the server's key. For Telegram, each `TELEGRAM_*` value set in `.env` wins over its field. The new `AssistantSettings` and `TelegramSettings` resolvers replace direct `config()` reads.

* [fixed] An empty `LUMINARA_ASSISTANT_BASE_URL` no longer turns off the Anthropic refusal fallback. The empty value was already treated as the default endpoint for the client, but not when deciding whether to send the fallback.

* [enhancement] `.env.example` now lists every key a self-hosted install supplies itself, each blank with a short comment: added `APP_PORT` (read by Compose and `Launch-Luminara-Desktop.cmd`), `LUMINARA_ADMIN_EMAIL` and `LUMINARA_ADMIN_PASSWORD` for `luminara:provision`, and `GOURWEST_WEBHOOK_SECRET`, and replaced the `IMAP_USERNAME`/`IMAP_PASSWORD` sample values with blanks. Added `docs/open-source-release-plan.md`, the secret-scan results and step-by-step plan for publishing the public repository while private deployments keep their own keys.

* [enhancement] The Experiments results table now shows a **95% CI** column: the Newcombe-Wilson confidence interval for each variant's conversion-rate difference against control, in percentage points. The column heading is present in all 10 locales.

* [security] Hardened `Webkul\Agent`, `Webkul\Ledger`, and `Webkul\Tma` boundaries: enforced daily passport call budgets and security audit logging on `dry_run` previews, re-authorized `edited_params` against the approver's scope and concurrent DB drift before signing, pinned resolved DNS IPs via `CURLOPT_RESOLVE` on outbound agent webhooks and TON relayer requests to prevent DNS-rebinding SSRF, gated `ledger_history` and `/api/ledger/blocks/{codeOrHash}/proof` behind MFA session checks, fail-closed `view_permission` defaults, and tombstone ownership checks, hid `canonical_payload` on admin Data Vault JSON feeds, bound TMA WebAuthn challenges 1:1 to pending `call_id`s, required biometric Passkey assertions when approving on Passkey-enrolled accounts, and added server-side CBOR `attestationObject` public-key extraction (`ES256` and `Ed25519`).

* [fixed] Reused existing scoped contacts and assigned contact ownership when `leads_create` creates or matches `person_email`, enforced `.edit` permissions when `contacts_upsert_person` and `contacts_upsert_organization` update existing records, filtered MCP v2 `resources/list` and `prompts/get` by caller permissions, added one-line intake capture on Morning Briefing (`/admin/agent/briefing`) and a live **Stop** control in the Desk's Ask sheet, recorded pre-delete tombstones on activity deletions, and eliminated cold-start InnoDB gap-lock contention and transaction-held HTTP anchor locks in `Webkul\Ledger`.

* [feature] Added the `Webkul\Ledger` verifiable data ledger (`Settings > Other Settings > Data Vault`, `settings.other_settings.ledger` permission). Every CRM record change (leads, contacts, organizations, quotes, products, activities, and pre-delete tombstones) and settled agent action is recorded as an append-only, SHA-256 hash-chained, Ed25519/ES256-signed Data Block (`LUM-<ULID>` receipt code) with secret scrubbing and RFC 8785 canonical JSON. Hourly `luminara:ledger-seal` seals pending blocks into RFC 6962 binary Merkle Epochs through a pluggable `AnchorDriver` (`local` default; `ton` ready), `luminara:ledger-verify` checks chain and Merkle integrity, and `/receipts/{receiptCode}` plus `/api/ledger/blocks/{codeOrHash}/proof` let anyone verify a receipt and its Merkle proof without exposing customer PII.

* [feature] Upgraded `Webkul\Agent` to a 26-action, multi-agent CRM execution platform for Claude Code, Antigravity, Grokbot, and Hermes Agent:
  * Expanded atomic actions across Leads (`leads_update`), Intake (`intake_capture` plain-text/voice deal capture with scoped contact deduplication), Contacts (`contacts_search`, `contacts_get`, `contacts_upsert_person`, `contacts_upsert_organization`, `contacts_merge_persons`), Products (`products_search`), Quotes (`quotes_search`, `quotes_get`, `quotes_draft` with automatic approval above 15% discount, `quotes_delete`), Activities (`activities_list`, `activities_log_note`, `activities_schedule`, `activities_complete`), Briefing (`briefing_today`), Memory (`playbook_read`, `playbook_propose_rule`), and Ledger (`ledger_verify`, `ledger_history`).
  * Added savepoint `dry_run` previews (`X-Luminara-Dry-Run: 1`, `dry_run: true`, or `--dry-run`) that return before/after snapshots with zero persisted mutations, **Agent Passports** (`luminara:passport`) with action allowlists, daily call budgets, value ceilings, trust streaks, and SSRF-guarded HMAC-SHA256 outbound webhooks, and **Edit-to-Teach Playbook Memory** (`business_identity`, `pricing_guardrails`, `communication_voice`, `active_priorities`) distilled from inline **Edit & Sign** corrections.
  * Added MCP v2 `resources/list`, `resources/read`, `prompts/list`, and `prompts/get`, `GET /api/agent/events` incremental polling, `luminara:act <action> --json` CLI execution, `/llms.txt` and `/.well-known/agent.json` agent discovery manifests, server-side **Stop** cancellation in the Assistant, `reviewed_at` **Mark reviewed** tracking, and **Owner Simple Mode** (`Briefing` in the sidebar, `/admin/agent/briefing`) across all 10 locales.

* [feature] Added the `Webkul\Tma` Telegram Mini App & Passkey module (`/tma`, `Settings > Other Settings > Telegram Mini App`). Business owners pair their Telegram account via a one-time code (`https://t.me/<Bot>?start=<CODE>`), review and edit waiting proposals, capture voice/text notes via `/api/tma/intake` or the paired Telegram Bot webhook (`/api/tma/bot/webhook`), sign irreversible actions with WebAuthn biometric Passkeys (`ES256` P-256 and `Ed25519`) sealed directly into `DataBlock` receipts, and inspect their Data Vault head and signing streak in a standalone zero-CDN mobile interface with `Telegram.WebApp.initData` HMAC-SHA256 replay protection and `TonAnchorDriver` readiness across all 10 locales.

* [enhancement] Prepared the project for public release under MIT. The README now explains what Luminara adds to Krayin, what is sent to an AI service, how to run it locally and in production, and its known limits. Added a Luminara Digital copyright line beside Webkul's in `LICENSE`, renamed the Composer package to `luminaradigital/luminara-crm`, and pointed the support and security issue templates at this repository. Both Compose files now pass `LUMINARA_ASSISTANT_API_KEY`, `LUMINARA_ASSISTANT_MODEL` and `LUMINARA_ASSISTANT_BASE_URL` to the app, so the Assistant can be turned on from `.env`. The development setup also takes `APP_TIMEZONE` and `APP_CURRENCY` from `.env`. These keys are documented in `.env.example`.

* [fixed] The agent's `leads_create` now needs `person_email` when it creates a new contact, as the CRM's own contact form does. Without one, Krayin gives every email-less contact of a user the same unique key, so the second such contact failed to save and the call reported "action failed".

* [fixed] The development container now runs pending migrations when it starts on an existing database. Before, only a first install migrated, so pulling new code and rebuilding left new tables missing (the Desk answered 500).

* [fixed] The development container now keeps its `APP_KEY` across rebuilds. Its `.env` lives only inside the container, so each rebuild generated a new key, which signed everyone out and left encrypted settings unreadable. The key is now saved to `storage/app/.app_key` on the storage volume and restored when the container is recreated. A container made before this fix keeps the key it already has.

* [fixed] The Assistant no longer passes an empty `LUMINARA_ASSISTANT_BASE_URL` to the API client as its address. An empty value now means the default Anthropic endpoint.

* [feature] Added the Desk (`Desk` in the sidebar, `agent_desk` permission), the first page for supervising the agent. It shows "N things need your OK" over a 3D model of the default pipeline:
  * Each deal is a block whose height shows its value. Waiting agent calls are dashed outlines, with dashed ghosts where moved deals would land.
  * Approving or rejecting a call beside the model sends it through the existing approval checks and then moves the real blocks. A delete takes a full review and a tick to confirm.
  * Dragging a block to another stage moves the deal through the CRM's own stage update, with Undo. Clicking a block opens the deal.
  * Narrow screens and devices without WebGL get bars drawn to scale instead of the model.
  * The page also lists, in plain words, what the agent did for you today, and opens the Assistant in a side sheet.
  * three.js 0.160.0 is loaded from jsDelivr only on this page.
  * Agent Approvals now answers JSON requests with the outcome instead of redirecting.

* [enhancement] Redesigned Agent Approvals and the Assistant around "the agent drafts in pencil, people sign in ink" (`docs/design.md`). Waiting calls are proposal slips with a dashed outline: the records before and after (`Negotiation → Won`, a struck-through lead for a delete), the agent's reason labelled as its own unchecked words, when the call closes unrun, and **Sign and run** / **Reject**. Deleting takes a second press to confirm. Every other call is listed below as a ledger of receipts. The Assistant states up front that it is an AI model and names it, shows each call it made as a numbered step with its state, and labels speakers instead of using chat bubbles. The approvals strings are now translated in all 10 locales.

* [security] Agent Approvals no longer lets Vue compile agent-written text (reasons, lead titles) as template expressions. The page body renders with `v-pre`.

* [feature] Added the `Webkul\Agent` module, which lets AI agents operate the CRM through a token API (`/api/agent`) and a Model Context Protocol endpoint (`/api/agent/mcp`). An agent acts as the CRM user its token was issued for (`php artisan luminara:agent-token <email>`), with that user's role permissions and data scope; deleting a lead or moving several leads at once waits for a person to approve it under `Settings > Other Settings > Agent Approvals`, repeated calls with the same `Idempotency-Key` change nothing twice, and every call is written to the security audit log. The approver sees the records a call will change and the agent's stated reason; an approved call does not run if those records changed after the agent asked, a call nobody decides within 72 hours (`agent.approval_ttl_hours`) expires, a call cut off mid-run is reported as interrupted with nothing changed, and results returned over MCP are marked as data so text in a record cannot pose as instructions.

* [feature] Added the in-app Assistant (`Assistant` in the sidebar, `agent_assistant` permission): a person asks in plain words and a model on the Anthropic Messages API (`claude-opus-5-5` by default) works the CRM through the same agent actions, as that person, with their role's permissions and data scope. It is offered only the actions the person may call, changes that need approval wait under Agent Approvals with a link from the reply, every call is recorded as an agent call with no token, record text it reads is fenced as data, and the conversation stays in the person's own session. Configure it with `LUMINARA_ASSISTANT_API_KEY`, optionally `LUMINARA_ASSISTANT_MODEL` and `LUMINARA_ASSISTANT_BASE_URL` for another Messages-compatible service; CRM records the person can read are sent to that service.

* [feature] Added the `Webkul\Gourwest` global multi-country CRM integration module (`Gourwest Global Hub`) with unified 7-region Golden-Record customer profiles (`AU`, `NZ`, `SG`, `MY`, `TH`, `UK`, `CA`), cross-border email/phone deduplication and lossless loyalty point consolidation, 8-system integration telemetry and dead-letter replay (`ERP`, `POS`, `Mobile Ordering`, `Online Ordering`, `Marketing Automation`, `Loyalty Rewards`, `Customer Support`, `BI & Reporting`), HMAC-SHA256 authenticated webhook ingestion (`/api/gourwest/webhooks/{systemCode}`), Two-Gate scoped regional SLA complaint management, `luminara:seed-gourwest`, and `<3s` / `5M`-record service warranty benchmarking (`luminara:benchmark`).

* [enhancement] Added `luminara:load-dataset` to generate or purge a synthetic customer volume, made `luminara:benchmark` sample random customers across the loaded table (`--samples`) and report `dataset_records` and `capacity_verified` so index readiness is no longer presented as measured 5M-record capacity, and replaced the desktop workstation's projected latency figure with real local measurements, and made the published CRM port configurable end to end (`APP_PORT` drives `APP_URL` in `compose.yaml` and the desktop launcher's CRM origin).

* [fixed] Fixed the test suite exhausting the default 128M PHP memory limit by setting `memory_limit` in `phpunit.xml`.

* [feature] Added the `Webkul\Security` enterprise security module with RFC 6238 TOTP multi-factor authentication, hashed single-use recovery codes, monotonic replay protection, append-only security audit logs (`Settings > Other Settings > Security Audit Logs`), transparent at-rest `core_config` secret encryption, distributed login lockout protection, deep `/up` database and cache readiness probes, `luminara:provision`, and signed `luminara:backup` / `luminara:backup-verify` disaster-recovery commands.

* [feature] Added the `Webkul\Experiment` A/B testing and AI/ML lead propensity module (`Settings > Other Settings > A/B Experiments`) with stateless SHA-256 basis-point bucketing, HMAC-authenticated subject attribution tokens, Holm-Bonferroni multiple-testing correction, Sample Ratio Mismatch ($\chi^2$) diagnostics, conjugate Beta win probability, Newcombe-Wilson 95% confidence intervals, `LeadPropensityScorer` `[0, 100]` explainable lead scoring, `@experiment` Blade directive, and WebForm/Lead funnel conversion attribution.

* [security] Fixed unescaped cookie-to-JavaScript script-context injection in Admin header and sidebar templates, enforced TLS peer verification on outbound SMTP connections, enabled encrypted session payloads, restricted trusted proxy header forwarding (`HEADER_X_FORWARDED_PREFIX` excluded), enforced raw request-line Apache `%{THE_REQUEST}` anti-clickjacking headers (`X-Frame-Options` and `CSP frame-ancestors`), hardened Apache `mod_remoteip` with official Cloudflare IPv4/IPv6 CIDR trust lists, and blocked production container startup when `APP_DEBUG=true` or active accounts retain the default `admin123` password.

* [fixed] Fixed SendGrid inbound email parse payload crashes on large attachments, unguarded background `inbound-emails:process` scheduler errors when IMAP is unconfigured, null-user fatal errors in Automation webhook/email actions and Lead reporting/contact views, mass-lead stage transitions omitting `closed_at` and lifecycle events, MagicAI multi-file upload array crashes and OpenRouter vision payload formatting, cache store environment resolution (`CACHE_STORE` with `CACHE_DRIVER` fallback), and Vietnamese CLI/Web installer localization.

* [enhancement] Hardened Docker (`compose.yaml`, `compose.prod.yaml`, `docker/Dockerfile`, `docker/entrypoint.sh`) with `redis` and `pcntl` PHP extensions, `web`/`worker`/`scheduler` container roles, `cloudflared` Zero-Trust Tunnel topology, GitHub Actions CI/CD with Gitleaks and Trivy security scanning, Windows-compatible agent skill validation (`bin/validate-skills.sh`), and consolidated `<x-admin::logo>` branding across all 10 supported locales (`ar`, `en`, `es`, `fa`, `ja`, `ko`, `pt_BR`, `tr`, `vi`, `zh_CN`).

## **v2.2.8 (5th of Nov 2026)**

* [security] Fixed Sercurity issue.

## **v2.2.7 (16th of Oct 2026)**

* [fixed] Fixed a web form submission creating a duplicate contact when the address differed only in case.

* [fixed] Fixed an empty web form submission answering with a server error.

* [feature] Added quotes to Quick Add.

* [fixed] Fixed creating a quote without line items failing with `Undefined array key "items"`. 

* [fixed] Fixed the leads settings control moving when switching between the kanban and table views.

* [feature] Added notifications.

* [feature] Update data filter at dashboard.

* [feature] Mega Search Will open into modal.

* [feature] Added a per-section record count to the mega search. 

* [fixed] Fixed the lookup field's search field.

* [fixed] Fixed saving a contact with the phone left blank answering with a server error.

* [fixed] Fixed picking a lead on a quote leaving the quote unsaveable and unlinked from that lead.

* [fixed] Fixed picking a lead on a quote replacing the existing items instead of adding to them.

* [fixed] Fixed a lead's page erroring when its contact had no phone number.

* [fixed] Fixed removing a quote from a lead failing with no such lead.

* [fixed] Fixed a lead and its quote not holding the same products.

* [fixed] Fixed opening a mail outside drafts erroring under any locale but English.

* [fixed] Added the missing Spanish and Brazilian Portuguese translations.

* [fixed] Fixed the language file test passing while a translation was missing.

* [security] Improved in Security.

## **v2.2.6 (10th of Sept 2026)**

* [security] Improved in Security.

* [feature] Added MariaDB support.

* [feature] Added customizable lead card information. Datagrid columns and Kanban lead card fields can now be chosen per user through new column and card settings components.

* [feature] Added PDF export for dashboard reports, available from the dashboard alongside the existing views.

* [feature] Added Japanese (`ja`) translation for the Admin, Installer, GoogleContact and WebForm packages.

* [feature] Added an associated group column to the users grid in Settings > Users.

* [fixed] Fixed duplicated activity handling across the lead, person, product and warehouse activity controllers by consolidating the shared logic, and added the missing Japanese activity translations.

* [fixed] Fixed the data transfer import queue processing inconsistently, and corrected the form control group rendering used by the import screen.

* [fixed] Fixed the Google Contact settings screen erroring when no account was connected, and added the corresponding translations.

* [fixed] Fixed the mail ACL mapping so email actions are checked against the correct permission.

* [fixed] Fixed people created from a lead being saved with a null `user_id`, which hid them from the person listing for users restricted to group or individual data scope. They are now assigned to the lead owner, falling back to the acting user.

* [fixed] Fixed the stage API resource omitting `lead_pipeline_id`, so stages could not be matched to their pipeline.

* [fixed] Fixed email attachment downloads being blocked by the URL sanitizer middleware.

* [fixed] Added the missing Chinese translations for the users grid's associated group column.

* [fixed] Fixed flaky admin end-to-end tests around organization owner lookup, lead creation and rich-text comment fields.

* [fixed] Fixed creating or updating a lead failing with a server error (HTTP 500) when a product row was submitted without a `product_id`. The request is now rejected with a validation error.

* [fixed] Fixed the expected close date of a lead being cleared when its stage was changed (Kanban drag and drop, the stage bar, won/lost) or another field was edited inline from the lead view.

* [security] Fixed user and role listings not being scoped by the acting user's data scope, which allowed users to see records outside their own group. Roles now track their creator via a new `created_by` column.

* [security] Hardened the admin ACL middleware to fail closed. An administrative route with no ACL mapping is now denied rather than allowed, inheriting the permission of its nearest mapped ancestor, with a narrow allow-list for authentication, self-service account management and generic UI helpers.

* [security] Removed SVG from the allowed upload types for the admin logo and favicon configuration fields.

* [security] Fixed a security issue configuration file uploads.

* [security] Fixed a security issue activity notes and the TinyMCE editor component.

* [security] Fixed a security issue in the web form embed view.

* [security] Fixed a security issue in attribute downloads.

* [security] Fixed a security issue in mail links.

* [security] Fixed broken tag handling in the tag settings controller.

* [security] Fixed SVG sanitization bypasses in media and configuration file uploads.

* [security] Secured installer APIs.

## **v2.2.5 (4th of Aug 2026)**

* #2631[fixed] Fixed the persons CSV import creating duplicate records and dropping select attribute values on re-import. Existing people are now updated by matched email regardless of a changed phone or organization (so the reported count is accurate), and select/multiselect option labels (or ids) are resolved to their option ids instead of being stored as `0`.

* #2630[fixed] Fixed date attributes in the persons CSV import silently saving as `0000-00-00`. Spreadsheet serial numbers and regional formats such as `DD/MM/YYYY` are now normalised to a valid date, and a value that cannot be parsed is reported as a row error instead of being stored as a zero date.

* [feature] Added Chinese (Simplified) `zh_CN` translation for the Admin, Installer, DataTransfer, WebForm and Core packages.

* [feature] Added a configurable default dashboard date range — 1 month, 3 months, 9 months, 1 year, 2 years or a custom number of days — under Configuration > General > Settings > Dashboard Configurations.

* [fixed] Fixed menu item names set in Configuration not applying to section pages, breadcrumbs and the mobile sidebar. Previously only the desktop sidebar reflected a rename.

* [fixed] Fixed renaming the "Mail" and "Contacts" menu items having no effect anywhere, as their configuration fields did not match the actual menu keys.

* [fixed] Fixed the dashboard date range label omitting the year on ranges spanning more than one calendar year, which rendered as "30 Jul - 30 Jul".

* [fixed] Fixed Arabic DataTransfer translations never loading, as the file was named `ar/ar.php` instead of `ar/app.php`.

* [fixed] Fixed the missing Korean translation for the "None" input validation option on the create and edit attribute forms.

* [enhancement] Moved the Core and DataTransfer package translations into the Admin package. Only packages that ship their own Blade views now carry a `Resources/lang` directory.

* [enhancement] Reduced database queries on every admin page by loading the configured menu names in a single query instead of one per menu item.

* [enhancement] Documented the localization convention in the `crm-package-development` agent skill and in AGENTS.md.
* [feature] Added a collapse/expand toggle to the admin sidebar, matching the Bagisto admin. The choice is remembered across page loads, and page content now reflows to the sidebar width instead of being overlaid by it. The sidebar no longer expands on hover; it is controlled by the toggle only.
* #2614[security] Fixed unauthenticated installer access and executable email attachment upload vulnerabilities.

* #2612[feature] Added import and export support for custom attributes for Leads and Persons.

* #2609[feature] Added Google Contacts export for Persons with Google account connection, duplicate detection, queued export progress, and result summary.

* #2608[fixed] Added missing "none" key to the Korean locale for attribute validation.

* #2606[feature] Added a collapse/expand toggle to the admin sidebar, matching the Bagisto admin. The choice is remembered across page loads, and page content now reflows to the sidebar width instead of being overlaid by it. The sidebar no longer expands on hover; it is controlled by the toggle only.

* #2606[feature] Added an option to show or hide the "Powered by" bar under Configuration > General > Settings > Powered by Section Configurations.

* #2603[feature] Added Korean translations for the Installer, DataTransfer, WebForm, and Core packages.

* #2602[feature] Added Korean translation support for the Admin package.

* #2600[fixed] Fixed invalid activity calendar .ics date-times by emitting UTC RFC 5545 values.

* #2592[security] Fixed webhook validation to reject internal endpoint URLs.

* #2580[enhancement] Added a "None" option to input validation for text attributes.

## **v2.2.4 (20th of July 2026)** *Release*

* #2590[fixed] Fixed page does not refresh after creating a record via Quick Add.

* #2589[fixed] Fixed Quick Add not working for users with group and individual permissions.

* #2582[fixed] Fixed pipeline field visible on public webform.

* #2581[enhancement] Fixed responsive UI issues when page is zoomed.

* #2579[feature] Allow group selection for individual view permission users.

* #2575[enhancement] Added previous month's sales update in Kanban view.

* #2573[enhancement] Added dashboard support for multiple pipelines.

* #2572[enhancement] Added filter by tag option in Contacts > Persons.

* #2571[fixed] Fixed issue with lead creation.

* #2570[fixed] Fixed auto-fill lead email issue.

* #2567[fixed] Fixed IDOR agent record access control vulnerability.

* #2563[fixed] Fixed Kanban infinite scroll duplicates issue.

* #2583[fixed] Fixed SQL injection in rotten lead filter.

* #2585[security] Fixed unrestricted file upload vulnerability (CVE-2026-38526).

* #2559[fixed] Fixed agent record access control issue.

* #2556[fixed] Fixed installation config save issue.

* #2550[fixed] Fixed Kanban infinite scroll duplicates.

* #2549[enhancement] Added support tab feature.

* #2548[enhancement] Allow search by phone and email when creating a lead.

* #2546[feature] Quick Attribute now available at lead form.

* #2545[feature] Added agent skills functionality.

* #2544[enhancement] Added validate skills.

* #2543[enhancement] Added Agents Skills folder.

* #2542[fixed] Fixed stored XSS vulnerability in notes field.

* #2541[fixed] Fixed quote description truncation issue.

* #2539[fixed] Fixed lost revenue arrow UI issue.

* #2538[fixed] Fixed missing translations.

* #2501[fixed] Fixed sales owner not saved in organization.

* #2500[fixed] Fixed activities date filter range issue.

* #2479[fixed] Fixed textarea field not rendered in WebForm.

* #2471[fixed] Fixed missing translations for lead won/lost modal.

* #2420[fixed] Added missing mega search translations for settings and configurations.

* #2533[fixed] Fixed GUI installation issue.

* #2419[security] Fixed stored XSS vulnerability in notes field.

* #2454[fixed] Fixed quote description truncation.

* #2407[fixed] Fixed missing translations.

* #2157[fixed] Fixed auto-fill lead email when creating a lead.

* #2258[fixed] Fixed issue with same-as-billing-address field.

## **v2.2.3 (1st of May 2026)** *Release*

* [fixed] Pipline critical issue resolved.

## **v2.2.2 (1st of May 2026)** *Release*

* [fixed] Update Change Log and version.

## **v2.2.1 (1st of May 2026)** *Release*

* [fixed] Quote fields now auto-fill correctly when a quote is linked to a lead.

* [fixed] Fixed price formatting issue.

* [fixed] Fixed Lead Kanban list ordering.

* [fixed] Fixed header block position at the top.

* [fixed] Updated Activity UI.

* [fixed] Admins can now view and share quote details to a person from the quote list.

* [fixed] Fixed submission issue on the web form.

* [fixed] Fixed activity display issue in the Calendar view.

* [fixed] Logo update issue resolved.

* [enhancement] Drag-and-drop support added to Activity. Admins can now change date and time directly from the Calendar view.

* [feature] Quick App feature added for faster access to key CRM actions.

* [feature] Admins can now add or update a person directly from the lead view page.

* [security] Resolved an authentication bypass vulnerability caused by improper access control in the installer.

## **v2.2.0 (17th of March 2026)** *Release*

* **[Laravel 12 Upgrade]** Upgraded framework to Laravel 12

* #2480[enhancement] Codebase updates and refinements.

* #2478[enhancement] Improved class instantiation handling.

* #2472[enhancement] Upgrade to Laravel 12.

* #2470[enhancement] Updated auto_commits.yml configuration.

* #2469[enhancement] General enhancements and optimizations.

* #2468[enhancement] Documentation updates (MD files).

* #2450[fixed] Added ACL support for warehouses.

* #2444[fixed] Improved global search functionality for organizations.
