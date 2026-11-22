<?php

namespace Webkul\Security\Observers;

use Illuminate\Database\Eloquent\Model;
use Throwable;
use Webkul\Activity\Models\Activity;
use Webkul\Attribute\Models\Attribute;
use Webkul\Attribute\Models\AttributeValue;
use Webkul\Contact\Models\Organization;
use Webkul\Contact\Models\Person;
use Webkul\Lead\Models\Lead;
use Webkul\Quote\Models\Quote;
use Webkul\Security\Support\SecurityAuditRecorder;

/**
 * Records create, update, and delete for CRM records.
 *
 * Only field names are kept. Attribute values, including custom-field values, are never copied into the audit log.
 */
class EntityAuditObserver
{
    private const IGNORED_FIELDS = ['id', 'created_at', 'updated_at'];

    private const VALUE_COLUMNS = [
        'text_value',
        'boolean_value',
        'integer_value',
        'float_value',
        'datetime_value',
        'date_value',
        'json_value',
    ];

    /** @var array<class-string, string> */
    private const TYPES = [
        Person::class => 'person',
        Organization::class => 'organization',
        Lead::class => 'lead',
        Quote::class => 'quote',
        Activity::class => 'activity',
    ];

    /** @var array<string, string> */
    private const CUSTOM_ENTITIES = [
        'persons' => 'person',
        'organizations' => 'organization',
        'leads' => 'lead',
        'quotes' => 'quote',
        'activities' => 'activity',
    ];

    /** @var array<string, string|null> */
    private array $attributeCodes = [];

    public function created(Model $model): void
    {
        $this->guard(function () use ($model): void {
            if ($model instanceof AttributeValue) {
                $this->noteCustomField($model);

                return;
            }

            $this->recordModel($model, 'created');
        });
    }

    public function updated(Model $model): void
    {
        $this->guard(function () use ($model): void {
            if ($model instanceof AttributeValue) {
                if ($model->wasChanged(self::VALUE_COLUMNS)) {
                    $this->noteCustomField($model);
                }

                return;
            }

            $this->recordModel($model, 'updated');
        });
    }

    public function deleted(Model $model): void
    {
        $this->guard(function () use ($model): void {
            if ($model instanceof AttributeValue) {
                return;
            }

            $this->recordModel($model, 'deleted');
        });
    }

    private function recordModel(Model $model, string $action): void
    {
        $type = $this->typeFor($model);

        if ($type === null) {
            return;
        }

        $fields = $action === 'deleted' ? [] : $this->changedFields($model, $action);

        if ($action === 'updated' && $fields === []) {
            return;
        }

        $key = $model->getKey();

        SecurityAuditRecorder::record($type.'.'.$action, $type, is_scalar($key) ? $key : null, [
            'action' => $action,
            'fields' => $fields,
        ]);
    }

    private function noteCustomField(AttributeValue $value): void
    {
        $entityType = $value->getAttribute('entity_type');
        $type = is_string($entityType) ? (self::CUSTOM_ENTITIES[$entityType] ?? null) : null;
        $entityId = $value->getAttribute('entity_id');

        if ($type === null || ! is_scalar($entityId)) {
            return;
        }

        $attributeId = $value->getAttribute('attribute_id');

        if (! is_numeric($attributeId)) {
            return;
        }

        $code = $this->attributeCode((int) $attributeId);

        if ($code === null) {
            return;
        }

        SecurityAuditRecorder::noteFields($type, $entityId, [$code]);
    }

    private function attributeCode(int $id): ?string
    {
        $connection = (string) config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $cacheKey = $database.':'.$id;

        if (array_key_exists($cacheKey, $this->attributeCodes)) {
            return $this->attributeCodes[$cacheKey];
        }

        $code = Attribute::query()->whereKey($id)->value('code');
        $this->attributeCodes[$cacheKey] = is_string($code) && $code !== '' ? $code : null;

        return $this->attributeCodes[$cacheKey];
    }

    /**
     * @return list<string>
     */
    private function changedFields(Model $model, string $action): array
    {
        $source = $action === 'created' ? $model->getAttributes() : $model->getChanges();
        $names = [];

        foreach ($source as $key => $value) {
            if (in_array($key, self::IGNORED_FIELDS, true)) {
                continue;
            }

            if ($action === 'created' && $value === null) {
                continue;
            }

            $names[] = $key;
        }

        return SecurityAuditRecorder::fieldNames($names);
    }

    private function typeFor(Model $model): ?string
    {
        foreach (self::TYPES as $class => $type) {
            if ($model instanceof $class) {
                return $type;
            }
        }

        return null;
    }

    private function guard(callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            if (app()->runningUnitTests()) {
                throw $e;
            }

            report($e);
        }
    }
}
