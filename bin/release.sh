#!/usr/bin/env bash
#
# Release a new image of the production stack without taking the CRM offline.
#
#   bash bin/release.sh [--skip-backup] [--project NAME] [--env-file FILE] [-f EXTRA.yaml] [--timeout SECONDS]
#
# Build the new image first (see docs/deployment-cloudflare-github.md). This script then:
#
#   1. takes and verifies a database backup with the version now serving,
#   2. starts the new version NEXT TO the old one and waits until it reports healthy,
#   3. tags the image that was serving as <project>:rollback-previous,
#   4. stops the old one gracefully, so requests in flight finish,
#   5. replaces the queue worker and the scheduler,
#   6. runs the migrations of every workspace.
#
# If the new version does not become healthy, it is removed and the old one keeps serving.
#
# It also applies changed settings: run it after editing the env file, with no new image.
#
# The new version migrates the main database while the old one is still serving, so a release
# may only ADD tables and columns. Remove or rename them one release later, when no running
# code reads them.
#
# Needs compose.prod.rolling.yaml (no fixed host port, so two app containers can run at once)
# and Docker Compose 2.24 or newer.

set -euo pipefail

cd "$(dirname "$0")/.."

# Git Bash on Windows rewrites arguments that look like paths; Docker must get them unchanged.
export MSYS_NO_PATHCONV=1

project="luminara-prod"
env_file=".env.prod"
timeout=300
skip_backup=0
extra_files=()

while [[ $# -gt 0 ]]; do
    case "$1" in
        --skip-backup) skip_backup=1; shift ;;
        --project) project="$2"; shift 2 ;;
        --env-file) env_file="$2"; shift 2 ;;
        --timeout) timeout="$2"; shift 2 ;;
        -f|--file) extra_files+=(-f "$2"); shift 2 ;;
        -h|--help) sed -n '3,26p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
        *) echo "Unknown option: $1 (try --help)" >&2; exit 64 ;;
    esac
done

compose() {
    docker compose -p "$project" -f compose.prod.yaml -f compose.prod.rolling.yaml "${extra_files[@]}" --env-file "$env_file" "$@"
}

# Runs a command in an app container as the web user, so files it creates stay writable.
in_app() {
    local container="$1"
    shift
    docker exec -u www-data -e HOME=/tmp -e XDG_CONFIG_HOME=/tmp "$container" "$@"
}

# The list is read in full before it is searched: with `pipefail`, a grep that stops at the
# first match can end the pipe early and make a command that exists look missing.
has_command() {
    local commands
    commands="$(in_app "$1" php artisan list --raw)"
    grep -q "^$2 " <<<"$commands"
}

step() { printf '\n==> %s\n' "$*"; }
die() { printf 'release: %s\n' "$*" >&2; exit 1; }

rollback_tag="${project}:rollback-previous"

mapfile -t serving < <(compose ps -q --status running app)

if [[ ${#serving[@]} -eq 0 ]]; then
    die "no app container is running. Start the stack first: docker compose -p ${project} -f compose.prod.yaml -f compose.prod.rolling.yaml --env-file ${env_file} up -d"
fi

if [[ ${#serving[@]} -gt 1 ]]; then
    docker ps --filter "label=com.docker.compose.project=${project}" --filter "label=com.docker.compose.service=app" \
        --format 'table {{.ID}}\t{{.Image}}\t{{.RunningFor}}\t{{.Status}}' >&2
    die "more than one app container is running, so an earlier release did not finish. Remove the one you do not want (docker rm -f <id>) and run this again."
fi

old="${serving[0]}"
old_image="$(docker inspect -f '{{.Image}}' "$old")"

if [[ $skip_backup -eq 0 ]]; then
    step "Backing up with the version now serving"
    in_app "$old" php artisan luminara:backup
    in_app "$old" php artisan luminara:backup-verify
    if has_command "$old" luminara:workspace:run; then
        in_app "$old" php artisan luminara:workspace:run "luminara:backup"
    fi
fi

step "Starting the new version next to the old one"
compose up -d --no-deps --no-recreate --no-build --scale app=2 app

new=""
while read -r id; do
    [[ -n "$id" && "$id" != "$old" ]] && new="$id"
done < <(compose ps -aq app)
[[ -n "$new" ]] || die "the new app container was not created."

image_ref="$(docker inspect -f '{{.Config.Image}}' "$new")"
new_image="$(docker inspect -f '{{.Image}}' "$new")"

abandon() {
    printf '\nrelease: %s\n\nLast lines from the new container:\n' "$1" >&2
    docker logs --tail 40 "$new" >&2 || true
    docker rm -f "$new" >/dev/null
    cat >&2 <<EOF

The new container was removed. The old version served throughout and is still serving.
If the new version got as far as migrating the main database, those migrations stay applied.
EOF
    exit 1
}

step "Waiting up to ${timeout}s for the new version to report healthy"
deadline=$((SECONDS + timeout))
while :; do
    read -r state health restarts < <(docker inspect -f '{{.State.Status}} {{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}} {{.RestartCount}}' "$new")
    [[ "$state" == "running" && "$health" == "healthy" ]] && break
    # A version that boots cleanly never restarts. Give up on the first restart: while a container
    # is crash-looping, requests routed to it can hang.
    [[ "$state" == "exited" || "$state" == "dead" || "$restarts" -ge 1 ]] && abandon "the new version failed to start."
    [[ $SECONDS -ge $deadline ]] && abandon "the new version was not healthy after ${timeout}s (state: ${state}, health: ${health})."
    sleep 1
done
docker exec "$new" curl -fsS --max-time 4 -o /dev/null http://127.0.0.1/health/ready || abandon "the new version does not answer its readiness check."

if [[ "$old_image" == "$new_image" ]]; then
    echo "Same image as the one that was serving: this release applied settings only."
elif docker image inspect "$old_image" >/dev/null 2>&1; then
    echo "Tagging the image that was serving as ${rollback_tag}"
    docker tag "$old_image" "$rollback_tag"
else
    echo "Previous image (${old_image:0:19}) was already replaced before release; skipping ${rollback_tag}."
fi

step "Stopping the old version (requests in flight get up to 60s to finish)"
docker stop -t 60 "$old" >/dev/null
docker rm "$old" >/dev/null

step "Replacing the queue worker and the scheduler"
compose up -d --no-deps --no-build --wait --wait-timeout 240 worker scheduler

step "Migrating workspaces"
warnings=0
if has_command "$new" luminara:workspace:run; then
    in_app "$new" php artisan luminara:workspace:run "migrate --force" || {
        echo "release: a workspace migration failed. The new version is live; fix the cause, then run: php artisan luminara:workspace:run \"migrate --force\"" >&2
        warnings=1
    }
fi

step "Done"
compose ps app worker scheduler
if [[ "$old_image" != "$new_image" ]]; then
    cat <<EOF

To go back to the previous version (database migrations are not undone):
  docker tag ${rollback_tag} ${image_ref}
  bash bin/release.sh --skip-backup
EOF
fi

exit "$warnings"
