#!/usr/bin/env bash
#
# Seed is the caller's job. This drill captures row counts, writes an encrypted
# logical backup, restores it into a fresh database, and diffs those counts.
#
# The process environment wins over an empty LUMINARA_BACKUP_KEY line in .env.
# RPO 24h and RTO 4h, key custody, and the quarterly checklist are in docs/backups.md.

set -euo pipefail

cd "$(dirname "$0")/.."

cleanup() {
    if [ -n "${RESTORE_DATABASE:-}" ]; then
        php bin/backup-restore-counts.php drop || true
    fi
    if [ -n "${CAPTURE:-}" ]; then
        rm -f "$CAPTURE"
    fi
    rm -rf storage/app/drill-ci
}
trap cleanup EXIT

SOURCE_DB="$(php bin/backup-restore-counts.php database | tail -n 1)"

if [ "$SOURCE_DB" = "luminara_crm" ]; then
    echo "Refusing to drill against the demo database luminara_crm." >&2
    exit 1
fi

RESTORE_DATABASE="${SOURCE_DB}_restore"
export RESTORE_DATABASE

if [ "${#RESTORE_DATABASE}" -gt 64 ]; then
    echo "Restore database name is longer than 64 characters." >&2
    exit 1
fi

KEY_OUTPUT="$(php artisan luminara:backup-key)"
LUMINARA_BACKUP_KEY="$(printf '%s\n' "$KEY_OUTPUT" | sed -n 's/^LUMINARA_BACKUP_KEY=//p' | head -n 1)"
KEY_ID="$(printf '%s\n' "$KEY_OUTPUT" | sed -n 's/^key_id=//p' | head -n 1)"

if [ -z "$LUMINARA_BACKUP_KEY" ] || [ -z "$KEY_ID" ]; then
    echo "luminara:backup-key did not print a key and key id." >&2
    exit 1
fi

export LUMINARA_BACKUP_KEY

CAPTURE="$(mktemp)"
php bin/backup-restore-counts.php capture "$CAPTURE"

rm -rf storage/app/drill-ci
php artisan luminara:backup --path=drill-ci
php artisan luminara:backup-verify --path=drill-ci

shopt -s nullglob
archives=(storage/app/drill-ci/*.ndjson.gz.enc)
manifests=(storage/app/drill-ci/*.manifest.json)
plain=(storage/app/drill-ci/*.ndjson.gz)

if [ "${#archives[@]}" -ne 1 ] || [ "${#manifests[@]}" -ne 1 ] || [ "${#plain[@]}" -ne 0 ]; then
    echo "Expected one encrypted archive and one manifest, and no plain gzip archive." >&2
    exit 1
fi

grep -F "\"key_id\": \"${KEY_ID}\"" "${manifests[0]}" >/dev/null

ARCHIVE="${archives[0]}" php -r '
$bytes = (string) file_get_contents((string) getenv("ARCHIVE"));
if (!str_starts_with($bytes, "LMBK")) {
    fwrite(STDERR, "Encrypted archive is missing the LMBK header.\n");
    exit(1);
}
if (str_starts_with($bytes, "\x1f\x8b")) {
    fwrite(STDERR, "Encrypted archive is plain gzip.\n");
    exit(1);
}
'

php artisan luminara:backup-restore --database="$RESTORE_DATABASE" --migrate --path=drill-ci
php bin/backup-restore-counts.php diff "$CAPTURE"

echo "encrypted backup restore drill passed"
