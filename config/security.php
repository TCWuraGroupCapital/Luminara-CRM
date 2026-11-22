<?php

return [
    'require_admin_mfa' => filter_var(
        env('SECURITY_REQUIRE_ADMIN_MFA', env('SECURITY_ENFORCE_ADMIN_MFA', false)),
        FILTER_VALIDATE_BOOL
    ),

    'mfa_issuer' => env('SECURITY_MFA_ISSUER', 'Luminara CRM'),

    'max_failed_logins_per_email' => (int) env('SECURITY_MAX_FAILED_LOGINS_PER_EMAIL', 10),

    'email_lockout_seconds' => (int) env('SECURITY_EMAIL_LOCKOUT_SECONDS', 900),

    'encrypted_core_config_codes' => [
        'general.magic_ai.settings.api_key',
        'email.imap.account.password',
        'general.assistant.settings.api_key',
        'general.telegram.bot.bot_token',
        'general.telegram.bot.webhook_secret',
    ],

    'backup_disk' => env('SECURITY_BACKUP_DISK', 'local'),

    'backup_path' => env('SECURITY_BACKUP_PATH', 'backups'),

    'backup_retention_days' => (int) env('SECURITY_BACKUP_RETENTION_DAYS', 14),

    /**
     * Optional 32-byte libsodium key (standard base64) for backup encryption.
     * Separate from APP_KEY. Empty keeps plain gzip archives.
     */
    'backup_key' => env('LUMINARA_BACKUP_KEY', ''),
];
