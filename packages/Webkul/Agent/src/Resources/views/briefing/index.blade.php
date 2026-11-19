<x-admin::layouts>
    <x-slot:title>
        @lang('agent::app.briefing.title')
    </x-slot>

    @include('agent::partials.styles')

    @php
        $issued = session('issued_passport');
        $pendingItems = $briefing['pending_approvals']['items'] ?? [];
        $unreviewedItems = $briefing['unreviewed_creates']['items'] ?? [];
        $rottingItems = $briefing['leads']['rotting_items'] ?? [];
        $dueItems = $briefing['due_activities']['items'] ?? [];
        $ledger = $briefing['ledger'] ?? [];
        $playbookBlocks = $briefing['playbook']['blocks'] ?? [];
        $momentum = $briefing['momentum'] ?? [];
    @endphp

    <x-admin::breadcrumbs name="agent.briefing" />

    <div class="lum-agent" v-pre>
        @if ($errors->any())
            <p class="notice" role="alert">{{ $errors->first() }}</p>
        @endif

        <div class="head">
            <div>
                <h1>@lang('agent::app.briefing.title')</h1>
                <p>@lang('agent::app.briefing.subtitle')</p>
            </div>

            <a href="{{ route('admin.agent.approvals.index') }}" class="btn sign">
                @lang('agent::app.briefing.open-approvals', ['count' => $briefing['pending_approvals']['count'] ?? 0])
            </a>
        </div>

        <nav class="skills-bar" aria-label="@lang('agent::app.briefing.skills-label')">
            <span class="kicker">@lang('agent::app.briefing.skills-label')</span>
            <a class="btn sm mono" href="{{ route('admin.agent.assistant.index', ['skill' => 'intake']) }}">/intake</a>
            <a class="btn sm mono" href="{{ route('admin.agent.assistant.index', ['skill' => 'quote']) }}">/quote</a>
            <a class="btn sm mono" href="{{ route('admin.agent.assistant.index', ['skill' => 'followup']) }}">/followup</a>
            <a class="btn sm mono" href="{{ route('admin.agent.assistant.index', ['skill' => 'briefing']) }}">/ask</a>
        </nav>

        @if (is_array($issued))
            <section class="token-banner" aria-live="polite">
                <strong>@lang('agent::app.briefing.token-once-title', ['name' => $issued['name'] ?? $issued['slug']])</strong>
                <p>@lang('agent::app.briefing.token-once-note')</p>
                <pre>{{ $issued['token'] ?? '' }}</pre>
                @if (! empty($issued['webhook_secret']))
                    <p class="mono">@lang('agent::app.briefing.webhook-secret'): {{ $issued['webhook_secret'] }}</p>
                @endif
                <pre>{{ $issued['cli'] ?? '' }}</pre>
            </section>
        @endif

        <div class="bento-7-5">
            {{-- Left 7-column stack: One-Line Intake, Today's Quests & Unreviewed Pencil Drafts --}}
            <div class="stack">
                <section class="sheet-card" aria-labelledby="briefing-intake">
                    <h2 id="briefing-intake">@lang('agent::app.briefing.intake-title')</h2>

                    <form method="POST" action="{{ route('admin.agent.briefing.intake') }}" class="intake-row">
                        @csrf
                        <input
                            type="text"
                            name="raw_text"
                            required
                            minlength="3"
                            maxlength="4000"
                            aria-label="@lang('agent::app.briefing.intake-title')"
                            placeholder="@lang('agent::app.briefing.intake-placeholder')"
                        />
                        <button type="submit" class="btn sign">@lang('agent::app.briefing.intake-submit')</button>
                    </form>
                </section>

                <section class="sheet-card" aria-labelledby="briefing-quests">
                    <h2 id="briefing-quests">
                        @lang('agent::app.briefing.quests-title')
                        <small class="num">{{ $briefing['date'] ?? '' }}</small>
                    </h2>

                    <div class="kpi-strip">
                        <div class="kpi">
                            <span class="val pencil num">{{ $briefing['pending_approvals']['count'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.kpi-pending')</span>
                        </div>
                        <div class="kpi">
                            <span class="val pencil num">{{ $briefing['unreviewed_creates']['count'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.kpi-unreviewed')</span>
                        </div>
                        <div class="kpi">
                            <span class="val num">{{ core()->formatBasePrice($briefing['leads']['open_value'] ?? 0) }}</span>
                            <span class="lbl">@lang('agent::app.briefing.kpi-open-pipeline', ['count' => $briefing['leads']['open_count'] ?? 0])</span>
                        </div>
                        <div class="kpi">
                            <span class="val num">{{ $briefing['leads']['rotting_count'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.kpi-rotting')</span>
                        </div>
                        <div class="kpi">
                            <span class="val num">{{ $briefing['due_activities']['count'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.kpi-due-today')</span>
                        </div>
                    </div>

                    <div class="quest-list">
                        @foreach ($pendingItems as $slip)
                            <div class="quest-row pencil">
                                <div>
                                    <span class="mono">#{{ $slip['id'] }} · {{ $slip['action'] }}</span>
                                    @if (! empty($slip['reason']))
                                        — <span>“{{ $slip['reason'] }}”</span>
                                    @endif
                                </div>
                                <a href="{{ route('admin.agent.approvals.index') }}" class="btn sm sign">
                                    @lang('agent::app.approvals.approve')
                                </a>
                            </div>
                        @endforeach

                        @foreach ($rottingItems as $lead)
                            <div class="quest-row">
                                <div>
                                    <span class="mono">#{{ $lead['id'] }}</span>
                                    <strong>{{ $lead['title'] }}</strong>
                                    @if (! empty($lead['stage']))
                                        · <span>{{ $lead['stage'] }}</span>
                                    @endif
                                </div>
                                <span class="num">{{ $lead['lead_value'] !== null ? core()->formatBasePrice($lead['lead_value']) : '—' }}</span>
                            </div>
                        @endforeach

                        @foreach ($dueItems as $activity)
                            <div class="quest-row">
                                <div>
                                    <span class="mono">{{ $activity['type'] }}</span> · <strong>{{ $activity['title'] }}</strong>
                                </div>
                                <span class="num">{{ $activity['schedule_from'] ?? '—' }}</span>
                            </div>
                        @endforeach

                        @if (empty($pendingItems) && empty($rottingItems) && empty($dueItems))
                            <p class="empty">@lang('agent::app.briefing.quests-empty')</p>
                        @endif
                    </div>
                </section>

                <section class="sheet-card pencil" aria-labelledby="briefing-unreviewed">
                    <h2 id="briefing-unreviewed">
                        @lang('agent::app.briefing.unreviewed-title')
                        <small>@lang('agent::app.briefing.unreviewed-subtitle')</small>
                    </h2>

                    @if (! empty($unreviewedItems))
                        <div class="quest-list">
                            @foreach ($unreviewedItems as $item)
                                <div class="quest-row">
                                    <div>
                                        <span class="mono">#{{ $item['id'] }} · {{ $item['action'] }}</span>
                                        @if (! empty($item['receipt_code']))
                                            <a href="{{ url('/receipts/'.$item['receipt_code']) }}" class="receipt-pill mono">{{ $item['receipt_code'] }}</a>
                                        @endif
                                    </div>

                                    <form method="POST" action="{{ route('admin.agent.briefing.review', $item['id']) }}">
                                        @csrf
                                        <button type="submit" class="btn sm">@lang('agent::app.approvals.mark-reviewed')</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="empty">@lang('agent::app.briefing.unreviewed-empty')</p>
                    @endif
                </section>
            </div>

            {{-- Right 5-column stack: Momentum & Ledger Vault, Passports, and Playbook Memory --}}
            <div class="stack">
                <section class="sheet-card" aria-labelledby="briefing-vault">
                    <h2 id="briefing-vault">@lang('agent::app.briefing.vault-title')</h2>

                    <div class="kpi-strip">
                        <div class="kpi">
                            <span class="val num">{{ $momentum['completed_7d'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.momentum-completed')</span>
                        </div>
                        <div class="kpi">
                            <span class="val num">{{ $momentum['reviewed_7d'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.momentum-reviewed')</span>
                        </div>
                        <div class="kpi">
                            <span class="val num">{{ $momentum['approved_streak'] ?? 0 }}</span>
                            <span class="lbl">@lang('agent::app.briefing.momentum-streak')</span>
                        </div>
                    </div>

                    <div class="quest-row">
                        <div>
                            <strong>@lang('agent::app.briefing.ledger-height')</strong>
                            <div class="mono">{{ $ledger['head_hash'] ? substr((string) $ledger['head_hash'], 0, 20).'…' : '—' }}</div>
                        </div>
                        <span class="mono num">#{{ $ledger['height'] ?? 0 }}</span>
                    </div>
                </section>

                <section class="sheet-card" aria-labelledby="briefing-passports">
                    <h2 id="briefing-passports">
                        @lang('agent::app.briefing.passports-title')
                        <small>@lang('agent::app.briefing.passports-subtitle')</small>
                    </h2>

                    <div class="preset-row">
                        @foreach ($presets as $preset)
                            <button
                                type="button"
                                class="btn sm"
                                data-passport-preset='@json($preset)'
                            >
                                + {{ $preset['name'] }}
                            </button>
                        @endforeach
                    </div>

                    <form id="passport-issue-form" method="POST" action="{{ route('admin.agent.briefing.passports.store') }}" class="edit-grid">
                        @csrf
                        <div class="field">
                            <label for="passport-slug">@lang('agent::app.briefing.passport-slug')</label>
                            <input id="passport-slug" type="text" name="slug" required placeholder="grokbot" />
                        </div>

                        <div class="field">
                            <label for="passport-name">@lang('agent::app.briefing.passport-name')</label>
                            <input id="passport-name" type="text" name="name" placeholder="Grokbot" />
                        </div>

                        <div class="field">
                            <label for="passport-scopes">@lang('agent::app.briefing.passport-scopes')</label>
                            <input id="passport-scopes" type="text" name="scopes" value="*" class="mono" />
                        </div>

                        <div class="kpi-strip">
                            <div class="field">
                                <label for="passport-limit">@lang('agent::app.briefing.passport-limit')</label>
                                <input id="passport-limit" type="number" name="daily_call_limit" value="1000" min="1" max="100000" />
                            </div>

                            <div class="field">
                                <label for="passport-trust">@lang('agent::app.briefing.passport-trust')</label>
                                <select id="passport-trust" name="trust_mode">
                                    <option value="standard">standard</option>
                                    <option value="always_confirm">always_confirm</option>
                                    <option value="earned_auto">earned_auto</option>
                                </select>
                            </div>
                        </div>

                        <div class="field">
                            <label for="passport-ceiling">@lang('agent::app.briefing.passport-ceiling')</label>
                            <input id="passport-ceiling" type="number" name="auto_approve_value_ceiling" min="0" max="9999999999.99" step="0.01" placeholder="5000.00" />
                        </div>

                        <div class="field">
                            <label for="passport-webhook">@lang('agent::app.briefing.passport-webhook')</label>
                            <input id="passport-webhook" type="url" name="webhook_url" placeholder="https://example.com/agent/webhook" />
                        </div>

                        <button type="submit" class="btn sign">@lang('agent::app.briefing.passport-issue-btn')</button>
                    </form>

                    @if ($passports->isNotEmpty())
                        <div class="quest-list">
                            @foreach ($passports as $passport)
                                <div class="quest-row">
                                    <div>
                                        <strong>{{ $passport->name }}</strong>
                                        <span class="mono">({{ $passport->slug }} · {{ $passport->trust_mode }} · {{ $passport->daily_call_limit }}/d)</span>
                                    </div>

                                    <form method="POST" action="{{ route('admin.agent.briefing.passports.toggle', $passport->id) }}">
                                        @csrf
                                        <button type="submit" class="btn sm">
                                            {{ $passport->status ? trans('agent::app.briefing.passport-pause') : trans('agent::app.briefing.passport-resume') }}
                                        </button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="sheet-card" aria-labelledby="briefing-playbook">
                    <h2 id="briefing-playbook">
                        @lang('agent::app.briefing.playbook-title')
                        <small class="mono">v{{ $briefing['playbook']['version'] ?? 0 }}</small>
                    </h2>

                    @foreach ($playbookBlocks as $slot => $block)
                        <details class="edit-drawer" @if ($loop->first) open @endif>
                            <summary class="mono">{{ $slot }} (v{{ $block['version'] ?? 0 }})</summary>

                            <form method="POST" action="{{ route('admin.agent.briefing.playbook.update') }}" class="edit-grid">
                                @csrf
                                <input type="hidden" name="slot" value="{{ $slot }}" />
                                <div class="field">
                                    <textarea name="content" aria-label="{{ $slot }}" maxlength="2000" rows="3">{{ $block['content'] ?? '' }}</textarea>
                                </div>
                                <button type="submit" class="btn sm">@lang('agent::app.briefing.playbook-save-btn')</button>
                            </form>
                        </details>
                    @endforeach
                </section>
            </div>
        </div>
    </div>

    @pushOnce('scripts')
        <script type="module">
            document.addEventListener('click', (event) => {
                const btn = event.target.closest('[data-passport-preset]');

                if (! btn) {
                    return;
                }

                const preset = JSON.parse(btn.dataset.passportPreset);
                document.getElementById('passport-slug').value = preset.slug;
                document.getElementById('passport-name').value = preset.name;
                document.getElementById('passport-scopes').value = preset.scopes;
                document.getElementById('passport-limit').value = preset.daily_call_limit;
                document.getElementById('passport-trust').value = preset.trust_mode;
                document.getElementById('passport-ceiling').value = preset.auto_approve_value_ceiling ?? '';
                document.getElementById('passport-slug').focus();
            });
        </script>
    @endPushOnce
</x-admin::layouts>
