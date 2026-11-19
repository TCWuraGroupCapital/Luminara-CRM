<?php

namespace Webkul\Agent\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Models\AgentPlaybookBlock;
use Webkul\Agent\Models\AgentPreferenceSignal;

/**
 * Versioned business playbook memory and Edit-to-Teach preference distillation.
 */
final class PlaybookMemory
{
    public const SLOTS = [
        'business_identity',
        'pricing_guardrails',
        'communication_voice',
        'active_priorities',
    ];

    public const MAX_SLOT_LENGTH = 2000;

    public function isValidSlot(string $slot): bool
    {
        return in_array($slot, self::SLOTS, true);
    }

    /**
     * @return array<string, array{slot: string, content: string, version: int, updated_at: ?string}>
     */
    public function allBlocks(): array
    {
        $rows = Schema::hasTable('agent_playbook_blocks')
            ? AgentPlaybookBlock::query()->whereIn('slot', self::SLOTS)->get()->keyBy('slot')
            : collect();

        $blocks = [];

        foreach (self::SLOTS as $slot) {
            /** @var AgentPlaybookBlock|null $row */
            $row = $rows->get($slot);

            $blocks[$slot] = [
                'slot' => $slot,
                'content' => $row ? $row->content : '',
                'version' => $row ? $row->version : 0,
                'updated_at' => $row?->updated_at?->toIso8601String(),
            ];
        }

        return $blocks;
    }

    public function version(): int
    {
        return array_sum(array_column($this->allBlocks(), 'version'));
    }

    public function upsertBlock(string $slot, string $content, ?int $userId = null, ?int $sourceCallId = null): AgentPlaybookBlock
    {
        if (! $this->isValidSlot($slot)) {
            throw new InvalidArgumentException("Unknown playbook slot [{$slot}].");
        }

        $clean = trim($content);

        if (mb_strlen($clean) > self::MAX_SLOT_LENGTH) {
            throw new InvalidArgumentException('Playbook slot content must not exceed '.self::MAX_SLOT_LENGTH.' characters.');
        }

        return DB::transaction(function () use ($slot, $clean, $userId, $sourceCallId): AgentPlaybookBlock {
            /** @var AgentPlaybookBlock|null $existing */
            $existing = AgentPlaybookBlock::query()->where('slot', $slot)->lockForUpdate()->first();

            if ($existing) {
                $existing->update([
                    'content' => $clean,
                    'version' => $existing->version + 1,
                    'updated_by_user_id' => $userId,
                    'source_call_id' => $sourceCallId,
                ]);

                return $existing->refresh();
            }

            /** @var AgentPlaybookBlock $created */
            $created = AgentPlaybookBlock::query()->create([
                'slot' => $slot,
                'content' => $clean,
                'version' => 1,
                'updated_by_user_id' => $userId,
                'source_call_id' => $sourceCallId,
            ]);

            return $created;
        });
    }

    /**
     * @param  array<string, mixed>  $originalParams
     * @param  array<string, mixed>  $editedParams
     */
    public function recordEditCorrection(
        AgentActionCall $call,
        array $originalParams,
        array $editedParams,
        ?string $feedbackNote = null,
        bool $appendPlaybookRule = false,
    ): AgentPreferenceSignal {
        $diff = self::diffParams($originalParams, $editedParams);
        $note = $feedbackNote !== null && trim($feedbackNote) !== '' ? trim($feedbackNote) : null;

        $summary = $note !== null
            ? "On {$call->action}: {$note}"
            : ($diff !== []
                ? "On {$call->action}: human adjusted ".implode(', ', array_keys($diff))
                : "On {$call->action}: human reviewed parameters before signing");

        $distilled = mb_substr($summary, 0, 280);
        $actorId = $call->decided_by ?? (is_numeric(auth()->guard('user')->id()) ? (int) auth()->guard('user')->id() : null);

        /** @var AgentPreferenceSignal $signal */
        $signal = AgentPreferenceSignal::query()->create([
            'call_id' => $call->id,
            'user_id' => $actorId,
            'action' => $call->action,
            'signal_type' => 'edit_correction',
            'param_diff' => $diff,
            'feedback_note' => $note !== null ? mb_substr($note, 0, 500) : null,
            'distilled_rule' => $distilled,
        ]);

        if ($appendPlaybookRule) {
            $this->appendRuleToSlot(self::slotForAction($call->action, $diff), $distilled, $actorId, $call->id);
        }

        return $signal;
    }

    public function recordRejectionFeedback(
        AgentActionCall $call,
        string $feedbackNote,
        bool $appendPlaybookRule = false,
    ): AgentPreferenceSignal {
        $note = mb_substr(trim($feedbackNote), 0, 500);
        $distilled = mb_substr("Avoid on {$call->action}: {$note}", 0, 280);
        $actorId = $call->decided_by ?? (is_numeric(auth()->guard('user')->id()) ? (int) auth()->guard('user')->id() : null);

        /** @var AgentPreferenceSignal $signal */
        $signal = AgentPreferenceSignal::query()->create([
            'call_id' => $call->id,
            'user_id' => $actorId,
            'action' => $call->action,
            'signal_type' => 'rejection_feedback',
            'param_diff' => null,
            'feedback_note' => $note,
            'distilled_rule' => $distilled,
        ]);

        if ($appendPlaybookRule && $note !== '') {
            $this->appendRuleToSlot(self::slotForAction($call->action), $distilled, $actorId, $call->id);
        }

        return $signal;
    }

    public function appendRuleToSlot(string $slot, string $rule, ?int $userId = null, ?int $sourceCallId = null): AgentPlaybookBlock
    {
        $existing = $this->allBlocks()[$slot]['content'] ?? '';
        $bullet = '- '.trim($rule);
        $combined = $existing === '' ? $bullet : $existing."\n".$bullet;

        while (mb_strlen($combined) > self::MAX_SLOT_LENGTH && str_contains($combined, "\n")) {
            $pos = mb_strpos($combined, "\n");
            $combined = $pos !== false ? mb_substr($combined, $pos + 1) : $combined;
        }

        $combined = mb_substr($combined, 0, self::MAX_SLOT_LENGTH);

        return $this->upsertBlock($slot, $combined, $userId, $sourceCallId);
    }

    public function promptSummary(): string
    {
        $sections = [];

        foreach ($this->allBlocks() as $slot => $block) {
            $sanitized = str_ireplace('business_playbook', 'business-playbook', trim($block['content']));

            if ($sanitized === '') {
                continue;
            }

            $sections[] = "[{$slot}]\n".$sanitized;
        }

        if ($sections === []) {
            return '';
        }

        return "<business_playbook>\n".implode("\n\n", $sections)."\n</business_playbook>";
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private static function diffParams(array $before, array $after): array
    {
        $diff = [];

        foreach (array_unique(array_merge(array_keys($before), array_keys($after))) as $key) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old !== $new) {
                $diff[(string) $key] = ['from' => $old, 'to' => $new];
            }
        }

        return $diff;
    }

    /**
     * @param  array<string, mixed>  $diff
     */
    private static function slotForAction(string $action, array $diff = []): string
    {
        if (
            str_starts_with($action, 'quotes_')
            || isset($diff['discount_percent'])
            || isset($diff['lead_value'])
            || isset($diff['items'])
        ) {
            return 'pricing_guardrails';
        }

        if (str_starts_with($action, 'contacts_') || str_starts_with($action, 'activities_')) {
            return 'communication_voice';
        }

        return 'active_priorities';
    }
}
