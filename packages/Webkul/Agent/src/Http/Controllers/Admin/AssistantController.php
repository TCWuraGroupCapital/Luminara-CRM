<?php

namespace Webkul\Agent\Http\Controllers\Admin;

use Anthropic\Core\Exceptions\AnthropicException;
use Anthropic\Core\Exceptions\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Agent\Models\AgentActionCall;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\Assistant;
use Webkul\Agent\Support\AssistantSettings;

class AssistantController extends Controller
{
    public const PERMISSION = 'agent_assistant';

    /**
     * The conversation lives in the user's own session, so nobody else can read or continue it.
     */
    private const HISTORY = 'agent.assistant.history';

    public function index(Request $request): View
    {
        $this->authorizeUser();

        /**
         * The page starts empty, so the conversation the model sees starts empty too.
         */
        session()->forget(self::HISTORY);

        return view('agent::assistant.index', [
            'configured' => AssistantSettings::configured(),
            'model' => AssistantSettings::resolve()['model'],
            'initialDraft' => $this->resolveInitialDraft($request),
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $this->authorizeUser();

        $text = $request->validate(['message' => ['required', 'string', 'max:4000']])['message'];

        if (! AssistantSettings::configured()) {
            return $this->error('not-configured', 503);
        }

        $history = session(self::HISTORY, []);

        if (count(array_filter($history, fn ($message) => is_string($message['content']))) >= config('agent.assistant.max_turns')) {
            return $this->error('full', 422);
        }

        $userId = auth()->guard('user')->id();

        if ($userId !== null) {
            Cache::forget(Assistant::stopCacheKey($userId));
        }

        try {
            $result = app(Assistant::class)->reply($history, $text);
        } catch (AuthenticationException) {
            return $this->error('not-configured', 503);
        } catch (AnthropicException $exception) {
            report($exception);

            return $this->error('unavailable', 503);
        }

        session([self::HISTORY => $result['history']]);

        $pending = in_array(AgentActionCall::PENDING_APPROVAL, array_column($result['calls'], 'status'), true);

        return new JsonResponse([
            'reply' => $result['reply'],
            'calls' => $result['calls'],
            'approvals_url' => $pending && bouncer()->hasPermission(ActionExecutor::APPROVAL_PERMISSION)
                ? route('admin.agent.approvals.index')
                : null,
        ]);
    }

    public function stop(): JsonResponse
    {
        $this->authorizeUser();

        $userId = auth()->guard('user')->id();

        if ($userId !== null) {
            Cache::put(Assistant::stopCacheKey($userId), true, 60);
        }

        return new JsonResponse(['ok' => true, 'stopped' => true]);
    }

    public function reset(): JsonResponse
    {
        $this->authorizeUser();

        $userId = auth()->guard('user')->id();

        if ($userId !== null) {
            Cache::forget(Assistant::stopCacheKey($userId));
        }

        session()->forget(self::HISTORY);

        return new JsonResponse(['ok' => true]);
    }

    private function resolveInitialDraft(Request $request): string
    {
        if ($request->filled('prompt')) {
            return mb_substr(trim((string) $request->query('prompt')), 0, 1000);
        }

        return match ((string) $request->query('skill', '')) {
            'intake' => 'Capture an inbound lead from this raw message: ',
            'quote' => 'Draft an itemized quote for contact ',
            'followup' => 'Review rotting deals and due activities today, then suggest or schedule follow-ups.',
            'briefing' => 'Give me today\'s morning briefing across waiting approvals, unreviewed drafts, and rotting leads.',
            default => '',
        };
    }

    private function error(string $key, int $status): JsonResponse
    {
        return new JsonResponse(['message' => trans('agent::app.assistant.errors.'.$key)], $status);
    }

    private function authorizeUser(): void
    {
        abort_unless(bouncer()->hasPermission(self::PERMISSION), 401, trans('admin::app.errors.unauthorized'));
    }
}
