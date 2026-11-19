@php
    $isRtl = in_array(app()->getLocale(), ['ar', 'fa'], true);
    $isVerified = (bool) ($verification['verified'] ?? false);
    $epoch = $verification['epoch'] ?? null;
    $merkleProof = $verification['merkle_proof'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@lang('ledger::app.receipt.page-title', ['code' => $block->receipt_code])</title>
    <style>
        :root {
            color-scheme: light dark;
            --paper: light-dark(oklch(0.985 0.004 190), oklch(0.165 0.012 190));
            --sheet: light-dark(oklch(1 0 0), oklch(0.195 0.013 190));
            --well: light-dark(oklch(0.965 0.006 190), oklch(0.225 0.014 190));
            --rule: light-dark(oklch(0.25 0.03 190 / 0.14), oklch(1 0 0 / 0.12));
            --ink: light-dark(oklch(0.22 0.025 190), oklch(0.94 0.008 190));
            --ink-2: light-dark(oklch(0.45 0.02 190), oklch(0.76 0.012 190));
            --ink-3: light-dark(oklch(0.52 0.016 190), oklch(0.66 0.012 190));
            --brand: light-dark(oklch(0.45 0.085 186), oklch(0.78 0.10 186));
            --ok: light-dark(oklch(0.48 0.11 155), oklch(0.78 0.12 155));
            --ok-wash: light-dark(oklch(0.96 0.03 155), oklch(0.24 0.04 155));
            --stamp: light-dark(oklch(0.52 0.19 30), oklch(0.74 0.14 32));
            --stamp-wash: light-dark(oklch(0.96 0.03 30), oklch(0.25 0.05 30));
            --font-sans: "IBM Plex Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            --font-mono: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100dvh;
            background: var(--paper);
            color: var(--ink);
            font-family: var(--font-sans);
            font-size: 16px;
            line-height: 1.5;
            padding: clamp(16px, 4vw, 40px) clamp(12px, 3vw, 24px);
            -webkit-font-smoothing: antialiased;
        }

        .lum-shell {
            max-width: 680px;
            margin: 0 auto;
        }

        .lum-sheet {
            background: var(--sheet);
            border: 1px solid var(--rule);
            border-radius: 10px;
            box-shadow: inset 0 1px 0 0 oklch(1 0 0 / 0.08);
            overflow: hidden;
        }

        .lum-header {
            padding: clamp(20px, 3vw, 28px);
            border-bottom: 1px solid var(--rule);
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }

        .lum-kicker {
            font-family: var(--font-mono);
            font-size: 12px;
            font-weight: 500;
            letter-spacing: 0.11em;
            text-transform: uppercase;
            color: var(--ink-3);
            margin: 0 0 6px;
        }

        .lum-code {
            font-family: var(--font-mono);
            font-size: clamp(18px, 2.8vw, 24px);
            font-weight: 600;
            letter-spacing: -0.02em;
            font-variant-numeric: tabular-nums;
            margin: 0;
            word-break: break-all;
        }

        .lum-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 13px;
            font-weight: 600;
            border: 1px solid currentColor;
            white-space: nowrap;
        }

        .lum-badge--ok {
            color: var(--ok);
            background: var(--ok-wash);
        }

        .lum-badge--tampered {
            color: var(--stamp);
            background: var(--stamp-wash);
            border-style: dashed;
        }

        .lum-summary {
            margin: 0;
            padding: 14px clamp(20px, 3vw, 28px);
            background: var(--well);
            border-bottom: 1px solid var(--rule);
            font-size: 14px;
            color: var(--ink-2);
            max-width: 65ch;
            text-wrap: pretty;
        }

        .lum-grid {
            margin: 0;
            padding: 0;
        }

        .lum-row {
            display: grid;
            grid-template-columns: minmax(140px, 200px) 1fr;
            gap: 8px 16px;
            padding: 12px clamp(20px, 3vw, 28px);
            border-bottom: 1px solid var(--rule);
            font-size: 14px;
        }

        @media (max-width: 520px) {
            .lum-row {
                grid-template-columns: 1fr;
                gap: 2px;
            }
        }

        .lum-dt {
            color: var(--ink-3);
            font-weight: 500;
            margin: 0;
        }

        .lum-dd {
            margin: 0;
            color: var(--ink);
            font-weight: 500;
            font-variant-numeric: tabular-nums;
            word-break: break-word;
        }

        .lum-mono {
            font-family: var(--font-mono);
            font-size: 13px;
            word-break: break-all;
        }

        .lum-details {
            border-bottom: 1px solid var(--rule);
        }

        .lum-details summary {
            cursor: pointer;
            padding: 14px clamp(20px, 3vw, 28px);
            font-size: 14px;
            font-weight: 600;
            color: var(--ink);
            background: var(--sheet);
            list-style: none;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .lum-details summary::-webkit-details-marker {
            display: none;
        }

        .lum-details summary::after {
            content: "+";
            font-family: var(--font-mono);
            font-size: 16px;
            color: var(--ink-3);
        }

        .lum-details[open] summary::after {
            content: "−";
        }

        .lum-details summary:focus-visible,
        .lum-link:focus-visible {
            outline: 2px solid var(--brand);
            outline-offset: -2px;
            border-radius: 4px;
        }

        .lum-proof-box {
            padding: 16px clamp(20px, 3vw, 28px) 20px;
            background: var(--well);
            border-top: 1px solid var(--rule);
            display: grid;
            gap: 12px;
        }

        .lum-field-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-3);
            margin: 0 0 4px;
        }

        .lum-hash-box {
            background: var(--sheet);
            border: 1px solid var(--rule);
            border-radius: 4px;
            padding: 8px 10px;
            font-family: var(--font-mono);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
            word-break: break-all;
            margin: 0;
        }

        .lum-steps {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 6px;
        }

        .lum-steps li {
            background: var(--sheet);
            border: 1px solid var(--rule);
            border-radius: 4px;
            padding: 6px 10px;
            font-family: var(--font-mono);
            font-size: 12px;
            word-break: break-all;
        }

        .lum-footer {
            padding: 14px clamp(20px, 3vw, 28px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 8px;
            font-size: 13px;
        }

        .lum-link {
            color: var(--brand);
            text-decoration: underline;
            text-underline-offset: 3px;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <main class="lum-shell">
        <article class="lum-sheet">
            <header class="lum-header">
                <div>
                    <p class="lum-kicker">@lang('ledger::app.receipt.kicker')</p>
                    <h1 class="lum-code">{{ $block->receipt_code }}</h1>
                </div>

                @if ($isVerified)
                    <span class="lum-badge lum-badge--ok" role="status">
                        @lang('ledger::app.receipt.verified-badge')
                    </span>
                @else
                    <span class="lum-badge lum-badge--tampered" role="alert">
                        @lang('ledger::app.receipt.tampered-badge')
                    </span>
                @endif
            </header>

            <p class="lum-summary">
                @if ($isVerified)
                    @lang('ledger::app.receipt.verified-copy')
                @else
                    @lang('ledger::app.receipt.tampered-copy')
                @endif
            </p>

            <dl class="lum-grid">
                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.receipt-code')</dt>
                    <dd class="lum-dd lum-mono">{{ $block->receipt_code }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.block-height')</dt>
                    <dd class="lum-dd lum-mono">#{{ $block->block_height }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.timestamp')</dt>
                    <dd class="lum-dd lum-mono">{{ $block->created_at->utc()->format('Y-m-d\TH:i:s.u\Z') }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.event-summary')</dt>
                    <dd class="lum-dd">{{ $eventSummary }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.entity-kind')</dt>
                    <dd class="lum-dd lum-mono">{{ $block->entity_type }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.actor-kind')</dt>
                    <dd class="lum-dd">{{ ucfirst($block->actor_kind) }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.signer-alg')</dt>
                    <dd class="lum-dd lum-mono">{{ strtoupper($block->signer_alg) }}</dd>
                </div>

                <div class="lum-row">
                    <dt class="lum-dt">@lang('ledger::app.receipt.epoch-status')</dt>
                    <dd class="lum-dd">
                        @if (is_array($epoch) && isset($epoch['id']))
                            @lang('ledger::app.receipt.epoch-sealed', ['id' => $epoch['id']])
                        @else
                            @lang('ledger::app.receipt.epoch-pending')
                        @endif
                    </dd>
                </div>
            </dl>

            <details class="lum-details">
                <summary>@lang('ledger::app.receipt.proof-toggle')</summary>
                <div class="lum-proof-box">
                    <div>
                        <p class="lum-field-label">@lang('ledger::app.receipt.block-hash')</p>
                        <p class="lum-hash-box">{{ $block->block_hash }}</p>
                    </div>

                    <div>
                        <p class="lum-field-label">@lang('ledger::app.receipt.prev-block-hash')</p>
                        <p class="lum-hash-box">{{ $block->prev_block_hash }}</p>
                    </div>

                    <div>
                        <p class="lum-field-label">@lang('ledger::app.receipt.signer-pubkey')</p>
                        <p class="lum-hash-box">{{ $block->signer_pubkey }}</p>
                    </div>

                    <div>
                        <p class="lum-field-label">@lang('ledger::app.receipt.signer-proof')</p>
                        <p class="lum-hash-box">{{ $block->signer_proof }}</p>
                    </div>

                    @if (is_array($epoch))
                        <div>
                            <p class="lum-field-label">@lang('ledger::app.receipt.merkle-root')</p>
                            <p class="lum-hash-box">{{ $epoch['merkle_root'] ?? '—' }}</p>
                        </div>

                        <div>
                            <p class="lum-field-label">@lang('ledger::app.receipt.anchor-ref')</p>
                            <p class="lum-hash-box">{{ $epoch['anchor_reference'] ?? ($epoch['anchor_driver'] ?? 'local') }}</p>
                        </div>

                        <div>
                            <p class="lum-field-label">@lang('ledger::app.receipt.merkle-path')</p>
                            @if (count($merkleProof) > 0)
                                <ol class="lum-steps">
                                    @foreach ($merkleProof as $idx => $step)
                                        <li>[{{ $idx }}] {{ $step['direction'] }}: {{ $step['sibling'] }}</li>
                                    @endforeach
                                </ol>
                            @else
                                <p class="lum-hash-box">@lang('ledger::app.receipt.merkle-single-leaf')</p>
                            @endif
                        </div>
                    @endif
                </div>
            </details>

            <footer class="lum-footer">
                <a class="lum-link" href="{{ route('api.ledger.blocks.proof', ['codeOrHash' => $block->receipt_code]) }}">
                    @lang('ledger::app.receipt.json-proof-link')
                </a>
            </footer>
        </article>
    </main>
</body>
</html>
