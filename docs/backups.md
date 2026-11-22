# Backups

Logical backups run daily. Copies leave the host only when the optional off-site profile is on. This page is the source for the recovery targets, the encryption key, and the quarterly restore drill.

## Recovery objectives

| Objective | Target | What it means |
| --- | --- | --- |
| RPO | 24h | `luminara:backup` runs every day at 02:00. A missed or failed run loses at most one day of database changes since the previous good archive. |
| RTO | 4h | From the decision to restore, to a checked scratch database that is ready to become the application database: download the off-site copy, decrypt it, restore it, and diff row counts. |

The off-site copier (`backup-sync` in `compose.prod.yaml`, profile `offsite`, around the `backup-sync` service) runs about once an hour. It only copies archives that already exist. It does not take a new database snapshot, so it does not shorten the 24h RPO. It does mean a finished archive leaves the host within about an hour.

These targets are what a quarterly drill has to meet. A drill that takes longer than 4h, or an archive older than 24h, is a failed drill.

## What is stored

Each run writes two files under `storage/app/backups` (override with `SECURITY_BACKUP_DISK` and `SECURITY_BACKUP_PATH`):

- `luminara_backup_YYYYMMDD_HHMMSS.ndjson.gz` when `LUMINARA_BACKUP_KEY` is unset. Gzip NDJSON, one JSON object per row.
- `luminara_backup_YYYYMMDD_HHMMSS.ndjson.gz.enc` when `LUMINARA_BACKUP_KEY` is set. The same gzip NDJSON, then encrypted with libsodium secretstream (XChaCha20-Poly1305).
- `luminara_backup_YYYYMMDD_HHMMSS.manifest.json`. Archive path, byte size, SHA-256 of the stored file, per-table row counts, and HMAC-SHA256.

The HMAC key is `APP_KEY`. The encryption key is `LUMINARA_BACKUP_KEY`. They are separate secrets. `APP_KEY` proves the manifest was produced by this application. `LUMINARA_BACKUP_KEY` is the secret that can read the rows. The manifest stores the encryption key id (BLAKE2b-256 of the key, hex) inside the signed payload. It never stores the key.

Host archives older than `SECURITY_BACKUP_RETENTION_DAYS` (14 by default) are deleted. Off-site copies are pruned after `BACKUP_OFFSITE_RETENTION_DAYS` (35 by default).

Plain archives written before encryption was turned on still verify and restore. `luminara:backup-verify` and `luminara:backup-restore` follow the manifest: no encryption block means plain gzip, an encryption block means secretstream. The archive name has to match that block (`.ndjson.gz` or `.ndjson.gz.enc`).

## Key custody

`LUMINARA_BACKUP_KEY` is a 32-byte libsodium key, standard base64. A `base64:` prefix is also accepted. It has to be a different key from `APP_KEY`. The backup command rejects a reuse. `.env.example` ships the variable empty, which leaves backups as plain gzip.

### Generation

On a trusted machine:

```bash
php artisan luminara:backup-key
php artisan luminara:backup-key --show
```

The first command prints `LUMINARA_BACKUP_KEY=` and `key_id=`. It does not write the key anywhere. `--show` prints only the id of the key already in the environment. That id is `encryption.key_id` inside each signed manifest.

### Storage

Put the key in a password manager the production host cannot read, labeled with its key id. Set `LUMINARA_BACKUP_KEY` in the secret store for the app and the scheduler (`compose.prod.yaml` passes it through). Recreate those containers after a change so the process environment picks it up. The daily 02:30 `luminara:backup-verify` uses that same variable on the latest archive.

Also keep `APP_KEY`, `BACKUP_CRYPT_PASSWORD`, and `BACKUP_CRYPT_SALT`. The crypt pair only unscrambles the bucket (`rclone crypt`). `APP_KEY` checks the manifest HMAC. `LUMINARA_BACKUP_KEY` is what reads the rows. A copy taken from the host disk still needs the backup key.

