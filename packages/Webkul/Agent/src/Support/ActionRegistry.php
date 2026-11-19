<?php

namespace Webkul\Agent\Support;

use LogicException;
use Webkul\Agent\Actions\AgentAction;

final class ActionRegistry
{
    /**
     * @var array<string, AgentAction>
     */
    private array $actions = [];

    /**
     * @param  array<int, AgentAction>  $actions
     */
    public function __construct(array $actions)
    {
        foreach ($actions as $action) {
            $name = $action->name();

            if (preg_match('/^[a-z][a-z0-9_]{0,63}$/', $name) !== 1) {
                throw new LogicException("Agent action name [{$name}] must be lowercase letters, digits and underscores.");
            }

            if (isset($action->rules()['reason'])) {
                throw new LogicException("Agent action [{$name}] may not define [reason]; every action already accepts it.");
            }

            if (isset($this->actions[$name])) {
                throw new LogicException("Agent action [{$name}] is registered twice.");
            }

            $this->actions[$name] = $action;
        }
    }

    public function find(string $name): ?AgentAction
    {
        return $this->actions[$name] ?? null;
    }

    /**
     * @return array<string, AgentAction>
     */
    public function all(): array
    {
        return $this->actions;
    }

    /**
     * The closest action name to a mistyped one. An action that needs approval is never suggested,
     * so a typo cannot steer an agent towards something destructive.
     */
    public function suggest(string $name): ?string
    {
        $best = null;
        $bestDistance = 4;

        foreach ($this->actions as $candidate => $action) {
            if ($action->approval() !== AgentAction::APPROVAL_NEVER) {
                continue;
            }

            $distance = levenshtein($name, $candidate);

            if ($distance < $bestDistance) {
                [$best, $bestDistance] = [$candidate, $distance];
            }
        }

        return $best;
    }
}
