# Luminara CRM — Production Launch Checklist

Plain-English, step-by-step go-live checklist from verified source code to a live Cloudflare-fronted production deployment. Each step notes who performs it (**You**, **Agent**, or **Both**).

---

## Phase A — Code, Tests & Security Sign-Off

- [x] **1. Pass PSR-12 formatting, Larastan Level 5 static analysis, and skill validation** *(Agent — Completed)*
  `./vendor/bin/pint --test`, `./vendor/bin/phpstan analyse --memory-limit=2G`, and `bash bin/validate-skills.sh` pass with zero errors.
- [x] **2. Pass Pest Unit, Feature, Security, Ledger, Agent v2, TMA, and A/B Experimentation suites + JS property tests** *(Agent — Completed)*
  `php artisan test --compact` and `npm run test:js` verify authentication, TOTP MFA, `core_config` encryption, append-only audit logs, `/up` health checks, `luminara:backup` / `luminara:backup-verify`, `Webkul\Ledger` RFC 8785 + RFC 6962 Merkle proofs, `Webkul\Agent` 26-action Passports / `dry_run` / MCP v2, `Webkul\Tma` `initData` HMAC + WebAuthn Passkeys, and A/B statistics (`Holm-Bonferroni`, `SRM`, `Newcombe-Wilson`, `LeadPropensityScorer`).
- [x] **3. Verify multi-stage production Docker image & startup security gates** *(Agent — Completed)*
  `docker build --target production -f docker/Dockerfile .` compiles fresh Vite assets and refuses startup when `APP_DEBUG=true`, `APP_KEY` is missing, or any active user still has `admin123`.

---

## Phase B — GitHub Repository & Branch Protection

- [ ] **4. Push verified branch to `TCWuraGroupCapital/Luminara-CRM`** *(You / Both)*
  Review `git status` and `git diff`, commit, and push to `origin` (`2.2` / `main`).
- [ ] **5. Enable GitHub Branch Protection Rules** *(You — GitHub Settings > Branches)*
  Require pull requests and passing status checks (`CI`, `Linting Tests`, `Static Analysis`, `JavaScript Tests`, `Docker Smoke & Security Tests`) before merging into `2.2` and `main`.

---

## Phase C — Secrets, Database & Origin Deployment

- [ ] **6. Create `.env.prod` on your production host** *(You)*
  Copy `.env.example` to `.env.prod`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://crm.yourdomain.com`, generate a persistent `APP_KEY` (`php artisan key:generate --show`), set random 32-character `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, and `REDIS_PASSWORD` values, and set `SECURITY_REQUIRE_ADMIN_MFA=true`. Optionally set `LUMINARA_LEDGER_SEED` (64-hex Ed25519 seed; defaults to HKDF-SHA256 from `APP_KEY`), `LEDGER_ANCHOR_DRIVER=local` (or `ton` with `TON_ANCHOR_RELAYER_URL` and `TON_ANCHOR_CONTRACT`), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, `TELEGRAM_WEBHOOK_SECRET`, and `TRUSTED_PROXIES` (pre-populated in `compose.prod.yaml` for Cloudflare + `172.31.250.0/28`).
- [ ] **7. Provision database & rotate administrator credentials** *(Both)*
  Start `db` and `redis`, then run `luminara:provision` with `LUMINARA_ADMIN_PASSWORD` (`>= 12` characters) as shown in [docs/deployment-cloudflare-github.md](deployment-cloudflare-github.md) Section 2.2.
- [ ] **8. Configure outbound SMTP & inbound email parse webhook (if using CRM Mail)** *(You)*
  Set `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_VERIFY_PEER=true`, and `MAIL_RECEIVER_INBOUND_TOKEN` (if receiving inbound emails via SendGrid Inbound Parse).

---

## Phase D — Cloudflare Edge & Zero-Trust Cutover

- [ ] **9. Create Cloudflare Tunnel & attach `CLOUDFLARE_TUNNEL_TOKEN`** *(You — Cloudflare Zero Trust)*
  Create a tunnel in `Networks > Tunnels`, route `https://crm.yourdomain.com` to `http://app:80`, paste the token into `.env.prod` (`COMPOSE_PROFILES=cloudflare`), and start the full stack (`docker compose -f compose.prod.yaml --env-file .env.prod --profile cloudflare up -d`).
- [ ] **10. Apply Cloudflare Cache & WAF rules** *(You — Cloudflare Dashboard)*
  Set SSL/TLS to **Full (Strict)**, cache `/(admin|installer|webform)/build/assets/*` (`1 year` immutable), bypass cache on `/admin*`, `/api*`, `/tma*`, `/receipts*`, `/llms.txt`, `/.well-known/agent.json`, `/experiments*`, `/web-forms*`, and `/up`, and exempt `/api/agent*`, `/api/tma/*`, `/api/ledger/*`, `/llms.txt`, `/.well-known/agent.json`, `/mail/inbound-parse`, `/web-forms/forms/*`, `/experiments/(track|assign/*)`, and `/up` from interactive browser JS challenges.

---

## Phase E — Post-Launch Verification

- [ ] **11. Verify `/up` readiness, Ledger integrity, Agent manifests, and first-login TOTP MFA** *(You)*
  Confirm `curl -fsSI https://crm.yourdomain.com/up`, `/llms.txt`, and `/.well-known/agent.json` return `200 OK` with `Strict-Transport-Security`, sign in with your provisioned administrator account, complete TOTP MFA setup, store the 8 recovery codes offline in your password vault, run `luminara:ledger-verify` to confirm chain integrity, and run `luminara:backup` + `luminara:backup-verify` once manually to confirm disaster-recovery storage.
