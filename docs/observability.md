# Observability

`/up` is the liveness probe. It proves the PHP process can answer, and after installation it also runs the existing database, cache, and storage probe. It does not report the queue, backups, or the ledger, and its success body is not JSON.

`GET /health/ready` is the readiness probe. It is public, rate limited, and safe to poll from Docker, a load balancer, or an uptime monitor. Cache it nowhere: the response is `Cache-Control: no-store`.

## Readiness

```bash
curl -fsS http://127.0.0.1/health/ready
```

A healthy body looks like this:

```json
{
  "status": "ok",
  "checks": {
    "database": {"status": "ok", "critical": true, "latency_ms": 2},
    "redis": {"status": "ok", "critical": true, "latency_ms": 1},
    "queue": {"status": "ok", "critical": true, "age_seconds": 12, "heartbeat_at": "2026-11-22T01:47:48Z"},
    "backup": {"status": "ok", "critical": false, "age_seconds": 7200, "completed_at": "2026-11-21T23:48:00Z"},
    "ledger_seal": {"status": "ok", "critical": false, "age_seconds": 1800, "sealed_at": "2026-11-22T01:18:00Z"}
  }
}
```

`ledger_seal` is omitted when the `ledger_epochs` table does not exist.

| `status` | HTTP | Meaning |
|---|---|---|
| `ok` | 200 | Every check passed. |
| `degraded` | 200 | A non-critical check failed. The process can still serve. |
| `fail` | 503 | A critical check failed. |

Critical by default:

- **database**: `SELECT 1` on the default connection.
- **redis**: `PING` on the default Redis connection.
- **queue**: age of the scheduler heartbeat. `luminara:queue-heartbeat` runs every minute and stores a UTC timestamp in the cache. Older than `HEALTH_QUEUE_MAX_AGE_SECONDS` (default 180) is a failure.

The queue check reports `starting` instead of failing during `HEALTH_QUEUE_START_GRACE_SECONDS` (default 180) after the container starts. The grace file is `/tmp/luminara-health-boot` inside that container, not on the shared storage volume. The app can become ready, and only then does Compose start the scheduler, which is what writes the heartbeat. After the grace, a missing or stale heartbeat fails readiness.

Not critical unless you opt in (a failure is `degraded` and HTTP 200):

- **backup**: age of the newest `luminara_backup_YYYYMMDD_HHMMSS.manifest.json` under `SECURITY_BACKUP_PATH`. The manifest body is not opened. Default max age is 93600 seconds (26 hours), one daily backup plus grace. Set `HEALTH_BACKUP_CRITICAL=true` to make a stale or missing backup return 503.
- **ledger_seal**: present only when the ledger is installed. It fails when an unsealed data block has been waiting longer than `HEALTH_LEDGER_SEAL_MAX_AGE_SECONDS` (default 7200, two hours). A quiet ledger with nothing new to seal stays ok. Set `HEALTH_LEDGER_SEAL_CRITICAL=true` to make that failure return 503.

Failed checks use a short `error` code (`unavailable`, `ping_failed`, `missing`, `stale`). Exception messages, passwords, DSNs, webhook URLs, and backup HMACs are not included. Known secret config values are stripped again before the JSON is encoded.

The route allows `HEALTH_READY_RATE_PER_MINUTE` requests per address per minute (default 120). The limiter uses the file cache, so a Redis outage still produces a readiness report. Over the limit the answer is 429 with `Retry-After: 60`.

`/health/ready` is exempt from the installer redirect, same as `/up`, so a probe during first boot gets JSON instead of an HTML redirect.

## Logs

`config/logging.php` defines a `json` channel: stderr, one JSON object per line, via `Webkul\Security\Logging\RedactingJsonFormatter`. Context keys that look like passwords, tokens, DSNs, or webhooks are replaced with `[redacted]`.

```dotenv
LOG_CHANNEL=json
LOG_LEVEL=info
```

`compose.prod.yaml` sets `LOG_CHANNEL` to `json` unless you override it. `stack` remains the default outside that file, so local `laravel.log` stays line-oriented.

## Sentry

`sentry/sentry-laravel` is a production dependency and stays inactive until `SENTRY_LARAVEL_DSN` is a non-empty string. The SDK does not read `SENTRY_DSN`. Spotlight is off. Default personal data is off. Metrics stay off unless `SENTRY_ENABLE_METRICS=true`. Tracing stays off unless `SENTRY_TRACES_SAMPLE_RATE` is set.

With the DSN empty, the SDK does not register its event listeners or HTTP middleware, and the exception handler does not forward errors. Set the DSN in `.env.prod` (compose passes `SENTRY_LARAVEL_DSN`) and restart so `config:cache` picks it up.

`/up` and `/health/ready` are ignored as Sentry transactions.

## Alerts

`luminara:ops-alert` runs every five minutes.

- **Failed jobs.** The first run stores the current `failed_jobs` count and does not alert. Later runs alert only when the count grows. Job payloads and exception text are not included.
- **Backups.** Alerts when the newest successful manifest is older than `HEALTH_BACKUP_MAX_AGE_SECONDS`, or when the install marker is older than that and no manifest exists. The same condition is not emailed again until `OBSERVE_ALERT_BACKUP_COOLDOWN_SECONDS` (default 21600, six hours). A fresh backup clears the cooldown.

Every alert is logged at `critical` as `luminara.ops.alert`. In addition:

```dotenv
OBSERVE_ALERT_EMAIL=ops@example.com
OBSERVE_ALERT_SLACK_WEBHOOK=https://hooks.slack.com/services/...
```

The Slack URL must be `https`. The message does not include the webhook URL or job payloads. Leave either value empty to skip that channel.

## Containers

`compose.prod.yaml`:

- **app** health check: `GET /health/ready` (readiness). The image `HEALTHCHECK` stays `GET /up` (liveness) for a container started without this compose file.
- **scheduler** health check: the process is `schedule:work`, and `luminara:queue-heartbeat --check` says the heartbeat is fresh or still inside the start grace.
- **worker** health check: the process is `queue:work`. It does not call the app, so a database blip does not get reported as a dead worker.
- **db**, **redis**, and **backup-sync** keep their own checks.

`bin/release.sh` waits until the new app container is healthy, then confirms `GET /health/ready` from inside that container.

Do not cache `/health/ready` at Cloudflare. The bypass rule in [deployment-cloudflare-github.md](./deployment-cloudflare-github.md) includes `/health`.
