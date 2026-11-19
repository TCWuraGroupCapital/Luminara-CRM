{{--
    Desk styles: "Pipeline you can hold" (docs/design.md, docs/prototype/v2). Plain CSS scoped to .lum-desk,
    because this package is outside the admin Tailwind build. Light by default, `.dark` follows the admin theme.
    The model sits on a dark plinth in both themes, so it has its own fixed palette (--p-*).
--}}
@pushOnce('styles')
<style>
    .lum-desk {
        --surface: oklch(1 0 0); --surface-2: oklch(0.955 0.006 190);
        --line: oklch(0.25 0.03 190 / 0.10); --line-2: oklch(0.25 0.03 190 / 0.18);
        --fg: oklch(0.21 0.02 190); --fg-2: oklch(0.44 0.018 190); --fg-3: oklch(0.52 0.015 190);
        --brand: oklch(0.45 0.085 186); --brand-fg: oklch(0.99 0.005 186); --brand-wash: oklch(0.95 0.03 186);
        --pencil: oklch(0.47 0.10 250); --pencil-wash: oklch(0.95 0.025 245); --pencil-line: oklch(0.47 0.10 250 / 0.5);
        --stamp: oklch(0.52 0.19 30); --stamp-wash: oklch(0.96 0.025 30); --ok: oklch(0.5 0.12 155);
        --focus: oklch(0.47 0.14 250); --page: oklch(0.975 0.004 190);
        --plinth: oklch(0.2 0.018 210); --p-raise: oklch(1 0 0 / 0.05); --p-line: oklch(1 0 0 / 0.1);
        --p-fg: oklch(0.95 0.006 210); --p-fg-2: oklch(0.76 0.012 210);
        --p-pencil: oklch(0.82 0.08 245); --p-pencil-line: oklch(0.82 0.08 245 / 0.5); --p-stamp: oklch(0.78 0.13 32);
        --mono: ui-monospace, "Cascadia Mono", Consolas, monospace;
        --r-m: 14px; --r-l: 22px;
        --shadow-1: 0 1px 2px oklch(0.2 0.03 190 / 0.06), 0 6px 20px -10px oklch(0.2 0.03 190 / 0.18);
        --shadow-3: 0 2px 6px oklch(0.2 0.03 190 / 0.08), 0 30px 80px -24px oklch(0.2 0.03 190 / 0.35);
        --out: cubic-bezier(0.22, 1, 0.36, 1); --in: cubic-bezier(0.55, 0, 1, 0.45);
        display: flex; flex-direction: column; gap: 40px; color: var(--fg); font-size: 15px; line-height: 1.5;
    }
    .dark .lum-desk {
        --surface: oklch(0.195 0.012 190); --surface-2: oklch(0.235 0.013 190);
        --line: oklch(1 0 0 / 0.09); --line-2: oklch(1 0 0 / 0.16);
        --fg: oklch(0.95 0.006 190); --fg-2: oklch(0.77 0.012 190); --fg-3: oklch(0.66 0.012 190);
        --brand: oklch(0.78 0.10 186); --brand-fg: oklch(0.16 0.02 186); --brand-wash: oklch(0.28 0.04 186);
        --pencil: oklch(0.78 0.09 245); --pencil-wash: oklch(0.27 0.04 250); --pencil-line: oklch(0.78 0.09 245 / 0.45);
        --stamp: oklch(0.74 0.14 32); --stamp-wash: oklch(0.27 0.05 30); --ok: oklch(0.78 0.12 155);
        --focus: oklch(0.82 0.11 245); --page: oklch(0.155 0.01 190);
        --shadow-1: 0 1px 2px oklch(0 0 0 / 0.4), 0 6px 20px -10px oklch(0 0 0 / 0.6);
        --shadow-3: 0 2px 6px oklch(0 0 0 / 0.5), 0 30px 80px -24px oklch(0 0 0 / 0.8);
    }
    .lum-desk [hidden] { display: none !important; }
    /* The admin forces p { line-height: 17px !important }, which crushes wrapped text. */
    .lum-desk p { line-height: 1.5 !important; margin: 0; text-wrap: pretty; }
    .lum-desk :is(h1, h2, h3, h4) { margin: 0; text-wrap: balance; }
    .lum-desk svg { width: 20px; height: 20px; fill: none; stroke: currentColor; stroke-width: 1.75; stroke-linecap: round; stroke-linejoin: round; flex: none; }
    .lum-desk .num, .lum-desk time { font-variant-numeric: tabular-nums; }
    .lum-desk .sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
    .lum-desk :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; }
    .lum-desk .stage-card :focus-visible { outline-color: var(--p-pencil); }

    .lum-desk .btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 40px; padding: 0 16px; border-radius: 999px; border: 1px solid var(--line-2); background: var(--surface); color: var(--fg); font-weight: 600; cursor: pointer; white-space: nowrap; transition: background-color .12s, transform .12s var(--out); }
    .lum-desk .btn:hover { background: var(--surface-2); }
    .lum-desk .btn:active { transform: scale(.97); }
    .lum-desk .btn:disabled { opacity: .45; cursor: not-allowed; transform: none; }
    .lum-desk .btn.primary { background: var(--brand); color: var(--brand-fg); border-color: transparent; }
    .lum-desk .btn.danger { background: var(--stamp); color: oklch(0.99 0 0); border-color: transparent; }
    .lum-desk .btn.quiet { border-color: transparent; background: transparent; color: var(--fg-2); }
    .lum-desk .btn.quiet:hover { background: var(--surface-2); color: var(--fg); }
    .lum-desk .btn.lg { min-height: 48px; padding: 0 22px; font-size: 16px; }
    .lum-desk .icon-btn { display: inline-grid; place-items: center; width: 40px; height: 40px; border-radius: 999px; border: 0; background: transparent; color: var(--fg-2); cursor: pointer; }
    .lum-desk .icon-btn:hover { background: var(--surface-2); color: var(--fg); }
    .lum-desk .chip-btn { min-height: 34px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--line-2); background: var(--surface); color: var(--fg); font-size: 14px; font-weight: 500; cursor: pointer; }
    .lum-desk .link { display: inline-block; padding-block: 10px; color: var(--fg-2); font-weight: 500; text-decoration: underline; text-underline-offset: 4px; }
    .lum-desk .linkish { border: 0; padding: 0; background: none; color: inherit; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; cursor: pointer; }

    /* Hello */
    .lum-desk .hello { display: grid; gap: 12px; padding-top: 8px; }
    .lum-desk .date { color: var(--fg-2); font-weight: 500; }
    .lum-desk .hello h1 { font-size: clamp(2rem, 1.3rem + 2.4vw, 3.25rem); line-height: 1.04; font-weight: 650; letter-spacing: -0.035em; max-width: 18ch; }
    .lum-desk .hello h1 span { color: var(--pencil); }
    .lum-desk .lede { font-size: 18px; color: var(--fg-2); max-width: 34em; }
    .lum-desk .hello-actions { display: flex; flex-wrap: wrap; align-items: center; gap: 20px; margin-top: 8px; }

    /* Plinth: model, suggestions, totals */
    .lum-desk .stage-card { position: relative; background: var(--plinth); color: var(--p-fg); border-radius: var(--r-l); padding: 22px 22px 0; display: grid; gap: 14px; box-shadow: var(--shadow-1), inset 0 0 0 1px oklch(1 0 0 / 0.07); overflow: hidden; }
    .lum-desk .stage-card[data-mode="bars"] :is(.model-only, .totals), .lum-desk .stage-card:not([data-mode="bars"]) .bars-only { display: none; }
    .lum-desk .stage-card[data-mode="bars"] { padding-bottom: 18px; }
    .lum-desk .stage-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap; padding-inline: 6px; }
    .lum-desk .stage-head h2 { font-size: 20px; font-weight: 600; letter-spacing: -0.01em; }
    .lum-desk .legend { color: var(--p-fg-2); font-size: 14px; margin-top: 4px; }
    .lum-desk .key { display: inline-block; width: 14px; height: 10px; border-radius: 2px; vertical-align: -1px; margin-inline-end: 6px; }
    .lum-desk .key.solid { background: oklch(0.72 0.09 186); }
    .lum-desk .key.dashed { border: 1.5px dashed var(--p-pencil); margin-inline-start: 14px; }
    .lum-desk .stage-tools { display: flex; gap: 8px; flex-wrap: wrap; }
    .lum-desk .stage-card .chip-btn { background: var(--p-raise); border-color: var(--p-line); color: var(--p-fg); }
    .lum-desk .stage-card .chip-btn:hover { background: oklch(1 0 0 / 0.12); }
    .lum-desk .stage-card .chip-btn[aria-pressed="true"] { background: var(--p-fg); color: var(--plinth); }
    .lum-desk .stage-grid { display: grid; grid-template-columns: minmax(0, 1fr) 330px; gap: 16px; align-items: start; }
    .lum-desk .stage-card[data-narrow] .stage-grid, .lum-desk .stage-card[data-mode="bars"] .stage-grid { grid-template-columns: minmax(0, 1fr); }
    .lum-desk .model-stage { position: relative; height: clamp(360px, 34vw, 500px); margin-inline-start: -22px; cursor: grab; touch-action: pan-y; }
    .lum-desk .stage-card[data-narrow] .model-stage { margin-inline: -22px; }
    .lum-desk .model-stage canvas { display: block; width: 100%; height: 100%; }
    .lum-desk .labels { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
    .lum-desk .labels > * { position: absolute; left: 0; top: 0; }
    .lum-desk .lane-label { translate: -50% 0; text-align: center; white-space: nowrap; font-size: 13px; color: var(--p-fg-2); line-height: 1.3; }
    .lum-desk .lane-label b { display: block; color: var(--p-fg); font-size: 15px; font-weight: 600; }
    .lum-desk .deal-name { translate: -50% -100%; max-width: 132px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; padding: 2px 8px; border-radius: 6px; background: oklch(0.14 0.015 210 / 0.72); color: var(--p-fg); font-size: 11px; font-weight: 600; }
    .lum-desk .ghost-label { translate: -50% -100%; padding: 4px 10px; border-radius: 999px; border: 1.5px dashed var(--p-pencil); color: var(--p-pencil); background: oklch(0.2 0.03 245 / 0.85); font-size: 12px; font-weight: 600; white-space: nowrap; transition: opacity .3s, scale .2s var(--out), background-color .2s; }
    .lum-desk .ghost-label.remove { border-color: var(--p-stamp); color: var(--p-stamp); background: oklch(0.22 0.04 30 / 0.85); }
    .lum-desk .ghost-label.lit { scale: 1.08; background: oklch(0.32 0.07 245); color: oklch(0.97 0.02 245); }
    .lum-desk .ghost-label.remove.lit { background: oklch(0.34 0.08 30); color: oklch(0.97 0.02 30); }
    .lum-desk .ghost-label.gone { opacity: 0; }
    .lum-desk .tip { position: absolute; left: 0; top: 0; z-index: 2; pointer-events: none; translate: -50% calc(-100% - 14px); padding: 10px 12px; border-radius: 12px; background: oklch(0.98 0 0); color: oklch(0.2 0.02 210); box-shadow: var(--shadow-3); font-size: 13px; line-height: 1.35; width: max-content; max-width: 260px; }
    .lum-desk .tip b { display: block; font-size: 14px; overflow-wrap: anywhere; }
    .lum-desk .tip span { color: oklch(0.45 0.02 210); }
    .lum-desk .tip .sug-note { color: oklch(0.47 0.10 250); font-weight: 600; }
    .lum-desk .model-stage[data-drop]::after { content: attr(data-drop); position: absolute; top: 12px; inset-inline-start: 50%; translate: -50% 0; padding: 6px 14px; border-radius: 999px; background: var(--p-fg); color: var(--plinth); font-weight: 600; font-size: 13px; }
    .lum-desk .drag-hint { position: absolute; inset-inline-end: 0; bottom: 10px; max-width: 70%; text-align: end; font-size: 12px; color: var(--p-fg-2); transition: opacity .4s; }
    .lum-desk .drag-hint.used { opacity: 0; }
    @media (pointer: coarse) { .lum-desk .drag-hint { display: none; } .lum-desk .model-stage { cursor: default; } }
    .lum-desk .skeleton { position: absolute; inset: 0; display: grid; place-content: center; gap: 18px; justify-items: center; background: var(--plinth); color: var(--p-fg-2); }
    .lum-desk .sk-lanes { display: flex; gap: 10px; transform: perspective(600px) rotateX(48deg); }
    .lum-desk .sk-lanes i { width: 64px; height: 120px; border-radius: 8px; background: var(--p-raise); animation: lum-breathe 1.4s ease-in-out infinite alternate; }
    .lum-desk .sk-lanes i:nth-child(2n) { animation-delay: .2s; }
    @keyframes lum-breathe { to { background: oklch(1 0 0 / 0.11); } }
    .lum-desk .model-note { padding: 10px 14px; border-radius: 12px; background: var(--p-raise); color: var(--p-fg-2); font-size: 14px; }

    .lum-desk .sugs { display: grid; gap: 10px; align-content: start; }
    .lum-desk .sugs h3 { font-size: 15px; font-weight: 600; color: var(--p-fg-2); }
    .lum-desk .sug-list { display: grid; gap: 10px; }
    .lum-desk .stage-card[data-narrow] .sug-list { grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); }
    .lum-desk .sug { display: grid; gap: 8px; padding: 14px; border-radius: var(--r-m); background: var(--p-raise); border: 1.5px dashed var(--p-pencil-line); transition: background-color .15s, border-color .15s; }
    .lum-desk .sug:hover, .lum-desk .sug:focus-within { background: oklch(1 0 0 / 0.09); border-color: var(--p-pencil); }
    .lum-desk .sug.remove { border-color: oklch(0.78 0.13 32 / 0.45); }
    .lum-desk .sug.remove:hover, .lum-desk .sug.remove:focus-within { border-color: var(--p-stamp); }
    .lum-desk .sug.leaving { animation: lum-leave .17s var(--in) forwards; pointer-events: none; }
    @keyframes lum-leave { to { opacity: 0; transform: scale(.97); } }
    .lum-desk .sug .kind { display: flex; align-items: center; gap: 8px; font-size: 12px; font-weight: 600; color: var(--p-pencil); }
    .lum-desk .sug.remove .kind { color: var(--p-stamp); }
    .lum-desk .sug .kind i { display: grid; place-items: center; width: 24px; height: 24px; border-radius: 7px; background: oklch(1 0 0 / 0.07); }
    .lum-desk .sug .kind svg { width: 14px; height: 14px; }
    .lum-desk .sug h4 { font-size: 16px; font-weight: 600; line-height: 1.25; }
    .lum-desk .sug p { color: var(--p-fg-2); font-size: 13px; overflow-wrap: anywhere; }
    .lum-desk .sug .value { margin-inline-start: 10px; font-weight: 600; font-size: 13px; }
    .lum-desk .faces { display: flex; align-items: center; }
    .lum-desk .face { display: grid; place-items: center; width: 30px; height: 30px; border-radius: 50%; border: 2px solid var(--plinth); margin-inline-start: -8px; font-size: 11px; font-weight: 700; color: oklch(0.25 0.03 var(--fh)); background: oklch(0.88 0.07 var(--fh)); flex: none; }
    .lum-desk .face:first-child { margin-inline-start: 0; }
    .lum-desk .acts { display: flex; gap: 6px; flex-wrap: wrap; }
    .lum-desk .stage-card .btn { min-height: 36px; padding: 0 14px; font-size: 14px; }
    .lum-desk .stage-card .btn.quiet { color: var(--p-fg-2); }
    .lum-desk .stage-card .btn.quiet:hover { background: oklch(1 0 0 / 0.08); color: var(--p-fg); }
    .lum-desk .stage-card .btn:not(.primary):not(.quiet) { background: var(--p-raise); border-color: var(--p-line); color: var(--p-fg); }
    .lum-desk .stage-card .btn.primary { background: oklch(0.78 0.1 186); color: oklch(0.16 0.02 186); }
    .lum-desk .stage-card .btn.danger-soft { color: var(--p-stamp); border-color: oklch(0.78 0.13 32 / 0.45); }
    .lum-desk .sug-empty { padding: 20px 16px; border-radius: var(--r-m); background: var(--p-raise); display: grid; gap: 8px; justify-items: start; }
    .lum-desk .sug-empty b { font-size: 16px; }
    .lum-desk .sug-empty p { color: var(--p-fg-2); font-size: 14px; }

    .lum-desk .bars { display: grid; gap: 14px; padding-block: 4px 8px; }
    .lum-desk .bar-row { display: grid; gap: 6px; padding: 8px 10px; margin-inline: -10px; border: 0; border-radius: 12px; background: transparent; color: inherit; text-align: start; cursor: default; transition: background-color .2s; }
    .lum-desk .bar-row.lit { background: oklch(1 0 0 / 0.07); }
    .lum-desk .bar-label { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; }
    .lum-desk .bar-label span { color: var(--p-fg-2); font-size: 13px; text-align: end; }
    .lum-desk .track { position: relative; height: 14px; border-radius: 7px; background: var(--p-raise); overflow: hidden; }
    .lum-desk .fill { position: absolute; inset: 0; transform-origin: left; border-radius: 7px; background: oklch(0.72 0.09 186); transition: transform .8s var(--out); }
    [dir="rtl"] .lum-desk .fill { transform-origin: right; }
    .lum-desk .bar-row[data-code="won"] .fill { background: oklch(0.82 0.09 180); }
    .lum-desk .bar-row[data-code="lost"] .fill { background: oklch(0.6 0.01 210); }
    .lum-desk .pend { position: absolute; inset-block: 0; border-radius: 7px; }
    .lum-desk .pend.in { border: 1.5px dashed var(--p-pencil); background: oklch(0.82 0.08 245 / 0.15); }
    .lum-desk .pend.out { background: repeating-linear-gradient(135deg, oklch(0.82 0.08 245 / 0.55) 0 1px, transparent 1px 6px); }

    .lum-desk .stage-list { max-height: clamp(360px, 34vw, 500px); overflow: auto; }
    .lum-desk .stage-list table { width: 100%; border-collapse: collapse; font-size: 14px; }
    .lum-desk .stage-list :is(th, td) { text-align: start; padding: 8px 10px; border-bottom: 1px solid var(--p-line); }
    .lum-desk .stage-list th { color: var(--p-fg-2); font-weight: 500; position: sticky; top: 0; background: var(--plinth); }
    .lum-desk .totals { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); margin: 0 -22px; border-top: 1px solid var(--p-line); }
    .lum-desk .totals div { padding: 14px 20px 18px; border-inline-start: 1px solid oklch(1 0 0 / 0.08); min-width: 0; }
    .lum-desk .totals div:first-child { border-inline-start: 0; }
    .lum-desk .totals dt { font-size: 13px; color: var(--p-fg-2); }
    .lum-desk .totals dd { margin: 2px 0 0; font-size: 20px; font-weight: 600; letter-spacing: -0.02em; }
    .lum-desk .totals dd small { display: block; font-size: 12px; font-weight: 500; letter-spacing: 0; color: var(--p-fg-2); }
    .lum-desk .totals .delta { color: var(--p-pencil); }

    /* Activity */
    .lum-desk .section-head { display: grid; gap: 4px; margin-bottom: 18px; }
    .lum-desk .section-head h2 { font-size: 24px; font-weight: 600; letter-spacing: -0.02em; }
    .lum-desk .section-head p { color: var(--fg-2); }
    .lum-desk .timeline { list-style: none; margin: 0; padding: 0; display: grid; max-width: 760px; }
    .lum-desk .timeline li { display: grid; grid-template-columns: 64px 20px minmax(0, 1fr); gap: 12px; padding-block: 10px; }
    .lum-desk .timeline time { color: var(--fg-3); font-size: 14px; text-align: end; }
    .lum-desk .timeline .dot { position: relative; display: grid; justify-items: center; }
    .lum-desk .timeline .dot::before { content: ""; width: 10px; height: 10px; margin-top: 6px; border-radius: 50%; border: 2px solid var(--fg-3); background: var(--page); position: relative; z-index: 1; }
    .lum-desk .timeline .dot::after { content: ""; position: absolute; top: 18px; bottom: -22px; width: 1px; background: var(--line-2); }
    .lum-desk .timeline li:last-child .dot::after { display: none; }
    .lum-desk .timeline li.wait .dot::before { border-color: var(--pencil); border-style: dashed; }
    .lum-desk .timeline li.ok .dot::before { border-color: var(--ok); background: var(--ok); }
    .lum-desk .timeline li.no .dot::before { border-color: var(--fg-3); background: var(--fg-3); }
    .lum-desk .timeline .what { display: grid; gap: 2px; }
    .lum-desk .timeline .what b { font-weight: 600; overflow-wrap: anywhere; }
    .lum-desk .timeline .tag { justify-self: start; margin-top: 4px; font-size: 12px; font-weight: 600; padding: 2px 8px; border-radius: 999px; background: var(--pencil-wash); color: var(--pencil); }
    .lum-desk .timeline-empty { color: var(--fg-2); }

    /* Dialogs */
    .lum-desk dialog { padding: 0; border: 0; color: var(--fg); background: transparent; }
    .lum-desk dialog::backdrop { background: oklch(0.15 0.02 210 / 0.45); backdrop-filter: blur(3px); }
    .lum-desk .review { width: min(680px, calc(100% - 32px)); max-height: calc(100dvh - 48px); }
    .lum-desk .review[open] { animation: lum-pop .26s var(--out); }
    @keyframes lum-pop { from { opacity: 0; transform: scale(.96) translateY(8px); } }
    .lum-desk .review-inner { background: var(--surface); border-radius: var(--r-l); box-shadow: var(--shadow-3); overflow: hidden; }
    .lum-desk .review-top { display: flex; align-items: center; gap: 14px; padding: 14px 14px 14px 24px; border-bottom: 1px solid var(--line); }
    .lum-desk .step { font-weight: 600; font-size: 14px; color: var(--fg-2); }
    .lum-desk .dots { display: flex; gap: 6px; margin-inline-end: auto; }
    .lum-desk .dots i { width: 24px; height: 4px; border-radius: 2px; background: var(--line-2); }
    .lum-desk .dots i.on { background: var(--fg); }
    .lum-desk .dots i.ok { background: var(--ok); }
    .lum-desk .review-body { padding: 24px; display: grid; gap: 20px; }
    .lum-desk .review-body.swap { animation: lum-swap .28s var(--out); }
    @keyframes lum-swap { from { opacity: 0; transform: translateX(14px); } }
    .lum-desk .rv-kind { font-size: 13px; font-weight: 600; color: var(--pencil); }
    .lum-desk .rv-kind.remove { color: var(--stamp); }
    .lum-desk .review-body h3 { font-size: clamp(22px, 2vw + 12px, 28px); font-weight: 650; line-height: 1.15; letter-spacing: -0.02em; margin-top: 4px; }
    .lum-desk .changes { display: grid; gap: 8px; }
    .lum-desk .change { display: grid; grid-template-columns: 36px minmax(0, 1fr) auto; align-items: center; gap: 12px; padding: 12px; border-radius: 12px; background: var(--surface-2); }
    .lum-desk .change .face, .lum-desk .deal-id .face { width: 36px; height: 36px; border-color: transparent; }
    .lum-desk .change b { display: block; font-weight: 600; overflow-wrap: anywhere; }
    .lum-desk .change small { color: var(--fg-2); font-size: 13px; }
    .lum-desk .moving { display: flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 600; white-space: nowrap; }
    .lum-desk .pill { padding: 3px 10px; border-radius: 999px; background: var(--surface); border: 1px solid var(--line-2); }
    .lum-desk .pill.was { color: var(--fg-3); text-decoration: line-through; }
    .lum-desk .pill.to { color: var(--pencil); background: var(--pencil-wash); border: 1.5px dashed var(--pencil-line); }
    .lum-desk .pill.gone { color: var(--stamp); background: var(--stamp-wash); border: 1.5px dashed color-mix(in oklch, var(--stamp) 50%, transparent); }
    .lum-desk .moving svg { width: 16px; height: 16px; color: var(--fg-3); }
    [dir="rtl"] .lum-desk .moving svg { transform: scaleX(-1); }
    .lum-desk .why { padding: 14px 16px; border-radius: 12px; border: 1px dashed var(--pencil-line); display: grid; gap: 6px; justify-items: start; overflow-wrap: anywhere; }
    .lum-desk .why span { font-size: 13px; color: var(--fg-3); }
    .lum-desk .effects { display: grid; gap: 6px; padding: 0; margin: 0; list-style: none; color: var(--fg-2); }
    .lum-desk .effects li { display: flex; gap: 10px; }
    .lum-desk .effects svg { width: 18px; height: 18px; color: var(--ok); margin-top: 2px; }
    .lum-desk .effects li.warn svg { color: var(--stamp); }
    .lum-desk .confirm { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 12px; background: var(--stamp-wash); font-weight: 500; cursor: pointer; }
    .lum-desk .confirm input { width: 20px; height: 20px; margin: 1px 0 0; accent-color: var(--stamp); flex: none; }
    .lum-desk details.tech { font-size: 13px; color: var(--fg-3); }
    .lum-desk details.tech summary { cursor: pointer; width: max-content; padding-block: 4px; }
    .lum-desk details.tech code { font-family: var(--mono); }
    .lum-desk .rv-foot { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; padding-top: 4px; }
    .lum-desk .rv-foot [data-rv="ok"] { margin-inline-start: auto; }
    .lum-desk .result { display: grid; justify-items: center; gap: 10px; padding: 24px 0 8px; text-align: center; }
    .lum-desk .result .rv-foot { justify-content: center; }
    .lum-desk .result p { color: var(--fg-2); max-width: 30em; }
    .lum-desk .seal { display: grid; place-items: center; width: 64px; height: 64px; border-radius: 50%; background: var(--brand-wash); color: var(--brand); animation: lum-seal .45s var(--out); }
    .lum-desk .seal svg { width: 30px; height: 30px; stroke-width: 2.5; }
    .lum-desk .seal path { stroke-dasharray: 30; stroke-dashoffset: 30; animation: lum-draw .4s .12s var(--out) forwards; }
    @keyframes lum-seal { from { transform: scale(.6); opacity: 0; } }
    @keyframes lum-draw { to { stroke-dashoffset: 0; } }

    .lum-desk .sheet { margin: 0 0 0 auto; height: 100dvh; max-height: 100dvh; width: min(480px, 100%); max-width: 100%; }
    [dir="rtl"] .lum-desk .sheet { margin: 0 auto 0 0; }
    .lum-desk .sheet[open] { animation: lum-sheet .3s var(--out); }
    @keyframes lum-sheet { from { transform: translateX(32px); opacity: 0; } }
    .lum-desk .sheet-inner { height: 100%; display: grid; grid-template-rows: auto minmax(0, 1fr); background: var(--surface); box-shadow: var(--shadow-3); overflow: auto; }
    .lum-desk .ask-inner { grid-template-rows: auto minmax(0, 1fr) auto; overflow: hidden; }
    .lum-desk .sheet-top { display: flex; gap: 12px; align-items: flex-start; justify-content: space-between; padding: 22px 18px 16px 24px; border-bottom: 1px solid var(--line); }
    .lum-desk .sheet-top h2 { font-size: 22px; font-weight: 650; letter-spacing: -0.02em; overflow-wrap: anywhere; }
    .lum-desk .sheet-top p { color: var(--fg-2); }
    .lum-desk .sheet-body { padding: 24px; display: grid; gap: 20px; align-content: start; }
    .lum-desk .disclose { margin-top: 6px; font-size: 13px; }
    .lum-desk .deal-id { display: flex; gap: 14px; align-items: flex-start; min-width: 0; }
    .lum-desk .deal-id .face { width: 48px; height: 48px; font-size: 15px; }
    .lum-desk .facts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin: 0; }
    .lum-desk .facts div { padding: 12px; border-radius: 12px; background: var(--surface-2); min-width: 0; }
    .lum-desk .facts dt { font-size: 13px; color: var(--fg-2); }
    .lum-desk .facts dd { margin: 2px 0 0; font-weight: 600; font-size: 17px; overflow-wrap: anywhere; }
    .lum-desk .move-form { display: grid; gap: 8px; }
    .lum-desk .move-form label { font-weight: 600; }
    .lum-desk .move-form .row { display: flex; gap: 8px; }
    .lum-desk .move-form select { flex: 1; min-height: 44px; padding-inline: 12px; border-radius: 12px; border: 1px solid var(--line-2); background: var(--page); color: var(--fg); }
    .lum-desk .move-form .hint { font-size: 13px; color: var(--fg-2); }
    .lum-desk .ask-log { overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 18px; }
    .lum-desk .msg { display: grid; gap: 6px; justify-items: start; }
    .lum-desk .msg .by { font-size: 13px; font-weight: 600; color: var(--fg-2); }
    .lum-desk .msg.agent .by { color: var(--pencil); }
    .lum-desk .msg p { font-size: 16px; line-height: 1.55 !important; white-space: pre-wrap; overflow-wrap: anywhere; }
    .lum-desk .msg.agent p { padding-inline-start: 12px; border-inline-start: 2px solid var(--pencil-line); }
    .lum-desk .msg.error p { padding-inline-start: 12px; border-inline-start: 2px solid var(--stamp); color: var(--stamp); }
    .lum-desk .plan { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
    .lum-desk .plan li { display: flex; align-items: center; gap: 10px; font-size: 14px; color: var(--fg-2); }
    .lum-desk .plan li::before { content: ""; width: 14px; height: 14px; border-radius: 50%; border: 2px solid var(--ok); background: var(--ok); flex: none; }
    .lum-desk .plan li.pending_approval::before { background: none; border-color: var(--pencil); border-style: dashed; }
    .lum-desk .plan li.run::before { background: none; border-color: var(--pencil); border-top-color: transparent; animation: lum-spin .8s linear infinite; }
    @keyframes lum-spin { to { rotate: 1turn; } }
    .lum-desk .suggest { display: flex; gap: 8px; flex-wrap: wrap; padding: 0 0 12px; }
    .lum-desk .composer { display: grid; gap: 10px; padding: 14px 18px 18px 24px; border-top: 1px solid var(--line); }
    .lum-desk .composer .row { display: flex; gap: 10px; align-items: flex-end; }
    .lum-desk .composer textarea { flex: 1; min-height: 52px; max-height: 160px; resize: vertical; padding: 12px 14px; border-radius: 14px; border: 1px solid var(--line-2); background: var(--page); color: var(--fg); font-size: 16px; line-height: 1.45; }
    .lum-desk .notice { padding: 10px 14px; border-radius: 12px; background: var(--stamp-wash); font-size: 14px; }

    .lum-desk .toast { position: fixed; z-index: 10050; inset-inline-start: 50%; bottom: 24px; translate: -50% 0; display: flex; align-items: center; gap: 14px; padding: 8px 8px 8px 18px; border-radius: 999px; background: var(--fg); color: var(--page); box-shadow: var(--shadow-3); font-weight: 500; animation: lum-toast .26s var(--out); max-width: calc(100% - 32px); }
    .lum-desk .toast .btn { min-height: 36px; background: transparent; color: inherit; border-color: color-mix(in oklch, var(--page) 35%, transparent); }
    @keyframes lum-toast { from { opacity: 0; transform: translateY(10px); } }

    @media (max-width: 640px) {
        .lum-desk .lede { font-size: 16px; }
        .lum-desk .stage-card { padding: 18px 16px; }
        .lum-desk .change { grid-template-columns: 36px minmax(0, 1fr); }
        .lum-desk .change .moving { grid-column: 2; }
        .lum-desk .rv-foot .btn { flex: 1; }
        .lum-desk .rv-foot [data-rv="ok"], .lum-desk .result .rv-foot .btn.primary { flex-basis: 100%; order: -1; }
        .lum-desk .facts { grid-template-columns: 1fr 1fr; }
        .lum-desk .timeline li { grid-template-columns: 48px 20px minmax(0, 1fr); }
        .lum-desk .btn, .lum-desk .chip-btn, .lum-desk .stage-card .btn { min-height: 44px; }
    }
    @media (prefers-reduced-motion: reduce) {
        .lum-desk *, .lum-desk *::before, .lum-desk *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: 0s !important; }
        .lum-desk .seal path { stroke-dashoffset: 0; }
    }
</style>
@endPushOnce
