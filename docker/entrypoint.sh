#!/bin/sh
set -eu

cd /var/www/html

mkdir -p \
    storage/app/public \
    storage/app/backups \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

ROLE="${CONTAINER_ROLE:-web}"

if [ "${APP_ENV:-local}" = "production" ]; then
    DEBUG_NORM="$(printf '%s' "${APP_DEBUG:-false}" | tr '[:upper:]' '[:lower:]')"
    case "$DEBUG_NORM" in
        1|true|yes|on)
            echo 'SECURITY ERROR: APP_DEBUG must be false in production.' >&2
            exit 1
            ;;
    esac

    EFFECTIVE_KEY="${APP_KEY:-}"
    if [ -z "$EFFECTIVE_KEY" ] && [ -f .env ]; then
        EFFECTIVE_KEY="$(grep -E '^APP_KEY=' .env | tail -n 1 | cut -d= -f2- | sed -e 's/^["'\'']//;s/["'\'']$//' || true)"
    fi

    case "$EFFECTIVE_KEY" in
        base64:?*) ;;
        *)
            echo 'Production requires a persistent APP_KEY=base64:... environment variable.' >&2
            exit 1
            ;;
    esac

    if [ ! -f .env ]; then
        printf 'APP_ENV=production\nAPP_DEBUG=false\n' > .env
    fi

    php artisan package:discover --ansi

    if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
        php artisan migrate --force --no-interaction
    fi

    # Stateless container install reconciliation + default admin123 credential block
    php -r '
        require "vendor/autoload.php";
        $app = require "bootstrap/app.php";
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        $dbManager = $app->make(Webkul\Installer\Helpers\DatabaseManager::class);
        if (! file_exists(storage_path("installed"))) {
            if (! $dbManager->isInstallationCompleted()) {
                fwrite(STDERR, "Production database is not provisioned. Run: php artisan luminara:provision\n");
                exit(1);
            }
            @file_put_contents(storage_path("installed"), "Reconciled from core_config at " . date("c"));
        }

        if (Illuminate\Support\Facades\Schema::hasTable("users")) {
            $hashes = Illuminate\Support\Facades\DB::table("users")
                ->where("status", 1)
                ->whereNotNull("password")
                ->pluck("password");

            foreach ($hashes as $hash) {
                if (is_string($hash) && password_verify("admin123", $hash)) {
                    fwrite(STDERR, "SECURITY ERROR: Active user with default admin123 password detected in production. Rotate via php artisan luminara:provision.\n");
                    exit(1);
                }
            }
        }
    '

    php artisan config:cache
    if [ "$ROLE" = "web" ]; then
        php artisan route:cache
        php artisan view:cache
    fi
else
    if [ ! -f .env ]; then
        cp .env.example .env
    fi

    # Safely sync environment overrides into .env for Krayin's runtime .env reader without sed delimiter injection.
    php -r '
        $envFile = ".env";
        $lines = file_exists($envFile) ? file($envFile, FILE_IGNORE_NEW_LINES) : [];
        $keys = [
            "APP_NAME" => "Luminara CRM",
            "APP_ENV" => "local",
            "APP_KEY" => null,
            "APP_DEBUG" => "true",
            "APP_URL" => "http://localhost:8080",
            "APP_TIMEZONE" => "Australia/Melbourne",
            "APP_LOCALE" => "en",
            "APP_CURRENCY" => "AUD",
            "TRUSTED_PROXIES" => null,
            "DB_CONNECTION" => "mysql",
            "DB_HOST" => "db",
            "DB_PORT" => "3306",
            "DB_DATABASE" => "luminara_crm",
            "DB_USERNAME" => "luminara",
            "DB_PASSWORD" => "luminara_local_only",
            "MAIL_MAILER" => "log",
        ];

        $format = static function (string $val): string {
            if ($val === "") {
                return "";
            }
            if (preg_match("/[\s#\"\\\\\\$]/", $val)) {
                return "\"" . addcslashes($val, "\"\\\\\\$") . "\"";
            }
            return $val;
        };

        foreach ($keys as $key => $default) {
            $raw = getenv($key);
            if ($raw === false) {
                if ($default === null) {
                    continue;
                }
                $raw = $default;
            }

            $formatted = $key . "=" . $format((string) $raw);
            $updated = false;
            foreach ($lines as $i => $line) {
                if (preg_match("/^" . preg_quote($key, "/") . "=/", $line)) {
                    $lines[$i] = $formatted;
                    $updated = true;
                    break;
                }
            }
            if (! $updated) {
                $lines[] = $formatted;
            }
        }

        file_put_contents($envFile, implode("\n", $lines) . "\n");
    '

    php artisan package:discover --ansi
    php artisan config:clear

    # .env lives only in the container, so the key is kept on the storage volume. Without this, every
    # rebuild made a new key, which signed everyone out and left encrypted settings unreadable.
    KEY_FILE=storage/app/.app_key
    if grep -Eq '^APP_KEY=base64:.+' .env; then
        if [ ! -s "$KEY_FILE" ]; then
            grep -E '^APP_KEY=' .env | tail -n 1 | cut -d= -f2- > "$KEY_FILE"
            chmod 600 "$KEY_FILE"
        fi
    elif [ -s "$KEY_FILE" ]; then
        SAVED_KEY="$(cat "$KEY_FILE")"
        export SAVED_KEY
        php -r '
            $f = ".env";
            $line = "APP_KEY=" . getenv("SAVED_KEY");
            $c = (string) file_get_contents($f);
            $c = preg_match("/^APP_KEY=/m", $c)
                ? (string) preg_replace("/^APP_KEY=.*$/m", $line, $c)
                : rtrim($c, "\n") . "\n" . $line . "\n";
            file_put_contents($f, $c);
        '
        unset SAVED_KEY
    else
        php artisan key:generate --force
        grep -E '^APP_KEY=' .env | tail -n 1 | cut -d= -f2- > "$KEY_FILE"
        chmod 600 "$KEY_FILE"
    fi

    if [ ! -f storage/installed ]; then
        php artisan krayin-crm:install --skip-env-check --skip-admin-creation --no-interaction
        php artisan tinker --execute="app(Webkul\\Installer\\Helpers\\DatabaseManager::class)->markInstallationCompleted(); app(Webkul\\Installer\\Helpers\\DatabaseManager::class)->clearInstallationInProgress();"
        touch storage/installed
    else
        php artisan migrate --force --no-interaction
    fi
fi

if [ "$(id -u)" = "0" ]; then
    find storage bootstrap/cache ! -user www-data -exec chown -h www-data:www-data {} +
fi

case "$ROLE" in
    web)
        exec "$@"
        ;;
    worker)
        if [ "$(id -u)" = "0" ]; then
            exec setpriv --reuid=www-data --regid=www-data --init-groups php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=90
        fi
        exec php /var/www/html/artisan queue:work --sleep=3 --tries=3 --max-time=3600 --timeout=90
        ;;
    scheduler)
        if [ "$(id -u)" = "0" ]; then
            exec setpriv --reuid=www-data --regid=www-data --init-groups php /var/www/html/artisan schedule:work
        fi
        exec php /var/www/html/artisan schedule:work
        ;;
    *)
        echo "Unsupported CONTAINER_ROLE: $ROLE (expected web, worker, or scheduler)." >&2
        exit 1
        ;;
esac
