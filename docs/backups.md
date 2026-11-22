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

1. Generate a key on a trusted machine: `php artisan luminara:backup-key`.
2. Put `LUMINARA_BACKUP_KEY=...` in the secret store. `.env.example` ships the variable empty. Do not commit the value.
3. Confirm the id with `php artisan luminara:backup-key --show`. The same id is `encryption.key_id` in each signed manifest.
4. Keep `APP_KEY`, `LUMINARA_BACKUP_KEY`, `BACKUP_CRYPT_PASSWORD`, and `BACKUP_CRYPT_SALT` in a password manager the production host cannot read. You need all four to download an off-site copy, decrypt the bucket layer, decrypt the archive, and trust the manifest.
5. Losing `LUMINARA_BACKUP_KEY` makes every archive with that key id unreadable. There is no second copy of the key inside the product.
6. Keep a previous key until every archive that names its id has aged out of host retention and off-site retention. To restore one of those archives, put that key in `LUMINARA_BACKUP_KEY` for the restore command. The command refuses a key whose id does not match the manifest.
7. `LUMINARA_BACKUP_KEY` has to be a different 32-byte key from `APP_KEY`. The backup command rejects a reuse.
8. After setting or rotating the key, recreate the app and the scheduler so the process environment picks it up. New backups then use the new key. Older archives stay tied to the key id in their manifest.
9. `BACKUP_CRYPT_PASSWORD` and `BACKUP_CRYPT_SALT` encrypt names and bytes in the bucket (`rclone crypt`). They are a second layer. A copy taken from the host disk still needs `LUMINARA_BACKUP_KEY`.

Generate the value with `php artisan luminara:backup-key`. It prints standard base64 (a `base64:` prefix is also accepted). `php artisan luminara:backup-key --show` prints only the key id.

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
