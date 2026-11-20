<?php

namespace Webkul\Agent\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Support\AgentWebhookDispatcher;
use Webkul\Agent\Support\PassportGuard;
use Webkul\User\Models\User;
use Webkul\User\Repositories\UserRepository;

class IssuePassportCommand extends Command
{
    protected $signature = 'luminara:passport
                            {email : The CRM user the passport acts as}
                            {slug : Lowercase slug (e.g. grokbot, antigravity, claude-code, hermes)}
                            {--name= : Display label for the passport}
                            {--scopes=* : Allowed action names or wildcard prefixes (default * allows all role actions)}
                            {--daily-limit=1000 : Maximum calls per UTC day}
                            {--ceiling= : Optional monetary ceiling for earned_auto conditional approvals}
                            {--trust=standard : Trust policy: standard, always_confirm, or earned_auto}
                            {--webhook= : Optional HTTPS webhook URL notified when calls are decided}
                            {--days=90 : Days until the token expires (1-365)}
                            {--json : Output machine-readable JSON}';

    protected $description = 'Issue or rotate a scoped Agent Passport with daily budget, trust mode, and bearer token.';

    public function handle(
        UserRepository $userRepository,
        AgentWebhookDispatcher $webhooks,
        PassportGuard $passportGuard,
    ): int {
        /** @var User|null $user */
        $user = $userRepository->findOneByField('email', (string) $this->argument('email'));
        $slug = strtolower(trim((string) $this->argument('slug')));
        $days = (int) $this->option('days');
        $dailyLimit = (int) $this->option('daily-limit');
        $trust = (string) $this->option('trust');

        if (! $user instanceof User || ! $user->getAttribute('status')) {
            $this->error('No active user has that email.');

            return self::FAILURE;
        }

        if (! preg_match('/^[a-z0-9][a-z0-9_-]{1,63}$/', $slug)) {
            $this->error('Passport slug must be 2-64 chars of lowercase letters, digits, hyphens, or underscores.');

            return self::FAILURE;
        }

        if ($days < 1 || $days > 365) {
            $this->error('--days must be between 1 and 365.');

            return self::FAILURE;
        }

        if ($dailyLimit < 1) {
            $this->error('--daily-limit must be at least 1.');

            return self::FAILURE;
        }

        if (! in_array($trust, [
            AgentPassport::TRUST_STANDARD,
            AgentPassport::TRUST_ALWAYS_CONFIRM,
            AgentPassport::TRUST_EARNED_AUTO,
        ], true)) {
            $this->error('--trust must be standard, always_confirm, or earned_auto.');

            return self::FAILURE;
        }

        $webhookUrl = $this->option('webhook');
        $cleanWebhook = is_string($webhookUrl) && trim($webhookUrl) !== '' ? trim($webhookUrl) : null;

        if ($cleanWebhook !== null && ! $webhooks->isSafeUrl($cleanWebhook)) {
            $this->error('The --webhook URL is disallowed (must be a public endpoint).');

            return self::FAILURE;
        }

        $ceilingRaw = $this->option('ceiling');

        if ($ceilingRaw !== null && $ceilingRaw !== '') {
            if (! is_numeric($ceilingRaw) || (float) $ceilingRaw < 0 || (float) $ceilingRaw > 9999999999.99) {
                $this->error('--ceiling must be a number between 0 and 9999999999.99.');

                return self::FAILURE;
            }
        }

        $issued = $passportGuard->issue($user, $slug, [
            'name' => $this->option('name') ? (string) $this->option('name') : null,
            'allowed_actions' => PassportGuard::parseScopes((array) $this->option('scopes')),
            'daily_call_limit' => $dailyLimit,
            'auto_approve_value_ceiling' => $ceilingRaw !== null && $ceilingRaw !== '' ? (float) $ceilingRaw : null,
            'trust_mode' => $trust,
            'webhook_url' => $cleanWebhook,
            'days' => $days,
        ]);

        $passport = $issued['passport'];
        $plainTextToken = $issued['token'];
        $webhookSecret = $issued['webhook_secret'];
        $userEmail = (string) ($user->getAttribute('email') ?? '');
        $mcpUrl = url('/api/agent/mcp');

        $payload = [
            'ok' => true,
            'passport' => [
                'id' => $passport->id,
                'slug' => $passport->slug,
                'name' => $passport->name,
                'user_email' => $userEmail,
                'allowed_actions' => $passport->allowed_actions ?? ['*'],
                'daily_call_limit' => $passport->daily_call_limit,
                'trust_mode' => $passport->trust_mode,
                'auto_approve_value_ceiling' => $passport->auto_approve_value_ceiling,
                'webhook_url' => $passport->webhook_url,
                'expires_at' => $issued['expires_at'],
            ],
            'token' => $plainTextToken,
            'webhook_secret' => $webhookSecret,
            'mcp_config' => [
                'mcpServers' => [
                    'luminara-crm' => [
                        'type' => 'http',
                        'url' => $mcpUrl,
                        'headers' => ['Authorization' => 'Bearer '.$plainTextToken],
                    ],
                ],
            ],
            'cli_example' => "php artisan luminara:act briefing_today --as={$userEmail} --passport={$passport->slug} --json",
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($payload, JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Issued Agent Passport [{$passport->slug}] for {$userEmail}");
        $this->line('Token: '.$plainTextToken);

        if ($webhookSecret !== null) {
            $this->line('Webhook Secret: '.$webhookSecret);
        }

        $this->comment('CLI: '.$payload['cli_example']);
        $this->comment('MCP Endpoint: '.$mcpUrl);

        return self::SUCCESS;
    }
}
