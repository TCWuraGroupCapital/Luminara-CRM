<?php

namespace Webkul\Agent\Http\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\Agent\Models\AgentPassport;
use Webkul\Agent\Support\ActionExecutor;
use Webkul\Agent\Support\AgentWebhookDispatcher;
use Webkul\Agent\Support\BriefingData;
use Webkul\Agent\Support\PassportGuard;
use Webkul\Agent\Support\PlaybookMemory;
use Webkul\User\Models\User;

class BriefingController extends Controller
{
    public const PERMISSION = 'agent_briefing';

    public function index(ActionExecutor $executor, BriefingData $briefing): View
    {
        $this->authorizeUser();

        $executor->settleStale();

        /** @var User $user */
        $user = auth()->guard('user')->user();

        $passports = Schema::hasTable('agent_passports')
            ? AgentPassport::query()->where('user_id', $user->id)->latest('id')->get()
            : collect();

        return view('agent::briefing.index', [
            'briefing' => $briefing->build(),
            'passports' => $passports,
            'presets' => self::presets(),
        ]);
    }

    public function storePassport(
        Request $request,
        AgentWebhookDispatcher $webhooks,
        PassportGuard $passportGuard,
    ): RedirectResponse|JsonResponse {
        $this->authorizeUser();

        $validated = $request->validate([
            'slug' => ['required', 'string', 'regex:/^[a-z0-9][a-z0-9_-]{1,63}$/'],
            'name' => ['nullable', 'string', 'max:120'],
            'scopes' => ['nullable', 'string', 'max:1000'],
            'daily_call_limit' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'auto_approve_value_ceiling' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'trust_mode' => ['nullable', 'string', 'in:standard,always_confirm,earned_auto'],
            'webhook_url' => ['nullable', 'string', 'max:500'],
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $webhookUrl = ! empty($validated['webhook_url']) ? trim((string) $validated['webhook_url']) : null;

        if ($webhookUrl !== null && ! $webhooks->isSafeUrl($webhookUrl)) {
            if ($request->wantsJson()) {
                return new JsonResponse(['ok' => false, 'message' => 'Webhook URL must be a public HTTPS endpoint.'], 422);
            }

            session()->flash('error', 'Webhook URL must be a public HTTPS endpoint.');

            return redirect()->route('admin.agent.briefing.index');
        }

        /** @var User $user */
        $user = auth()->guard('user')->user();
        $result = $passportGuard->issue($user, (string) $validated['slug'], [
            'name' => ! empty($validated['name']) ? (string) $validated['name'] : null,
            'allowed_actions' => PassportGuard::parseScopes((string) ($validated['scopes'] ?? '*')),
            'daily_call_limit' => (int) ($validated['daily_call_limit'] ?? 1000),
            'auto_approve_value_ceiling' => isset($validated['auto_approve_value_ceiling']) && $validated['auto_approve_value_ceiling'] !== ''
                ? (float) $validated['auto_approve_value_ceiling']
                : null,
            'trust_mode' => (string) ($validated['trust_mode'] ?? AgentPassport::TRUST_STANDARD),
            'webhook_url' => $webhookUrl,
            'days' => (int) ($validated['days'] ?? 90),
        ]);

        $passport = $result['passport'];
        $userEmail = (string) ($user->getAttribute('email') ?? '');

        $issued = [
            'slug' => $passport->slug,
            'name' => $passport->name,
            'token' => $result['token'],
            'webhook_secret' => $result['webhook_secret'],
            'mcp_url' => url('/api/agent/mcp'),
            'cli' => "php artisan luminara:act briefing_today --as={$userEmail} --passport={$passport->slug} --json",
        ];

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'issued' => $issued], 201);
        }

        session()->flash('issued_passport', $issued);
        session()->flash('success', trans('agent::app.briefing.passport-issued'));

        return redirect()->route('admin.agent.briefing.index');
    }

    public function togglePassport(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorizeUser();

        /** @var User $user */
        $user = auth()->guard('user')->user();

        /** @var AgentPassport $passport */
        $passport = AgentPassport::query()
            ->where('user_id', $user->id)
            ->whereKey($id)
            ->firstOrFail();

        $passport->update(['status' => ! $passport->status]);

        if ($request->wantsJson()) {
            return new JsonResponse(['ok' => true, 'status' => $passport->status]);
        }

        session()->flash('success', trans('agent::app.briefing.passport-toggled'));

        return redirect()->route('admin.agent.briefing.index');
    }

    public function updatePlaybook(Request $request, PlaybookMemory $playbook): RedirectResponse|JsonResponse
    {
        $this->authorizeUser();

        $validated = $request->validate([
            'slot' => ['required', 'string', 'in:business_identity,pricing_guardrails,communication_voice,active_priorities'],
            'content' => ['nullable', 'string', 'max:2000'],
        ]);

        /** @var User $user */
        $user = auth()->guard('user')->user();

        $block = $playbook->upsertBlock(
            (string) $validated['slot'],
            (string) ($validated['content'] ?? ''),
            $user->id
        );

        if ($request->wantsJson()) {
            return new JsonResponse([
                'ok' => true,
                'slot' => $block->slot,
                'version' => $block->version,
            ]);
        }

        session()->flash('success', trans('agent::app.briefing.playbook-saved'));

        return redirect()->route('admin.agent.briefing.index');
    }

    public function captureIntake(Request $request, ActionExecutor $executor): RedirectResponse|JsonResponse
    {
        $this->authorizeUser();

        $validated = $request->validate([
            'raw_text' => ['required', 'string', 'min:3', 'max:4000'],
        ]);

        $outcome = $executor->execute('intake_capture', [
            'raw_text' => trim((string) $validated['raw_text']),
            'source_channel' => 'briefing_desk',
        ]);

        if ($request->wantsJson()) {
            return new JsonResponse($outcome->body, $outcome->httpStatus);
        }

        if ($outcome->body['ok'] ?? false) {
            session()->flash('success', trans('agent::app.briefing.intake-saved', [
                'id' => (string) ($outcome->body['call_id'] ?? '—'),
            ]));
        } else {
            session()->flash('error', (string) ($outcome->body['error']['message'] ?? trans('admin::app.errors.unauthorized')));
        }

        return redirect()->route('admin.agent.briefing.index');
    }

    /**
     * @return array<int, array{slug: string, name: string, scopes: string, trust_mode: string, daily_call_limit: int}>
     */
    private static function presets(): array
    {
        return [
            ['slug' => 'grokbot', 'name' => 'Grokbot', 'scopes' => '*', 'trust_mode' => 'standard', 'daily_call_limit' => 1500],
            ['slug' => 'antigravity', 'name' => 'Antigravity', 'scopes' => '*', 'trust_mode' => 'earned_auto', 'daily_call_limit' => 2000],
            ['slug' => 'claude-code', 'name' => 'Claude Code', 'scopes' => '*', 'trust_mode' => 'standard', 'daily_call_limit' => 1500],
            ['slug' => 'hermes-agent', 'name' => 'Hermes Agent', 'scopes' => 'intake_capture,leads_*,contacts_*,activities_*,briefing_today,playbook_read', 'trust_mode' => 'always_confirm', 'daily_call_limit' => 1000],
        ];
    }

    private function authorizeUser(): void
    {
        abort_unless(
            bouncer()->hasPermission(self::PERMISSION),
            401,
            trans('admin::app.errors.unauthorized')
        );
    }
}
