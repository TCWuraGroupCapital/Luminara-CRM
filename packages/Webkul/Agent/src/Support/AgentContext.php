<?php

namespace Webkul\Agent\Support;

use Webkul\Agent\Actions\AgentAction;

/**
 * The machine-readable description of everything an agent can do here, built from the registry so
 * it cannot drift from the code.
 */
final class AgentContext
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private ActionRegistry $registry,
        private PlaybookMemory $playbook,
        private PassportGuard $passportGuard,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function describe(): array
    {
        $passport = $this->passportGuard->resolve();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'server' => ['name' => 'luminara-crm', 'version' => core()->luminaraVersion()],
            'conventions' => [
                'call' => 'POST /api/agent/actions/{name} with the parameters as a JSON object.',
                'dry_run' => 'Pass "dry_run": true or header X-Luminara-Dry-Run: 1 to simulate inside a rolled-back savepoint.',
                'retry' => 'Send an Idempotency-Key header. A repeat with the same key and parameters returns the first outcome and changes nothing.',
                'approval' => 'A call that needs approval answers 202 with status pending_approval. Read GET /api/agent/calls/{call_id} or GET /api/agent/events for the outcome.',
                'data' => McpServer::DATA_NOTICE,
                'errors' => 'Every error carries a code and next_steps. Follow next_steps before retrying.',
            ],
            'playbook' => [
                'version' => $this->playbook->version(),
                'blocks' => $this->playbook->allBlocks(),
                'summary' => $this->playbook->promptSummary(),
            ],
            'actions' => array_values(array_map(fn (AgentAction $action) => [
                'name' => $action->name(),
                'summary' => $action->summary(),
                'permission' => $action->permission(),
                'allowed' => bouncer()->hasPermission($action->permission())
                    && ($passport === null || ($passport->status && $passport->allowsAction($action->name()))),
                'approval' => $action->approval(),
                'params' => $action->schema(),
            ], $this->registry->all())),
        ];
    }
}
