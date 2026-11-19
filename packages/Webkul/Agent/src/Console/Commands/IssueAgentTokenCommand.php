<?php

namespace Webkul\Agent\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Agent\Http\Middleware\AuthenticateAgentToken;
use Webkul\Security\Support\SecurityAuditRecorder;
use Webkul\User\Repositories\UserRepository;

class IssueAgentTokenCommand extends Command
{
    protected $signature = 'luminara:agent-token
                            {email : The CRM user the agent acts as; it gets that user\'s role and data scope}
                            {--name=agent : A label to recognise the token by}
                            {--days=90 : Days until the token expires}';

    protected $description = 'Issue a bearer token that lets an AI agent act as a CRM user through /api/agent.';

    public function handle(UserRepository $userRepository): int
    {
        $user = $userRepository->findOneByField('email', $this->argument('email'));
        $days = (int) $this->option('days');

        if (! $user || ! $user->status) {
            $this->error('No active user has that email.');

            return self::FAILURE;
        }

        if ($days < 1 || $days > 365) {
            $this->error('--days must be between 1 and 365.');

            return self::FAILURE;
        }

        $token = $user->createToken((string) $this->option('name'), [AuthenticateAgentToken::ABILITY], now()->addDays($days));

        SecurityAuditRecorder::record('agent.token.issued', 'personal_access_token', $token->accessToken->getKey(), [
            'name' => $token->accessToken->name,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ], $user->id, $user->email);

        $this->line($token->plainTextToken);
        $this->comment('Shown once. Store it in the agent\'s secret store; it expires in '.$days.' days.');

        return self::SUCCESS;
    }
}
