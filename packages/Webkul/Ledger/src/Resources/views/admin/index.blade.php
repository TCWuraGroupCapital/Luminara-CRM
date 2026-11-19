<x-admin::layouts>
    <x-slot:title>
        @lang('ledger::app.admin.title')
    </x-slot>

    @pushOnce('styles')
        <style>
            .lum-ledger {
                --paper: oklch(0.985 0.004 190);
                --sheet: oklch(1 0 0);
                --well: oklch(0.965 0.006 190);
                --rule: oklch(0.25 0.03 190 / 0.12);
                --ink: oklch(0.22 0.025 190);
                --ink-2: oklch(0.45 0.02 190);
                --ink-3: oklch(0.52 0.016 190);
                --brand: oklch(0.45 0.085 186);
                --ok: oklch(0.48 0.11 155);
                --ok-wash: oklch(0.96 0.03 155);
                --stamp: oklch(0.52 0.19 30);
                --stamp-wash: oklch(0.96 0.03 30);
                display: flex;
                flex-direction: column;
                gap: 16px;
                color: var(--ink);
            }
            .dark .lum-ledger {
                --paper: oklch(0.165 0.012 190);
                --sheet: oklch(0.195 0.013 190);
                --well: oklch(0.225 0.014 190);
                --rule: oklch(1 0 0 / 0.10);
                --ink: oklch(0.94 0.008 190);
                --ink-2: oklch(0.76 0.012 190);
                --ink-3: oklch(0.66 0.012 190);
                --brand: oklch(0.78 0.10 186);
                --ok: oklch(0.78 0.12 155);
                --ok-wash: oklch(0.24 0.04 155);
                --stamp: oklch(0.74 0.14 32);
                --stamp-wash: oklch(0.25 0.05 30);
            }
            .lum-ledger-card {
                background: var(--sheet);
                border: 1px solid var(--rule);
                border-radius: 10px;
                overflow: hidden;
            }
            .lum-ledger-head {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 16px 20px;
            }
            .lum-ledger-title-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                gap: 10px;
            }
            .lum-ledger-title {
                font-size: 20px;
                font-weight: 600;
                letter-spacing: -0.02em;
                color: var(--ink);
                margin: 0;
            }
            .lum-ledger-sub {
                margin: 4px 0 0;
                font-size: 14px;
                color: var(--ink-2);
            }
            .lum-ledger-badge-ok {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 4px;
                border: 1px solid var(--ok);
                background: var(--ok-wash);
                color: var(--ok);
                font-size: 12px;
                font-weight: 600;
            }
            .lum-ledger-badge-bad {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                padding: 2px 8px;
                border-radius: 4px;
                border: 1.5px dashed var(--stamp);
                background: var(--stamp-wash);
                color: var(--stamp);
                font-size: 12px;
                font-weight: 600;
            }
            .lum-ledger-alert {
                padding: 14px 18px;
                border-radius: 10px;
                border: 1.5px dashed var(--stamp);
                background: var(--stamp-wash);
                color: var(--stamp);
                font-size: 13px;
            }
            .lum-ledger-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
                gap: 12px;
            }
            .lum-ledger-stat {
                background: var(--sheet);
                border: 1px solid var(--rule);
                border-radius: 10px;
                padding: 14px 16px;
            }
            .lum-ledger-kicker {
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--ink-3);
                margin: 0;
            }
            .lum-ledger-metric {
                margin: 6px 0 0;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 18px;
                font-weight: 600;
                font-variant-numeric: tabular-nums;
                color: var(--ink);
            }
            .lum-ledger-pubkey {
                margin: 6px 0 0;
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-size: 12px;
                color: var(--ink-2);
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .lum-ledger-section-head {
                padding: 12px 16px;
                border-bottom: 1px solid var(--rule);
                font-size: 14px;
                font-weight: 600;
                color: var(--ink);
            }
            .lum-ledger-table-wrap {
                overflow-x: auto;
            }
            .lum-ledger-table {
                width: 100%;
                border-collapse: collapse;
                text-align: left;
                font-size: 13px;
            }
            .lum-ledger-table th {
                padding: 10px 16px;
                background: var(--well);
                border-bottom: 1px solid var(--rule);
                font-size: 11px;
                font-weight: 600;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--ink-3);
                white-space: nowrap;
            }
            .lum-ledger-table td {
                padding: 10px 16px;
                border-bottom: 1px solid var(--rule);
                color: var(--ink);
                white-space: nowrap;
            }
            .lum-ledger-table tr:last-child td {
                border-bottom: none;
            }
            .lum-ledger-mono {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-variant-numeric: tabular-nums;
            }
            .lum-ledger-trunc {
                max-width: 200px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .lum-ledger-link {
                color: var(--brand);
                text-decoration: underline;
            }
            .lum-ledger-empty {
                padding: 28px 16px;
                text-align: center;
                color: var(--ink-3);
                font-size: 14px;
            }
        </style>
    @endPushOnce

    <x-admin::breadcrumbs name="ledger.index" />

    <div class="lum-ledger" v-pre>
        <div class="lum-ledger-card lum-ledger-head">
            <div>
                <div class="lum-ledger-title-row">
                    <h1 class="lum-ledger-title">
                        @lang('ledger::app.admin.title')
                    </h1>

                    @if ($health['ok'])
                        <span class="lum-ledger-badge-ok">
                            @lang('ledger::app.admin.status-verified') ✓
                        </span>
                    @else
                        <span class="lum-ledger-badge-bad">
                            @lang('ledger::app.admin.status-tampered')
                        </span>
                    @endif
                </div>

                <p class="lum-ledger-sub">
                    @lang('ledger::app.admin.subtitle')
                </p>
            </div>

            <form method="POST" action="{{ route('admin.ledger.seal') }}">
                @csrf
                <button type="submit" class="primary-button">
                    @lang('ledger::app.admin.seal-epoch')
                </button>
            </form>
        </div>

        @if (! empty($health['errors']))
            <div class="lum-ledger-alert">
                <ul class="lum-ledger-mono" style="margin:0;padding-left:18px;">
                    @foreach ($health['errors'] as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="lum-ledger-stats">
            <div class="lum-ledger-stat">
                <p class="lum-ledger-kicker">@lang('ledger::app.admin.head-height')</p>
                <p class="lum-ledger-metric">#{{ $health['head_height'] }}</p>
            </div>

            <div class="lum-ledger-stat">
                <p class="lum-ledger-kicker">@lang('ledger::app.admin.checked-blocks')</p>
                <p class="lum-ledger-metric">{{ $health['checked_blocks'] }}</p>
            </div>

            <div class="lum-ledger-stat">
                <p class="lum-ledger-kicker">@lang('ledger::app.admin.checked-epochs')</p>
                <p class="lum-ledger-metric">{{ $health['checked_epochs'] }}</p>
            </div>

            <div class="lum-ledger-stat">
                <p class="lum-ledger-kicker">@lang('ledger::app.admin.server-pubkey')</p>
                <p class="lum-ledger-pubkey" title="{{ $serverPubkey }}">{{ $serverPubkey }}</p>
            </div>
        </div>

        <div class="lum-ledger-card">
            <div class="lum-ledger-section-head">
                @lang('ledger::app.admin.epochs-heading')
            </div>

            <div class="lum-ledger-table-wrap">
                <table class="lum-ledger-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger::app.admin.col-epoch')</th>
                            <th>@lang('ledger::app.admin.col-range')</th>
                            <th>@lang('ledger::app.admin.col-count')</th>
                            <th>@lang('ledger::app.admin.col-merkle-root')</th>
                            <th>@lang('ledger::app.admin.col-anchor')</th>
                            <th>@lang('ledger::app.admin.col-sealed-at')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($epochs as $epoch)
                            <tr>
                                <td class="lum-ledger-mono">#{{ $epoch->id }}</td>
                                <td class="lum-ledger-mono">{{ $epoch->start_height }}..{{ $epoch->end_height }}</td>
                                <td class="lum-ledger-mono">{{ $epoch->block_count }}</td>
                                <td class="lum-ledger-mono lum-ledger-trunc" title="{{ $epoch->merkle_root }}">{{ $epoch->merkle_root }}</td>
                                <td class="lum-ledger-mono lum-ledger-trunc" title="{{ $epoch->anchor_reference ?? $epoch->anchor_driver }}">{{ $epoch->anchor_reference ?? $epoch->anchor_driver }}</td>
                                <td class="lum-ledger-mono">{{ $epoch->sealed_at->utc()->format('Y-m-d\TH:i:s\Z') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="lum-ledger-empty">
                                    @lang('ledger::app.admin.empty-epochs')
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lum-ledger-card">
            <div class="lum-ledger-section-head">
                @lang('ledger::app.admin.blocks-heading')
            </div>

            <div class="lum-ledger-table-wrap">
                <table class="lum-ledger-table">
                    <thead>
                        <tr>
                            <th>@lang('ledger::app.admin.col-height')</th>
                            <th>@lang('ledger::app.admin.col-receipt')</th>
                            <th>@lang('ledger::app.admin.col-event')</th>
                            <th>@lang('ledger::app.admin.col-entity')</th>
                            <th>@lang('ledger::app.admin.col-actor')</th>
                            <th>@lang('ledger::app.admin.col-signer')</th>
                            <th>@lang('ledger::app.admin.col-hash')</th>
                            <th>@lang('ledger::app.admin.col-time')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($blocks as $block)
                            <tr>
                                <td class="lum-ledger-mono">#{{ $block->block_height }}</td>
                                <td class="lum-ledger-mono">
                                    <a href="{{ route('ledger.receipts.show', ['receiptCode' => $block->receipt_code]) }}" target="_blank" rel="noopener" class="lum-ledger-link">
                                        {{ $block->receipt_code }}
                                    </a>
                                </td>
                                <td class="lum-ledger-mono">{{ $block->event_name }}</td>
                                <td class="lum-ledger-mono">{{ $block->entity_type }}:{{ $block->entity_id }}</td>
                                <td>{{ $block->actor_kind }}{{ $block->actor_id ? ' #'.$block->actor_id : '' }}</td>
                                <td class="lum-ledger-mono" style="text-transform:uppercase;">{{ $block->signer_alg }}</td>
                                <td class="lum-ledger-mono lum-ledger-trunc" title="{{ $block->block_hash }}">{{ $block->block_hash }}</td>
                                <td class="lum-ledger-mono">{{ $block->created_at->utc()->format('Y-m-d\TH:i:s\Z') }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="lum-ledger-empty">
                                    @lang('ledger::app.admin.empty-blocks')
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($blocks->hasPages())
            <div class="lum-ledger-card" style="padding:12px 16px;">
                {{ $blocks->withQueryString()->links() }}
            </div>
        @endif
    </div>
</x-admin::layouts>