### Rotation

1. Generate a new key and store it beside the old one, each labeled with its key id.
2. Set `LUMINARA_BACKUP_KEY` to the new value and recreate the app and the scheduler.
3. The next 02:00 backup uses the new id. The 02:30 verify checks that latest archive with the key now in the environment.
4. Keep the old key until every archive that names its id has aged out of host retention (14 days) and off-site retention (35 days).
5. To read an older archive, put that archive's key in `LUMINARA_BACKUP_KEY` for the one verify or restore. The command refuses a key whose id does not match the manifest.

### If the key is lost

Archives whose manifest key id matches the lost key cannot be decrypted. `APP_KEY` cannot derive the backup key, and the product keeps no second copy. Verify and restore stop with a key error and do not write a scratch database. Treat the days those archives cover as unrecoverable. Generate a new key and take a fresh backup immediately. Plain archives written while the variable was empty do not need this key.

## Quarterly restore drill

Run this every quarter. Use a machine other than the production host when you can. Use the off-site copy, which is the profile `offsite` bucket, when that profile is on. Budget 4h. If the clock passes 4h, stop and record a failure.

Checklist:

- [ ] Name the quarter and the operator in the drill log.
- [ ] Confirm the latest host backup is less than 24h old (`luminara:backup` at 02:00, `luminara:backup-verify` at 02:30).
- [ ] Confirm `backup-sync` is healthy when the off-site profile is on, and that the latest sync is less than 2h old.
- [ ] Read `encryption.key_id` from the latest manifest. Match it to a key in the password manager (`luminara:backup-key --show` on a machine that has that key). A manifest with no encryption block is a legacy plain archive.
- [ ] Download that archive and manifest from the bucket onto the drill machine. Decrypt the rclone layer with `BACKUP_CRYPT_PASSWORD` and `BACKUP_CRYPT_SALT`. The commands are in `docs/deployment-cloudflare-github.md` section 4.2.
- [ ] `php artisan luminara:backup-verify <manifest>` with the matching `LUMINARA_BACKUP_KEY` and the production `APP_KEY`. It must exit 0.
- [ ] `php artisan luminara:backup-restore <manifest> --database=<scratch> --migrate` into an empty database. It must exit 0 and report that every table matches the manifest.
- [ ] Diff row counts between the scratch database and the manifest. The restore command does this. Record the totals.
- [ ] Spot-check one known record (a person, a deal, or a setting) in the scratch database.
- [ ] Record the backup name, key id, source (off-site or host), row totals, duration, and pass or fail.
- [ ] Drop the scratch database. Delete the downloaded files. Both hold customer data.
- [ ] If any step failed, open an incident before the next release. Waiting for the next quarter leaves the gap in place.

A scratch restore never writes the live database. Switching production to the restored data is a separate decision, taken only in a real recovery.

### Drill log

| Date | Backup | Key id | Source | Result |
| --- | --- | --- | --- | --- |
| 2026-11-21 | `luminara_backup_20261120_132804` (main workspace) | plain archive, before encryption | R2 off-site copy | 1,013 rows across 81 tables, all counts match; 34 s |

The 2026-11-21 row is the drill already recorded in `docs/deployment-cloudflare-github.md`. Later drills append a row here.

## Commands

```bash
php artisan luminara:backup-key
php artisan luminara:backup-key --show
php artisan luminara:backup
php artisan luminara:backup-verify
php artisan luminara:backup-restore --database=luminara_crm_restore_drill --migrate
```

## CI

The GitHub Actions job `backup-restore` installs the CRM (that seeds the database), takes an encrypted backup, restores it into a fresh database with `--migrate`, and diffs row counts. The Docker smoke job runs the same drill (`bin/backup-restore-drill.sh`) against the seeded smoke database `luminara_crm_ci`.

Capture the source counts before `luminara:backup`. The command appends a security audit row after it finishes scanning, and restore appends another on the source connection. The scratch database matches the captured counts.
