# Luminara CRM — GitHub & Cloudflare Enterprise Deployment Runbook

This runbook documents the verified production deployment architecture for **Luminara CRM** across **GitHub Actions CI/CD** (`TCWuraGroupCapital/Luminara-CRM`) and **Cloudflare** (Zero Trust Tunnel `cloudflared`, WAF, Cache Rules, and Edge TLS).

---

## 1. Pre-Push Verification Gates

Run all automated quality and security gates locally before tagging or cutting over a release:

```bash
# 1. Code style (PSR-12 / Laravel Pint)
docker compose exec app ./vendor/bin/pint --test

# 2. Static analysis (Larastan Level 5 across app/ and packages/)
docker compose exec app ./vendor/bin/phpstan analyse --memory-limit=2G

# 3. Rector dry-run check
docker compose exec app ./vendor/bin/rector process --dry-run --no-progress-bar

# 4. Full Pest Unit + Feature + Security + A/B Experimentation suite
docker compose exec app php artisan test --compact

# 5. JavaScript property tests & agent skill source-of-truth check
npm run test:js
bash bin/validate-skills.sh
```

On push/PR to `main` or `2.2`, GitHub Actions automatically runs:
- [ci.yml](../.github/workflows/ci.yml): Gitleaks secret scan + MySQL 8.0 Pest test suite.
- [linting_tests.yml](../.github/workflows/linting_tests.yml): Laravel Pint + `bin/validate-skills.sh`.
- [static_analysis.yml](../.github/workflows/static_analysis.yml): PHPStan Level 5 + Rector dry-run.
- [js_tests.yml](../.github/workflows/js_tests.yml): Node 22 property tests.
- [docker.yml](../.github/workflows/docker.yml): Multi-stage `production` image build, Trivy `CRITICAL,HIGH` vulnerability scan, negative security gate probes (`APP_DEBUG=true`, missing `APP_KEY`, `admin123` rejection), `luminara:provision`, and live HTTP/Cloudflare header smoke verification.

---

## 2. Production Stack Provisioning (`compose.prod.yaml`)

### 2.1 Configure Production Environment Secrets

Create a root `.env.prod` (never committed to git — `.env.*` is ignored in `.gitignore`) with strong, unique secrets:

```dotenv
APP_NAME="Luminara CRM"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERATE_ONCE_WITH_PHP_ARTISAN_KEY_GENERATE
APP_URL=https://crm.yourdomain.com
APP_TIMEZONE=Australia/Melbourne
APP_LOCALE=en
APP_CURRENCY=AUD

DB_DATABASE=luminara_crm
DB_USERNAME=luminara
DB_PASSWORD=REPLACE_WITH_32_CHAR_RANDOM_DB_PASSWORD
MYSQL_ROOT_PASSWORD=REPLACE_WITH_32_CHAR_RANDOM_ROOT_PASSWORD

REDIS_PASSWORD=REPLACE_WITH_32_CHAR_RANDOM_REDIS_PASSWORD
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
QUEUE_CONNECTION=redis

TRUSTED_PROXIES=127.0.0.1,172.31.250.0/28
SECURITY_REQUIRE_ADMIN_MFA=true
SECURITY_BACKUP_RETENTION_DAYS=14

# Verifiable Ledger, Agent Assistant & Telegram Mini App (optional overrides)
LUMINARA_LEDGER_SEED=REPLACE_OPTIONAL_64_HEX_ED25519_SEED
LEDGER_ANCHOR_DRIVER=local
LUMINARA_ASSISTANT_API_KEY=
LUMINARA_ASSISTANT_MODEL=claude-opus-5-5
TELEGRAM_BOT_TOKEN=
TELEGRAM_BOT_USERNAME=
TELEGRAM_WEBHOOK_SECRET=
TON_ANCHOR_RELAYER_URL=
TON_ANCHOR_CONTRACT=

COMPOSE_PROFILES=cloudflare
CLOUDFLARE_TUNNEL_TOKEN=REPLACE_WITH_CLOUDFLARE_ZERO_TRUST_TUNNEL_TOKEN
```

> **Important:** Generate `APP_KEY` once (`php artisan key:generate --show`) and store it in your secret vault. `APP_KEY` encrypts sessions, encrypts `core_config` secrets at rest (`enc:v1:...`), signs backup manifests, and authenticates A/B experiment subject tokens.

### 2.2 Build & Provision Database (`luminara:provision`)

`docker/entrypoint.sh` blocks `production` startup if:
1. `APP_DEBUG` is truthy (`1`, `true`, `yes`, `on`),
2. `APP_KEY` is missing,
3. the database has not completed installation, **or**
4. any active `users` row still uses the upstream default `admin123` password.

Run the idempotent `luminara:provision` command once against your fresh database to run migrations, seed baseline reference tables, mark installation complete in `core_config` + `storage/installed`, and set a strong initial administrator password (`>= 12` chars, mixed case + digit/symbol):

```bash
# 1. Start MySQL 8.0 and Redis 7.4 first
docker compose -f compose.prod.yaml --env-file .env.prod up -d db redis

# 2. Build the production image and run idempotent zero-touch provisioning
docker compose -f compose.prod.yaml --env-file .env.prod build app
docker compose -f compose.prod.yaml --env-file .env.prod run --rm \
  -e LUMINARA_ADMIN_PASSWORD="REPLACE_WITH_STRONG_INITIAL_ADMIN_PASSWORD" \
  --entrypoint php app \
  artisan luminara:provision \
  --admin-email="admin@yourdomain.com" \
  --admin-name="Luminara Administrator" \
  --force

# 3. Launch web, queue worker, scheduler, and Cloudflare Tunnel services
docker compose -f compose.prod.yaml --env-file .env.prod --profile cloudflare up -d
```

