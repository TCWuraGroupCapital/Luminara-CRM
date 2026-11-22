<?php

namespace Webkul\Security\Console\Commands;

use Illuminate\Console\Command;
use RuntimeException;
use Webkul\Security\Support\BackupCipher;

class BackupKeyCommand extends Command
{
    protected $signature = 'luminara:backup-key {--show : Print the key id of the configured LUMINARA_BACKUP_KEY}';

    protected $description = 'Generate a libsodium secretstream key for backup encryption. The key is separate from APP_KEY.';

    public function handle(): int
    {
        if ($this->option('show')) {
            return $this->show();
        }

        $raw = random_bytes(SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES);
        $encoded = sodium_bin2base64($raw, SODIUM_BASE64_VARIANT_ORIGINAL);
        $keyId = BackupCipher::keyId($raw);
        BackupCipher::wipe($raw);

        $this->line('LUMINARA_BACKUP_KEY='.$encoded);
        $this->line('key_id='.$keyId);
        fwrite(STDERR, "Store this key apart from APP_KEY and apart from the backups. The manifest records only the key id.\n");

        return self::SUCCESS;
    }

    private function show(): int
    {
        try {
            $raw = BackupCipher::configuredKey();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($raw === null) {
            $this->error('LUMINARA_BACKUP_KEY is not set.');

            return self::FAILURE;
        }

        $keyId = BackupCipher::keyId($raw);
        BackupCipher::wipe($raw);

        $this->line('key_id='.$keyId);

        return self::SUCCESS;
    }
}
