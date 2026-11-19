<?php

namespace Webkul\Agent\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\ActionOutcome;
use Webkul\Agent\Support\PassportGuard;
use Webkul\User\Models\User;
use Webkul\User\Repositories\UserRepository;

class RunAgentActionCommand extends Command
{
    public const EXIT_COMPLETED = 0;

    public const EXIT_PENDING_APPROVAL = 2;

    public const EXIT_ERROR = 3;

    protected $signature = 'luminara:act
                            {action : Action identifier to invoke (e.g. briefing_today, leads_search, intake_capture)}
                            {--params= : JSON object of action parameters}
                            {--as= : Email of the CRM user to run as (defaults to first active user)}
                            {--passport= : Optional Agent Passport slug owned by the user}
                            {--idempotency-key= : Optional Idempotency-Key}
                            {--dry-run : Simulate inside a rolled-back savepoint without persisting changes}
                            {--json : Print compact single-line JSON}';

    protected $description = 'Execute a Luminara CRM agent action over the local CLI transport.';

    public function handle(
        UserRepository $userRepository,
        ActionExecutor $executor,
        PassportGuard $passportGuard,
    ): int {
        $asEmail = $this->option('as');

        /** @var User|null $user */
        $user = $asEmail
            ? $userRepository->findOneByField('email', (string) $asEmail)
            : User::query()->where('status', 1)->orderBy('id')->first();

        if (! $user instanceof User || ! $user->getAttribute('status')) {
            return $this->emitOutcome(
                ActionOutcome::error(403, 'unauthenticated', 'No active CRM user found for --as.'),
                self::EXIT_ERROR
            );
        }

        $rawParams = trim((string) ($this->option('params') ?? ''));
        $decoded = $rawParams === '' ? [] : json_decode($rawParams, true);

        if (
            ($rawParams !== '' && json_last_error() !== JSON_ERROR_NONE)
            || ! is_array($decoded)
            || ($decoded !== [] && array_is_list($decoded))
        ) {
            return $this->emitOutcome(
                ActionOutcome::error(422, 'invalid_json', '--params must be a valid JSON object.'),
                self::EXIT_ERROR
            );
        }

        $passport = null;
        $passportSlug = $this->option('passport');
        $userEmail = (string) $user->getAttribute('email');

        if (is_string($passportSlug) && trim($passportSlug) !== '') {
            $passport = AgentPassport::query()
                ->where('user_id', $user->id)
                ->where('slug', trim($passportSlug))
                ->first();

            if (! $passport instanceof AgentPassport) {
                return $this->emitOutcome(
                    ActionOutcome::error(404, 'passport_not_found', "No passport [{$passportSlug}] belongs to [{$userEmail}]."),
                    self::EXIT_ERROR
                );
            }
        }

        $idempotencyKey = $this->option('idempotency-key');
        $key = is_string($idempotencyKey) && $idempotencyKey !== '' ? $idempotencyKey : null;
        $dryRun = (bool) $this->option('dry-run');

        auth()->guard('user')->setUser($user);

        $outcome = $passportGuard->withPassport(
            $passport,
            fn () => $executor->execute((string) $this->argument('action'), $decoded, $key, $dryRun)
        );

        if (! ($outcome->body['ok'] ?? false)) {
            return $this->emitOutcome($outcome, self::EXIT_ERROR);
        }

        if (($outcome->body['status'] ?? null) === AgentActionCall::PENDING_APPROVAL) {
            return $this->emitOutcome($outcome, self::EXIT_PENDING_APPROVAL);
        }

        return $this->emitOutcome($outcome, self::EXIT_COMPLETED);
    }

    private function emitOutcome(ActionOutcome $outcome, int $exitCode): int
    {
        $flags = $this->option('json')
            ? JSON_UNESCAPED_SLASHES
            : JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES;

        $this->line((string) json_encode($outcome->body, $flags));

        return $exitCode;
    }
}
