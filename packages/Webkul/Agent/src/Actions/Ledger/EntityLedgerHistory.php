<?php

namespace Webkul\Agent\Actions\Ledger;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Agent\Actions\AgentAction;
use Webkul\Agent\Support\ActionExecutor;

class EntityLedgerHistory extends AgentAction
{
    public function name(): string
    {
        return 'ledger_history';
    }

    public function summary(): string
    {
        return 'List immutable Data Block receipts recorded for an entity type and id.';
    }

    public function permission(): string
    {
        return 'leads';
    }

    public function isMutating(): bool
    {
        return false;
    }

    public function rules(): array
    {
        return [
            'entity_type' => ['required', 'string', 'max:64'],
            'entity_id' => ['required', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    public function handle(array $params): array
    {
        if (! Schema::hasTable('data_blocks')) {
            return [
                'ledger_enabled' => false,
                'blocks' => [],
            ];
        }

        $entityType = self::normalizeEntityType((string) $params['entity_type']);
        $entityId = (int) $params['entity_id'];

        if ($entityType === null) {
            return [
                'ledger_enabled' => true,
                'blocks' => [],
            ];
        }

        $authorizedIds = bouncer()->getAuthorizedUserIds();

        if (! $this->canInspectEntity($entityType, $entityId, $authorizedIds)) {
            return [
                'ledger_enabled' => true,
                'blocks' => [],
            ];
        }

        $rows = DB::table('data_blocks')
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->orderByDesc('id')
            ->limit($params['limit'] ?? 25)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id ?? null,
                'height' => isset($row->block_height) ? (int) $row->block_height : null,
                'block_height' => isset($row->block_height) ? (int) $row->block_height : null,
                'block_hash' => $row->block_hash ?? null,
                'prev_block_hash' => $row->prev_block_hash ?? null,
                'prev_hash' => $row->prev_block_hash ?? null,
                'receipt_code' => $row->receipt_code ?? null,
                'event_name' => $row->event_name ?? null,
                'event_type' => $row->event_name ?? null,
                'signer_alg' => $row->signer_alg ?? null,
                'created_at' => isset($row->created_at) ? (string) $row->created_at : null,
            ])
            ->all();

        return [
            'ledger_enabled' => true,
            'blocks' => $rows,
        ];
    }

    private static function normalizeEntityType(string $raw): ?string
    {
        return match (strtolower(trim($raw))) {
            'lead', 'leads' => 'lead',
            'person', 'persons' => 'person',
            'organization', 'organizations' => 'organization',
            'quote', 'quotes' => 'quote',
            'activity', 'activities' => 'activity',
            'product', 'products' => 'product',
            'agent_call', 'agent_action_call', 'agent_action_calls' => 'agent_action_call',
            default => null,
        };
    }

    /**
     * @param  array<int, int>|null  $authorizedIds
     */
    private function canInspectEntity(string $entityType, int $entityId, ?array $authorizedIds): bool
    {
        $callerId = auth()->guard('user')->id();

        if ($entityType === 'product') {
            return bouncer()->hasPermission('products');
        }

        $requiredPermission = match ($entityType) {
            'lead' => 'leads',
            'person' => 'contacts.persons',
            'organization' => 'contacts.organizations',
            'quote' => 'quotes',
            'activity' => 'activities',
            'agent_action_call' => ActionExecutor::APPROVAL_PERMISSION,
            default => null,
        };

        $hasModulePermission = $requiredPermission !== null && bouncer()->hasPermission($requiredPermission);

        if (! $hasModulePermission && $entityType !== 'agent_action_call') {
            return false;
        }

        $ownerTable = match ($entityType) {
            'lead' => 'leads',
            'person' => 'persons',
            'organization' => 'organizations',
            'quote' => 'quotes',
            'activity' => 'activities',
            default => 'agent_action_calls',
        };

        if (! Schema::hasTable($ownerTable)) {
            return false;
        }

        $record = DB::table($ownerTable)->where('id', $entityId)->first(['user_id']);

        if ($record !== null) {
            if ($entityType === 'agent_action_call' && ! $hasModulePermission) {
                return $callerId !== null && $record->user_id !== null && (int) $record->user_id === (int) $callerId;
            }

            if ($authorizedIds === null) {
                return true;
            }

            return $record->user_id !== null && in_array((int) $record->user_id, $authorizedIds, true);
        }

        if ($entityType === 'agent_action_call' && ! $hasModulePermission) {
            return false;
        }

        if ($authorizedIds === null) {
            return true;
        }

        $latestBlock = DB::table('data_blocks')
            ->where('entity_type', $entityType)
            ->where('entity_id', (string) $entityId)
            ->orderByDesc('id')
            ->first(['actor_id', 'canonical_payload']);

        if ($latestBlock === null) {
            return false;
        }

        $payload = is_string($latestBlock->canonical_payload ?? null)
            ? json_decode($latestBlock->canonical_payload, true)
            : null;
        $ownerId = is_array($payload)
            ? ($payload['user_id'] ?? ($payload['snapshot']['user_id'] ?? $latestBlock->actor_id ?? null))
            : ($latestBlock->actor_id ?? null);

        return is_numeric($ownerId) && in_array((int) $ownerId, $authorizedIds, true);
    }
}
