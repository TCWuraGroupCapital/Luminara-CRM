# Running Luminara CRM on Oracle Cloud Always Free

This guide moves a running `compose.prod.yaml` stack, for example from a PC, onto a free Oracle Cloud Ampere (ARM) server in Australia. The public address doesn't change, because the Cloudflare tunnel connects outwards from wherever the stack runs. Expect 15–30 minutes of downtime during the switch.

Nothing here costs money while you stay inside Oracle's Always Free limits. Check Oracle's current Always Free terms before you start: limits and the idle-instance policy change from time to time.

## 1. Create the server (you, in the Oracle Cloud console)

1. Sign up at cloud.oracle.com. Choose **Australia East (Sydney)** or **Australia Southeast (Melbourne)** as the *home region*; it can't be changed later, and Always Free servers run only in the home region.
2. Create an SSH key on your own computer if you don't have one: `ssh-keygen -t ed25519 -f ~/.ssh/luminara_oracle`.
3. **Compute > Instances > Create instance:**
   - Image: **Canonical Ubuntu 24.04** (the aarch64 build is picked automatically for Ampere).
   - Shape: **VM.Standard.A1.Flex**, **1 OCPU and 6 GB memory**. The free A1 allowance is 2 OCPU and 12 GB in total (Oracle docs, checked November 2026). 6 GB is plenty for a beta: MySQL, the app, the worker and the scheduler use about 2–2.5 GB.
   - Why not the full 12 GB: Oracle reclaims an Always Free instance when, over 7 days, CPU (95th percentile), network **and** memory all stay under 20%. A quiet CRM can sit under 20% CPU and network, so memory is what keeps it in use. About 2 GB is over 20% of 6 GB, but under 20% of 12 GB. Check it after the move with `free -m`: used memory should stay above 1.2 GB.
   - Boot volume: 100 GB.
   - Networking: the default VCN with a public IP; paste the contents of `~/.ssh/luminara_oracle.pub`.
   - If you see "Out of capacity", try again later or pick another availability domain.
4. **Networking > Virtual cloud networks > your VCN > Security lists:** change the SSH (port 22) rule's source from `0.0.0.0/0` to your own IP address followed by `/32`. Don't open ports 80 or 443: the tunnel doesn't need them.
5. Budgets: create a budget of 1 USD with an email alert, so any accidental paid resource is noticed at once.

## 2. Prepare the server (once)

```bash
ssh -i ~/.ssh/luminara_oracle ubuntu@<server ip>
# copy bin/server-bootstrap.sh across first, or paste it, then:
sudo bash server-bootstrap.sh
exit   # log back in so docker works without sudo
```

## 3. Build the release on the server

From the private repository on your computer:

```bash
git archive --format=tar <release commit> -o luminara.tar
scp -i ~/.ssh/luminara_oracle luminara.tar .env.prod ubuntu@<server ip>:/opt/luminara/
```

On the server:

```bash
cd /opt/luminara && mkdir -p app && tar -xf luminara.tar -C app && mv .env.prod app/ && cd app
chmod 600 .env.prod
docker build --target production -f docker/Dockerfile -t ghcr.io/luminaradigital/luminara-crm:latest .
```

ARM builds natively here, so this takes a few minutes. Nothing else runs yet.

## 4. Cut over

**On the old host.** Stop writes, take a last backup, and copy the data volumes:

```bash
P="docker compose -p luminara-prod -f compose.prod.yaml --env-file .env.prod --profile cloudflare --profile offsite"
$P exec -u www-data app php artisan luminara:backup
$P exec -u www-data app php artisan luminara:workspace:run "luminara:backup"
$P stop app worker scheduler backup-sync
$P stop db redis
mkdir -p cutover
docker run --rm -v luminara-prod_luminara_prod_mysql:/v:ro -v "$PWD/cutover:/out" alpine:3.20 tar -C /v -czf /out/mysql.tgz .
docker run --rm -v luminara-prod_luminara_prod_storage:/v:ro -v "$PWD/cutover:/out" alpine:3.20 tar -C /v -czf /out/storage.tgz .
sha256sum cutover/*.tgz
scp -i ~/.ssh/luminara_oracle cutover/*.tgz ubuntu@<server ip>:/opt/luminara/
```

The tunnel is still up on the old host, so visitors see an error page during the copy.

**On the server.** Load the volumes and start everything except the tunnel:

```bash
cd /opt/luminara/app
sha256sum ../*.tgz   # must match the old host's output
for v in mysql storage; do
  docker volume create "luminara-prod_luminara_prod_${v}"
  docker run --rm -v "luminara-prod_luminara_prod_${v}:/v" -v /opt/luminara:/in:ro alpine:3.20 sh -c "tar -C /v -xzf /in/${v}.tgz"
done
P="docker compose -p luminara-prod -f compose.prod.yaml --env-file .env.prod --profile cloudflare --profile offsite"
$P up -d --no-build db redis app worker scheduler
curl -fsS "http://127.0.0.1:$(grep '^APP_PORT=' .env.prod | cut -d= -f2)/health/ready" && echo OK
```

**Switch the traffic.** Stop the tunnel on the old host first, so two copies of the CRM never take writes at the same time:

```bash
# old host
$P stop cloudflared
# server
$P up -d --no-build cloudflared backup-sync
```

## 5. Check

- [ ] `https://<your domain>/health/ready` answers 200 with `"status":"ok"` (or `"degraded"` when only backup or ledger age is behind). `https://<your domain>/up` still answers 200. `/.well-known/agent.json` lists `https://` addresses.
- [ ] Sign in and open a lead. Two-factor sign-in still works, because `APP_KEY` came across in `.env.prod`.
- [ ] `docker logs luminara-prod-backup-sync-1` shows `offsite sync ok` within a few minutes.
- [ ] `$P exec -u www-data app php artisan luminara:ledger-verify --json` reports the chain intact.
- [ ] Your uptime monitor stays green.
- [ ] `free -m` shows more than 20% of memory used, so Oracle doesn't treat the server as idle.
- [ ] Delete `cutover/*.tgz` on both machines and `/opt/luminara/*.tgz`. They contain customer data.

## Rollback

Within the first hours, before many new changes: stop `cloudflared` on the server, then start `db redis app worker scheduler cloudflared` on the old host. Anything written on the server since the switch is then lost, so keep the old host's stack stopped, not deleted, for two weeks, and roll back only for a serious fault.

## Later releases on the server

Copy the new `git archive`, build it as in step 3, then run `bash bin/release.sh` in `/opt/luminara/app`. It backs up, starts the new version next to the old one, switches without an outage, keeps the previous image as `luminara-prod:rollback-previous` and migrates every workspace. See "Releases without downtime" in [deployment-cloudflare-github.md](./deployment-cloudflare-github.md).

From then on, add `-f compose.prod.rolling.yaml` after `-f compose.prod.yaml` in `$P`, and check the app with `$P exec app curl -fsS http://127.0.0.1/health/ready`, because the host port is no longer published.
