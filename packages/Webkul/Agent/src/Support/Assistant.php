<?php

namespace Webkul\Agent\Support;

use Anthropic\Beta\Messages\BetaToolUseBlock;
use Anthropic\Client;
use Illuminate\Support\Facades\Cache;
use Webkul\Agent\Actions\AgentAction;

/**
 * The in-app assistant: a model that works the CRM for the signed-in user. It has no powers of its
 * own; every tool call goes through the same executor, permissions and approvals as an agent token.
 */
final class Assistant
{
    private const SYSTEM = 'You are the assistant inside Luminara CRM. You work for the signed-in user and act on the CRM '
        .'only through your tools, which run with that user\'s role and data scope. Do what they ask with the fewest calls, '
        .'then answer in a few plain sentences: what you found or changed, with record ids. Never claim a change your tools '
        .'did not report as completed. A result with status pending_approval has not happened yet: say a person must approve '
        .'it under Agent Approvals, and do not repeat the call. If no tool can do what was asked, say so. '
        .McpServer::DATA_NOTICE;

    public function __construct(
        private ActionRegistry $registry,
        private ActionExecutor $executor,
        private PlaybookMemory $playbook,
        private CustomerFacts $facts,
        private Client $client,
    ) {}

    public static function stopCacheKey(int|string $userId): string
    {
        return "agent.assistant.stop.{$userId}";
    }

    /**
     * Runs one user turn to its end, honoring server-side Stop requests between steps.
     *
     * @param  array<int, array<string, mixed>>  $history  The conversation so far, exactly as this method returned it.
     * @return array{reply: string, calls: array<int, array<string, mixed>>, history: array<int, array<string, mixed>>}
     */
    public function reply(array $history, string $text): array
    {
        $userId = auth()->guard('user')->id();
        $messages = [...$history, ['role' => 'user', 'content' => $text]];
        $calls = [];
        $tools = $this->tools();
        $newEntities = [];
        $seenEntities = [];

        for ($step = 0; $step < (int) config('agent.assistant.max_steps'); $step++) {
            if ($step > 0 && $userId !== null && Cache::pull(self::stopCacheKey($userId))) {
                return ['reply' => trans('agent::app.assistant.stopped'), 'calls' => $calls, 'history' => $messages];
            }

            $response = $this->client->beta->messages->create(...$this->request($messages, $tools));

            /**
             * The content goes back unchanged, thinking blocks included: the API requires them as sent.
             */
            $messages[] = ['role' => 'assistant', 'content' => $response->content];

            if ($response->stopReason === 'refusal') {
                return ['reply' => trans('agent::app.assistant.refused'), 'calls' => $calls, 'history' => $messages];
            }

            if ($response->stopReason !== 'tool_use') {
                return ['reply' => self::text($response->content), 'calls' => $calls, 'history' => $messages];
            }

            $results = [];
            $stopped = false;

            foreach ($response->content as $block) {
                if (! $block instanceof BetaToolUseBlock) {
                    continue;
                }

                if (! $stopped && $userId !== null && Cache::pull(self::stopCacheKey($userId))) {
                    $stopped = true;
                }

                if ($stopped) {
                    $results[] = [
                        'type' => 'tool_result',
                        'toolUseID' => $block->id,
                        'content' => 'Stopped by user before execution.',
                        'isError' => true,
                    ];

                    continue;
                }

                $outcome = $this->executor->execute($block->name, (array) $block->input);

                /**
                 * A contact the model has just read becomes eligible for approved customer facts.
                 * Facts are injected once per entity per turn, as data in the tool-result message.
                 */
                if ($block->name === 'contacts_get' && ($outcome->body['ok'] ?? false)) {
                    $personId = $outcome->body['result']['person']['id'] ?? null;

                    if (is_numeric($personId)) {
                        $key = CustomerFacts::ENTITY_PERSON.':'.(int) $personId;

                        if (! isset($seenEntities[$key])) {
                            $seenEntities[$key] = true;
                            $newEntities[] = [CustomerFacts::ENTITY_PERSON, (int) $personId];
                        }
                    }
                }

                $calls[] = array_filter([
                    'action' => $block->name,
                    'call_id' => $outcome->body['call_id'] ?? null,
                    'status' => $outcome->body['status'] ?? $outcome->body['error']['code'],
                ]);

                $results[] = [
                    'type' => 'tool_result',
                    'toolUseID' => $block->id,
                    'content' => McpServer::fence($outcome->body),
                    'isError' => ! $outcome->body['ok'],
                ];
            }

            if ($results === []) {
                return ['reply' => self::text($response->content), 'calls' => $calls, 'history' => $messages];
            }

            if (! $stopped && $newEntities !== []) {
                $factBlock = $this->facts->promptDataBlock($newEntities);

                if ($factBlock !== null) {
                    $results[] = ['type' => 'text', 'text' => $factBlock];
                }
            }

            $newEntities = [];
            $messages[] = ['role' => 'user', 'content' => $results];

            if ($stopped) {
                return ['reply' => trans('agent::app.assistant.stopped'), 'calls' => $calls, 'history' => $messages];
            }
        }

        return ['reply' => trans('agent::app.assistant.too-many-steps'), 'calls' => $calls, 'history' => $messages];
    }

    /**
     * Only the actions this user may call are offered, so the model never plans around a refusal.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tools(): array
    {
        return array_values(array_map(fn (AgentAction $action) => [
            'name' => $action->name(),
            'description' => $action->summary()
                .($action->approval() === AgentAction::APPROVAL_NEVER ? '' : ' May wait for a person to approve it.'),
            'inputSchema' => $action->schema(),
        ], array_filter(
            $this->registry->all(),
            fn (AgentAction $action) => bouncer()->hasPermission($action->permission())
        )));
    }

    /**
     * Server-side refusal fallback exists only on Anthropic's own API, so it is left off when
     * `base_url` points at another Messages-compatible service.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     * @return array<string, mixed>
     */
    private function request(array $messages, array $tools): array
    {
        $settings = AssistantSettings::resolve();
        $anthropic = $settings['base_url'] === null;
        $playbookSummary = $this->playbook->promptSummary();
        $system = $playbookSummary === ''
            ? self::SYSTEM
            : self::SYSTEM."\n\n".$playbookSummary;

        return array_filter([
            'model' => $settings['model'],
            'maxTokens' => 16000,
            'system' => $system,
            'messages' => $messages,
            'tools' => $tools,
            'outputConfig' => ['effort' => 'medium'],
            'cacheControl' => ['type' => 'ephemeral'],
            'betas' => $anthropic ? ['server-side-fallback-2026-07-01'] : null,
            'fallbacks' => $anthropic ? 'default' : null,
        ], fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  array<int, mixed>  $content
     */
    private static function text(array $content): string
    {
        $text = '';

        foreach ($content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }
}
