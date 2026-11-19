<?php

namespace Webkul\Agent\Support;

use Webkul\Agent\Models\AgentActionCall;

/**
 * The call currently being executed, available to the action while `handle()` runs.
 *
 * Actions stay transport-agnostic: they never read request state. The executor opens this scope
 * around a committed call so an action can link its own writes back to the receipt and to the
 * person who signed it. Dry runs never open a scope, so they cannot borrow a real call's identity.
 */
final class ActionCallContext
{
    private static ?AgentActionCall $call = null;

    public static function call(): ?AgentActionCall
    {
        return self::$call;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function using(?AgentActionCall $call, callable $callback): mixed
    {
        $previous = self::$call;
        self::$call = $call;

        try {
            return $callback();
        } finally {
            self::$call = $previous;
        }
    }
}
