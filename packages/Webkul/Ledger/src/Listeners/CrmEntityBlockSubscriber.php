<?php

namespace Webkul\Ledger\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Webkul\Activity\Models\Activity;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Ledger\Support\LedgerAppender;
use Webkul\Product\Models\Product;
use Webkul\Quote\Models\Quote;

class CrmEntityBlockSubscriber
{
    public function __construct(
        private readonly LedgerAppender $appender,
    ) {}

    public function subscribe(Dispatcher $events): void
    {
        $events->listen('lead.create.after', fn (mixed $lead) => $this->recordMutation('lead.created', 'lead', Lead::class, $lead));
        $events->listen('lead.update.after', fn (mixed $lead) => $this->recordMutation('lead.updated', 'lead', Lead::class, $lead));
        $events->listen('lead.delete.before', fn (mixed $target) => $this->recordTombstone('lead.tombstoned', 'lead', Lead::class, $target));

        $events->listen('contacts.person.create.after', fn (mixed $person) => $this->recordMutation('person.created', 'person', Person::class, $person));
        $events->listen('contacts.person.update.after', fn (mixed $person) => $this->recordMutation('person.updated', 'person', Person::class, $person));
        $events->listen('contacts.person.delete.before', fn (mixed $target) => $this->recordTombstone('person.tombstoned', 'person', Person::class, $target));
        $events->listen('contact.person.delete.before', fn (mixed $target) => $this->recordTombstone('person.tombstoned', 'person', Person::class, $target));

        $events->listen('contacts.organization.create.after', fn (mixed $org) => $this->recordMutation('organization.created', 'organization', Organization::class, $org));
        $events->listen('contacts.organization.update.after', fn (mixed $org) => $this->recordMutation('organization.updated', 'organization', Organization::class, $org));
        $events->listen('contacts.organization.delete.before', fn (mixed $target) => $this->recordTombstone('organization.tombstoned', 'organization', Organization::class, $target));
        $events->listen('contact.organization.delete.before', fn (mixed $target) => $this->recordTombstone('organization.tombstoned', 'organization', Organization::class, $target));

        $events->listen('quote.create.after', fn (mixed $quote) => $this->recordMutation('quote.created', 'quote', Quote::class, $quote));
        $events->listen('quote.update.after', fn (mixed $quote) => $this->recordMutation('quote.updated', 'quote', Quote::class, $quote));
        $events->listen('quote.delete.before', fn (mixed $target) => $this->recordTombstone('quote.tombstoned', 'quote', Quote::class, $target));

        $events->listen('product.create.after', fn (mixed $product) => $this->recordMutation('product.created', 'product', Product::class, $product));
        $events->listen('product.update.after', fn (mixed $product) => $this->recordMutation('product.updated', 'product', Product::class, $product));
        $events->listen('product.delete.before', fn (mixed $target) => $this->recordTombstone('product.tombstoned', 'product', Product::class, $target));
        $events->listen('settings.products.delete.before', fn (mixed $target) => $this->recordTombstone('product.tombstoned', 'product', Product::class, $target));

        $events->listen('activity.create.after', fn (mixed $activity) => $this->recordMutation('activity.created', 'activity', Activity::class, $activity));
        $events->listen('activity.update.after', fn (mixed $activity) => $this->recordMutation('activity.updated', 'activity', Activity::class, $activity));
        $events->listen('activity.delete.before', fn (mixed $target) => $this->recordTombstone('activity.tombstoned', 'activity', Activity::class, $target));

        $events->listen('agent.call.settled', [self::class, 'handleAgentCallSettled']);
    }

