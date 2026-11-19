<?php

namespace Webkul\Agent\Actions;

use Illuminate\Validation\Validator;
use Webkul\Agent\Support\JsonSchema;

/**
 * One thing an agent can do in the CRM, described well enough to be validated, permissioned,
 * listed in the context schema and offered as an MCP tool without any per-transport code.
 */
abstract class AgentAction
{
    public const APPROVAL_NEVER = 'never';

    public const APPROVAL_CONDITIONAL = 'conditional';

    public const APPROVAL_ALWAYS = 'always';

    /**
     * Stable identifier. Letters, digits and underscores only, so it is also a valid MCP tool name.
     */
    abstract public function name(): string;

    abstract public function summary(): string;

    /**
     * The ACL key the calling user must hold, from the same vocabulary the admin routes use.
     */
    abstract public function permission(): string;

    /**
     * Laravel validation rules, written as arrays of string rules so a JSON Schema can be derived.
     *
     * @return array<string, array<int, string>>
     */
    abstract public function rules(): array;

    /**
     * The action's rules plus `reason`, which every action accepts and none may redefine.
     *
     * @return array<string, array<int, string>>
     */
    final public function inputRules(): array
    {
        return $this->rules() + ['reason' => ['nullable', 'string', 'max:500']];
    }

    /**
     * @return array<string, mixed>
     */
    final public function schema(): array
    {
        $schema = JsonSchema::fromRules($this->inputRules());

        $schema['properties']['reason']['description'] = 'Why you are making this call, quoting the request of the person you work for. Shown to whoever approves it.';

        return $schema;
    }

    /**
     * The records a call needing approval will change, one flat row each, read as the calling user.
     * The approver sees these rows, and an approved call runs only if they still read the same.
     *
     * @param  array<string, mixed>  $params
     * @return array<int, array<string, scalar|null>>
     */
    public function subject(array $params): array
    {
        return [];
    }

    public function approval(): string
    {
        return self::APPROVAL_NEVER;
    }

    public function isMutating(): bool
    {
        return true;
    }

    /**
     * Monetary value affected by this call, used when a passport has an auto-approval ceiling.
     *
     * @param  array<string, mixed>  $params
     */
    public function estimatedValue(array $params): ?float
    {
        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function requiresApproval(array $params): bool
    {
        return $this->approval() === self::APPROVAL_ALWAYS;
    }

    /**
     * Optional cross-field validation hook invoked after standard string rules.
     *
     * @param  array<string, mixed>  $params
     */
    public function afterValidate(Validator $validator, array $params): void {}

    /**
     * Runs as the calling user on the `user` guard, inside a database transaction.
     *
     * @param  array<string, mixed>  $params  Validated parameters only.
     * @return array<string, mixed>
     */
    abstract public function handle(array $params): array;
}
