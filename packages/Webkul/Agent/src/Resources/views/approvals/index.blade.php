<x-admin::layouts>
    <x-slot:title>
        @lang('agent::app.approvals.title')
    </x-slot>

    @include('agent::partials.styles')

    @php
        $pending = $calls->getCollection()->where('status', \Webkul\Agent\Models\AgentActionCall::PENDING_APPROVAL);
        $history = $calls->getCollection()->reject(fn ($call) => $call->status === \Webkul\Agent\Models\AgentActionCall::PENDING_APPROVAL);
        $ttl = (int) config('agent.approval_ttl_hours');
        $describe = fn ($value) => is_scalar($value) || $value === null ? (string) ($value ?? '—') : json_encode($value);
        $reviewableActions = \Webkul\Agent\Support\BriefingData::UNREVIEWED_ACTIONS;
    @endphp

    <x-admin::breadcrumbs name="agent.approvals" />

    {{-- v-pre: agent-written text (reasons, titles) must never be compiled as a Vue template. --}}
    <div class="lum-agent" v-pre>
        @if ($errors->any())
            <p class="notice" role="alert">{{ $errors->first() }}</p>
        @endif

        <div class="head">
            <div>
                <h1>{{ trans_choice('agent::app.approvals.waiting', $pending->count()) }}</h1>
                <p>@lang('agent::app.approvals.subtitle')</p>
            </div>
        </div>

        @if ($pending->isNotEmpty())
            <section aria-labelledby="agent-to-sign">
                <h2 id="agent-to-sign">
                    @lang('agent::app.approvals.to-sign')
                    <small>@lang('agent::app.approvals.closes-info', ['hours' => $ttl])</small>
                </h2>

                <div class="slips">
                    @foreach ($pending as $call)
                        @php
                            $rows = $call->subject ?? [];
                            $deletes = in_array($call->action, ['leads_delete', 'quotes_delete', 'contacts_merge_persons'], true);
                            $movesTo = $rows[0]['moves_to'] ?? null;
                            $closes = $call->created_at?->copy()->addHours($ttl)->diffForHumans(now(), \Carbon\CarbonInterface::DIFF_ABSOLUTE, false, 2);
                        @endphp

                        <article
                            class="slip {{ $deletes ? 'destroy' : '' }}"
                            aria-labelledby="agent-call-{{ $call->id }}"
                        >
                            <p class="meta">
                                <span class="mono">#{{ $call->id }} · {{ $call->action }}</span>
                                @if ($call->passport)
                                    · <span class="mono">{{ $call->passport->slug }}</span>
                                @endif
                                · <time datetime="{{ $call->created_at?->toIso8601String() }}">{{ core()->formatDate($call->created_at, 'd M H:i') }}</time>
                                · @lang('agent::app.approvals.closes', ['time' => $closes])
                            </p>

                            <div>
                                <h3 id="agent-call-{{ $call->id }}">
                                    @if ($call->action === 'leads_delete')
                                        {{ trans_choice('agent::app.approvals.title-delete', count($rows)) }}
                                    @elseif ($movesTo)
                                        {{ trans_choice('agent::app.approvals.title-move', count($rows), ['stage' => $movesTo]) }}
                                    @else
                                        {{ $call->action }}
                                    @endif
                                </h3>

                                <p class="who">
                                    <b>@lang($call->token_id ? 'agent::app.approvals.source-token' : 'agent::app.approvals.source-assistant')</b>
                                    @lang('agent::app.approvals.acting-as', ['user' => $call->getRelationValue('user')?->getAttribute('name') ?? $call->actor_email_snapshot ?? '#'.$call->user_id])
                                </p>
                            </div>

                            @if (count($rows) && isset($rows[0]['title']))
                                <table class="diff">
                                    <thead>
                                        <tr>
                                            <th scope="col">@lang('agent::app.approvals.col-lead')</th>
                                            <th scope="col" class="hide-sm">@lang('agent::app.approvals.col-person')</th>
                                            <th scope="col">@lang('agent::app.approvals.col-stage')</th>
                                            <th scope="col">@lang('agent::app.approvals.col-value')</th>
                                        </tr>
                                    </thead>

                                    <tbody>
                                        @foreach ($rows as $row)
                                            <tr>
                                                <td>
                                                    <span @class(['gone' => $deletes])>#{{ $row['id'] ?? '?' }} {{ $row['title'] }}</span>
                                                </td>
                                                <td class="hide-sm">{{ $row['person'] ?? '—' }}</td>
                                                <td>
                                                    @if (isset($row['moves_to']))
                                                        <span class="was">{{ $row['stage'] ?? '—' }}</span>
                                                        <span class="to">{{ $row['moves_to'] }}</span>
                                                    @else
                                                        {{ $row['stage'] ?? '—' }}
                                                    @endif
                                                </td>
                                                <td class="num">{{ isset($row['lead_value']) ? core()->formatBasePrice($row['lead_value']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @elseif (count($rows) && (isset($rows[0]['name']) || isset($rows[0]['subject']) || isset($rows[0]['slot'])))
                                <div class="quest-list">
                                    @foreach ($rows as $row)
                                        <div class="quest-row">
                                            <div>
                                                @if (isset($row['id']))
                                                    <span class="mono">#{{ $row['id'] }}</span>
                                                @endif
                                                <strong @class(['gone' => $deletes && (($row['role'] ?? null) !== 'primary')])>
                                                    {{ $row['name'] ?? $row['subject'] ?? $row['slot'] ?? '—' }}
                                                </strong>
                                                @if (! empty($row['email']))
                                                    · <span class="mono">{{ $row['email'] }}</span>
                                                @endif
                                                @if (! empty($row['role']))
                                                    · <span class="mono">{{ $row['role'] }}</span>
                                                @endif
                                            </div>
                                            @if (isset($row['sub_total']))
                                                <span class="num">{{ core()->formatBasePrice($row['sub_total']) }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p class="params">
                                    @foreach ($call->params as $field => $value)
                                        {{ $field }}: {{ $describe($value) }}@if (! $loop->last) · @endif
                                    @endforeach
                                </p>
                            @endif

                            @if ($call->reason)
                                <p class="reason">
                                    <span>@lang('agent::app.approvals.reason')</span>
                                    “{{ $call->reason }}”
                                </p>
                            @endif

                            <p class="checks">
                                <span>@lang('agent::app.approvals.runs-if-unchanged')</span>

                                @if ($deletes)
                                    <span class="warn">@lang('agent::app.approvals.cannot-undo')</span>
                                @endif
                            </p>

                            <details class="edit-drawer">
                                <summary>@lang('agent::app.approvals.edit-before-sign')</summary>

                                <div class="edit-grid">
                                    <div class="field">
                                        <label for="edited-params-{{ $call->id }}">@lang('agent::app.approvals.edited-params-label')</label>
                                        <textarea
                                            id="edited-params-{{ $call->id }}"
                                            form="approve-form-{{ $call->id }}"
                                            name="edited_params_json"
                                            class="mono"
                                        >{{ json_encode($call->params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</textarea>
                                    </div>

                                    <div class="field">
                                        <label for="feedback-note-{{ $call->id }}">@lang('agent::app.approvals.feedback-label')</label>
                                        <input
                                            id="feedback-note-{{ $call->id }}"
                                            type="text"
                                            name="feedback_note"
                                            maxlength="500"
                                            form="approve-form-{{ $call->id }}"
                                            data-sync-reject="reject-feedback-{{ $call->id }}"
                                            placeholder="@lang('agent::app.approvals.feedback-placeholder')"
                                        />
                                    </div>

                                    <label class="check-inline">
                                        <input
                                            type="checkbox"
                                            name="distill_rule"
                                            value="1"
                                            form="approve-form-{{ $call->id }}"
                                            data-sync-reject-check="reject-distill-{{ $call->id }}"
                                        />
                                        @lang('agent::app.approvals.distill-rule')
                                    </label>
                                </div>
                            </details>

                            <div class="actions">
                                <form id="approve-form-{{ $call->id }}" method="POST" action="{{ route('admin.agent.approvals.approve', $call->id) }}">
                                    @csrf

                                    @if ($deletes)
                                        <button
                                            type="submit"
                                            class="btn danger"
                                            aria-pressed="false"
                                            data-agent-confirm="@lang('agent::app.approvals.confirm-delete')"
                                        >
                                            @lang('agent::app.approvals.delete')
                                        </button>
                                    @else
                                        <button type="submit" class="btn sign">@lang('agent::app.approvals.approve')</button>
                                    @endif
                                </form>

                                <form method="POST" action="{{ route('admin.agent.approvals.reject', $call->id) }}">
                                    @csrf
                                    <input type="hidden" id="reject-feedback-{{ $call->id }}" name="feedback_note" value="" />
                                    <input type="hidden" id="reject-distill-{{ $call->id }}" name="distill_rule" value="0" />

                                    <button type="submit" class="btn">@lang('agent::app.approvals.reject')</button>
                                </form>

                                @if ($deletes)
                                    <span class="note" aria-live="polite"></span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        <section aria-labelledby="agent-ledger">
            <h2 id="agent-ledger">
                @lang('agent::app.approvals.ledger')
                <small>@lang('agent::app.approvals.ledger-info')</small>
            </h2>

            <div class="ledger-wrap">
                <table class="ledger">
                    <thead>
                        <tr>
                            <th scope="col">@lang('agent::app.approvals.col-time')</th>
                            <th scope="col">@lang('agent::app.approvals.col-call')</th>
                            <th scope="col">@lang('agent::app.approvals.col-action')</th>
                            <th scope="col" class="hide-sm">@lang('agent::app.approvals.col-agent')</th>
                            <th scope="col">@lang('agent::app.approvals.col-params')</th>
                            <th scope="col">@lang('agent::app.approvals.col-status')</th>
                            <th scope="col" class="hide-sm">@lang('agent::app.approvals.col-decision')</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($history as $call)
                            @php
                                $needsReview = $call->status === \Webkul\Agent\Models\AgentActionCall::COMPLETED
                                    && $call->reviewed_at === null
                                    && in_array($call->action, $reviewableActions, true);
                            @endphp
                            <tr>
                                <td class="num nowrap"><time datetime="{{ $call->created_at?->toIso8601String() }}">{{ core()->formatDate($call->created_at, 'd M H:i') }}</time></td>
                                <td class="mono">
                                    #{{ $call->id }}
                                    @if ($call->receipt_code)
                                        <div>
                                            <a href="{{ url('/receipts/'.$call->receipt_code) }}" class="receipt-pill mono">
                                                @lang('agent::app.approvals.receipt', ['code' => $call->receipt_code])
                                            </a>
                                        </div>
                                    @endif
                                </td>
                                <td class="mono">{{ $call->action }}</td>
                                <td class="hide-sm">{{ $call->getRelationValue('user')?->getAttribute('name') ?? $call->actor_email_snapshot ?? '#'.$call->user_id }}</td>
                                <td>
                                    <span class="params">
                                        @foreach (($call->edited_params ?? $call->params) as $field => $value)
                                            {{ $field }}: {{ $describe($value) }}@if (! $loop->last) · @endif
                                        @endforeach
                                    </span>

                                    @if ($call->reason)
                                        <p class="reason tight">
                                            <span>@lang('agent::app.approvals.reason')</span>
                                            “{{ $call->reason }}”
                                        </p>
                                    @endif

                                    @if ($call->human_feedback)
                                        <p class="reason tight">
                                            <span>@lang('agent::app.approvals.human-note')</span>
                                            {{ $call->human_feedback }}
                                        </p>
                                    @endif
                                </td>
                                <td>
                                    <span class="state {{ $call->status }}">@lang('agent::app.approvals.status.'.$call->status)</span>
                                    @if ($needsReview)
                                        <div class="tag-gap">
                                            <span class="pencil-tag">@lang('agent::app.approvals.unreviewed')</span>
                                        </div>
                                    @endif
                                </td>
                                <td class="num hide-sm nowrap">
                                    @if ($needsReview)
                                        <form method="POST" action="{{ route('admin.agent.approvals.review', $call->id) }}">
                                            @csrf
                                            <button type="submit" class="btn sm">@lang('agent::app.approvals.mark-reviewed')</button>
                                        </form>
                                    @elseif ($call->reviewed_at)
                                        {{ core()->formatDate($call->reviewed_at, 'd M H:i') }}
                                    @else
                                        {{ $call->decided_at ? core()->formatDate($call->decided_at, 'd M H:i') : '—' }}
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="empty">
                                    @lang($pending->isEmpty() ? 'agent::app.approvals.empty' : 'agent::app.approvals.ledger-empty')
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if ($calls->hasPages())
            <div class="ledger-wrap pager">
                {{ $calls->withQueryString()->links() }}
            </div>
        @endif
    </div>

    @pushOnce('scripts')
        <script type="module">
            {{-- Deleting takes two presses: the first arms the button, Esc or leaving it disarms. --}}
            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-agent-confirm]');

                if (! button || button.getAttribute('aria-pressed') === 'true') {
                    return;
                }

                event.preventDefault();

                const label = button.textContent;
                const note = button.closest('.actions').querySelector('.note');

                const disarm = () => {
                    button.setAttribute('aria-pressed', 'false');
                    button.textContent = label;
                    note.textContent = '';
                };

                button.setAttribute('aria-pressed', 'true');
                button.textContent = button.dataset.agentConfirm;
                note.textContent = @json(trans('agent::app.approvals.confirm-hint'));

                button.addEventListener('keydown', (key) => key.key === 'Escape' && disarm(), { once: true });
                button.addEventListener('blur', disarm, { once: true });
            });

            document.addEventListener('input', (event) => {
                const rejectId = event.target.dataset?.syncReject;

                if (rejectId) {
                    const hidden = document.getElementById(rejectId);

                    if (hidden) {
                        hidden.value = event.target.value;
                    }
                }
            });

            document.addEventListener('change', (event) => {
                const rejectCheckId = event.target.dataset?.syncRejectCheck;

                if (rejectCheckId) {
                    const hidden = document.getElementById(rejectCheckId);

                    if (hidden) {
                        hidden.value = event.target.checked ? '1' : '0';
                    }
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