    /**
     * @param  array<string, mixed>|null  $signerProof
     */
    public function handleAgentCallSettled(mixed $call, ?array $signerProof = null): void
    {
        if (! Schema::hasTable('data_blocks') || ! is_object($call)) {
            return;
        }

        $callId = data_get($call, 'id');

        if (! is_scalar($callId) || (string) $callId === '') {
            return;
        }

        $status = (string) (data_get($call, 'status') ?: 'settled');
        $decidedBy = data_get($call, 'decided_by');
        $userId = data_get($call, 'user_id');
        $passportId = data_get($call, 'agent_passport_id');

        $actorKind = $decidedBy !== null ? 'human' : 'agent';
        $rawActorId = $decidedBy ?? $userId;
        $actorId = is_numeric($rawActorId) ? (int) $rawActorId : null;
        $agentPassportId = is_numeric($passportId) ? (int) $passportId : null;

        $payload = $call instanceof Model ? $call->attributesToArray() : get_object_vars($call);
        unset($payload['block_hash'], $payload['receipt_code']);

        $block = $this->appender->append(
            eventName: 'agent.call.'.$status,
            entityType: 'agent_action_call',
            entityId: (string) $callId,
            payload: $payload,
            actorKind: $actorKind,
            actorId: $actorId,
            agentPassportId: $agentPassportId,
            customSigner: $signerProof,
        );

        if (Schema::hasTable('agent_action_calls')) {
            $updates = [];

            if (Schema::hasColumn('agent_action_calls', 'block_hash')) {
                $updates['block_hash'] = $block->block_hash;
            }

            if (Schema::hasColumn('agent_action_calls', 'receipt_code')) {
                $updates['receipt_code'] = $block->receipt_code;
            }

            if ($updates !== []) {
                DB::table('agent_action_calls')->where('id', $callId)->update($updates);

                if ($call instanceof Model) {
                    foreach ($updates as $column => $value) {
                        $call->setAttribute($column, $value);
                        $call->syncOriginalAttribute($column);
                    }
                }
            }
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function recordMutation(string $eventName, string $entityType, string $modelClass, mixed $subject): void
    {
        if (! Schema::hasTable('data_blocks')) {
            return;
        }

        $model = $this->resolveModel($modelClass, $subject);

        if ($model === null) {
            return;
        }

        $key = $model->getKey();

        if (! is_scalar($key)) {
            return;
        }

        [$actorKind, $actorId] = $this->resolveCurrentActor();

        $this->appender->append(
            eventName: $eventName,
            entityType: $entityType,
            entityId: (string) $key,
            payload: $this->extractModelSnapshot($model),
            actorKind: $actorKind,
            actorId: $actorId,
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function recordTombstone(string $eventName, string $entityType, string $modelClass, mixed $target): void
    {
        if (! Schema::hasTable('data_blocks')) {
            return;
        }

        $model = $this->resolveModel($modelClass, $target);

        if ($model !== null) {
            $key = $model->getKey();
            $entityId = is_scalar($key) ? (string) $key : '';
            $snapshot = $this->extractModelSnapshot($model);
        } elseif (is_scalar($target) && (string) $target !== '') {
            $entityId = (string) $target;
            $snapshot = ['id' => $target];
        } else {
            return;
        }

        if ($entityId === '') {
            return;
        }

        [$actorKind, $actorId] = $this->resolveCurrentActor();

        $payload = array_merge($snapshot, [
            '_tombstone' => true,
            'snapshot' => $snapshot,
        ]);

        $this->appender->append(
            eventName: $eventName,
            entityType: $entityType,
            entityId: $entityId,
            payload: $payload,
            actorKind: $actorKind,
            actorId: $actorId,
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveModel(string $modelClass, mixed $subject): ?Model
    {
        if ($subject instanceof Model) {
            return $subject;
        }

        if (is_scalar($subject) && (string) $subject !== '') {
            return $modelClass::query()->find($subject);
        }

        return null;
    }

    /**
     * @return array{0: string, 1: ?int}
     */
    private function resolveCurrentActor(): array
    {
        if (auth()->guard('user')->check()) {
            $id = auth()->guard('user')->id();

            return ['human', is_numeric($id) ? (int) $id : null];
        }

        if (auth()->guard('sanctum')->check()) {
            $id = auth()->guard('sanctum')->id();

            return ['agent', is_numeric($id) ? (int) $id : null];
        }

        return ['system', null];
    }

    /**
     * Read populated table attributes via Eloquent casts without allowing Krayin's CustomAttribute::attributesToArray()
     * to overwrite real columns with null EAV rows.
     *
     * @return array<string, mixed>
     */
    private function extractModelSnapshot(Model $model): array
    {
        $snapshot = [];
        $hidden = $model->getHidden();

        foreach (array_keys($model->getAttributes()) as $column) {
            if (in_array($column, $hidden, true)) {
                continue;
            }

            $snapshot[$column] = $model->getAttributeValue($column);
        }

        return $snapshot;
    }
}