For subsequent releases with schema changes, run migrations cleanly before rolling `app` / `worker`:

```bash
docker compose -f compose.prod.yaml --env-file .env.prod run --rm --entrypoint php app artisan migrate --force --no-interaction
docker compose -f compose.prod.yaml --env-file .env.prod --profile cloudflare up -d --build
```

Never run `migrate:fresh` against a production database.

---

## 3. Cloudflare Zero Trust, WAF & Caching Configuration

### 3.1 Cloudflare Tunnel (`cloudflared`)
In Cloudflare Zero Trust (`Networks > Tunnels`):
1. Create a `cloudflared` tunnel, copy its token into `CLOUDFLARE_TUNNEL_TOKEN` in `.env.prod`, and add a Public Hostname:
   - **Subdomain / Domain:** `crm.yourdomain.com`
   - **Service:** `http://app:80`
2. No inbound ports (`80` or `443`) need to be opened on the host firewall (`compose.prod.yaml` binds only to loopback `127.0.0.1:8080` and exposes zero public host interfaces; `cloudflared` dials outbound over QUIC/HTTP2 on the internal `172.31.250.0/28` edge bridge).
3. Apache `mod_remoteip` trusts only `127.0.0.1`, `::1`, `172.31.250.0/28`, and official Cloudflare IPv4/IPv6 CIDR blocks when reading `CF-Connecting-IP` — spoofed `CF-Connecting-IP` headers from untrusted peers are ignored.

### 3.2 SSL/TLS & Edge Security
1. **SSL/TLS Encryption Mode:** **Full (Strict)** (or Tunnel HTTP origin over encrypted `cloudflared` QUIC channel). Enable **Always Use HTTPS** and **Minimum TLS Version: TLS 1.2**.
2. **Cache Rules (`Caching > Cache Rules`):**
   - **Rule 1 — Immutable Vite Assets (`Cache Everything`, Edge TTL `1 year`):**
     `http.request.uri.path matches "^/(admin|installer|webform)/build/assets/"`
   - **Rule 2 — Bypass Dynamic CRM, API, TMA, Ledger, A/B & Auth Endpoints (`Bypass Cache`):**
     `http.request.uri.path matches "^/(admin|api|tma|receipts|experiments|web-forms|up|llms\.txt|\.well-known/agent\.json)"`
3. **WAF / Bot Fight Mode Exceptions (`Security > WAF > Custom Rules`):**
   - Skip *Managed Challenge / JS Challenge / Super Bot Fight Mode* (while keeping OWASP SQLi/XSS Managed Rules active) on:
     - `http.request.method eq "POST" and http.request.uri.path eq "/api/tma/bot/webhook"` (Telegram Bot intake/pairing webhook authenticated via `X-Telegram-Bot-Api-Secret-Token`)
     - `http.request.uri.path matches "^/api/(agent|tma|ledger)/"` (Bearer-authenticated Agent/MCP/TMA APIs & Merkle proof endpoint)
     - `http.request.method eq "GET" and http.request.uri.path in {"/llms.txt" "/.well-known/agent.json" "/tma"}` (Agent discovery manifests & Telegram WebApp entrypoint)
     - `http.request.method eq "POST" and http.request.uri.path contains "/mail/inbound-parse"` (SendGrid / Mailgun inbound webhook)
     - `http.request.method eq "POST" and http.request.uri.path matches "^/web-forms/forms/"` (embedded lead capture forms)
     - `http.request.uri.path matches "^/experiments/(track|assign/)"` (public A/B assignment & conversion beacons)
     - `http.request.method eq "GET" and http.request.uri.path eq "/up"` (synthetic uptime probes)
   - Optional Edge Rate Limit: block IPs exceeding `30` `POST` requests/minute to `/admin/login` or `/admin/security/mfa/verify`.

---

## 4. Backups, Disaster Recovery & Operations

- **Automated Daily & Hourly Schedule (`scheduler` container):**
  - Hourly: `php artisan luminara:ledger-seal` seals pending `DataBlock` records into a signed RFC 6962 binary Merkle Epoch (`ledger_epochs`) through the configured `LEDGER_ANCHOR_DRIVER` (`local` default; `ton` when ready for on-chain anchoring).
  - `02:00` daily: `php artisan luminara:backup` writes a gzip-compressed NDJSON logical snapshot (`storage/app/backups/luminara_backup_YYYYMMDD_HHMMSS.ndjson.gz`) plus a `.manifest.json` signed with `SHA-256` and `HMAC-SHA256`, and prunes archives older than `SECURITY_BACKUP_RETENTION_DAYS` (`14` days default).
  - `02:30` daily: `php artisan luminara:backup-verify` streams the latest archive to verify its SHA-256 digest, HMAC signature, gzip integrity, and per-table row counts.
- **On-Demand Backup, Ledger Verification & Agent Passport Issuance:**
  ```bash
  docker compose -f compose.prod.yaml --env-file .env.prod exec app php artisan luminara:backup
  docker compose -f compose.prod.yaml --env-file .env.prod exec app php artisan luminara:backup-verify
  docker compose -f compose.prod.yaml --env-file .env.prod exec app php artisan luminara:ledger-verify --json
  docker compose -f compose.prod.yaml --env-file .env.prod exec app php artisan luminara:passport admin@yourdomain.com claude-code --json
  ```
- **First-Login Administrator MFA Enrollment:**
  When `SECURITY_REQUIRE_ADMIN_MFA=true`, signing in at `https://crm.yourdomain.com/admin/login` automatically redirects un-enrolled administrators to `/admin/security/mfa/setup` to pair an RFC 6238 authenticator app and download 8 single-use recovery codes before accessing CRM data.
