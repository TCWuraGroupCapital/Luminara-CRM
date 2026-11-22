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

    /*
     | Readiness (/health/ready) and the operational alert command.
     | Backup and ledger checks are reported, and they only fail the HTTP
     | status when the matching critical flag is true.
     */
    'health_ready_rate_per_minute' => (int) env('HEALTH_READY_RATE_PER_MINUTE', 120),

    'health_queue_max_age_seconds' => (int) env('HEALTH_QUEUE_MAX_AGE_SECONDS', 180),

    'health_queue_start_grace_seconds' => (int) env('HEALTH_QUEUE_START_GRACE_SECONDS', 180),

    'health_boot_path' => env('HEALTH_BOOT_PATH', '/tmp/luminara-health-boot'),

    'health_backup_max_age_seconds' => (int) env('HEALTH_BACKUP_MAX_AGE_SECONDS', 93600),

    'health_backup_critical' => filter_var(env('HEALTH_BACKUP_CRITICAL', false), FILTER_VALIDATE_BOOL),

    'health_ledger_max_age_seconds' => (int) env('HEALTH_LEDGER_SEAL_MAX_AGE_SECONDS', 7200),

    'health_ledger_critical' => filter_var(env('HEALTH_LEDGER_SEAL_CRITICAL', false), FILTER_VALIDATE_BOOL),

    'alert_email' => env('OBSERVE_ALERT_EMAIL', ''),

    'alert_slack_webhook' => env('OBSERVE_ALERT_SLACK_WEBHOOK', ''),

    'alert_backup_cooldown_seconds' => (int) env('OBSERVE_ALERT_BACKUP_COOLDOWN_SECONDS', 21600),

    /**
     * Content-Security-Policy for HTML pages: enforce (default), report-only (browsers only
     * warn in the console, for checking a new page) or off.
     */
    'csp' => env('SECURITY_CSP', 'enforce'),

    /**
     * Extra origins (space-separated) allowed as form targets, for example a single sign-on
     * provider whose login form the app posts to.
     */
    'csp_form_action_extra' => env('SECURITY_CSP_FORM_ACTION', ''),
];
