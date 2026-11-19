{{--
    Luminara "drafting table" tokens for the agent pages (docs/design.md).
    The agent drafts in pencil (dashed, non-photo blue); people sign in ink (solid). Vermilion marks what cannot be undone.
    Plain CSS scoped to .lum-agent: this package is outside the admin Tailwind build.
--}}
@pushOnce('styles')
<style>
    .lum-agent {
        --paper: oklch(0.985 0.004 190); --sheet: oklch(1 0 0); --well: oklch(0.965 0.006 190);
        --rule: oklch(0.25 0.03 190 / 0.12); --rule-2: oklch(0.25 0.03 190 / 0.22);
        --ink: oklch(0.22 0.025 190); --ink-2: oklch(0.45 0.02 190); --ink-3: oklch(0.52 0.016 190);
        --brand: oklch(0.45 0.085 186); --brand-fg: oklch(0.99 0.005 186);
        --pencil: oklch(0.47 0.10 250); --pencil-wash: oklch(0.95 0.025 245); --pencil-line: oklch(0.47 0.10 250 / 0.55);
        --stamp: oklch(0.52 0.19 30); --stamp-wash: oklch(0.96 0.025 30); --ok: oklch(0.48 0.11 155);
        --focus: oklch(0.47 0.14 250);
        --mono: ui-monospace, "Cascadia Mono", "SF Mono", Consolas, monospace;
        --r-1: 4px; --r-2: 10px;
        display: flex; flex-direction: column; gap: 24px; margin-top: 16px; color: var(--ink); font-size: 14px; line-height: 1.5;
    }
    .dark .lum-agent {
        --paper: oklch(0.165 0.012 190); --sheet: oklch(0.195 0.013 190); --well: oklch(0.225 0.014 190);
        --rule: oklch(1 0 0 / 0.10); --rule-2: oklch(1 0 0 / 0.18);
        --ink: oklch(0.94 0.008 190); --ink-2: oklch(0.76 0.012 190); --ink-3: oklch(0.66 0.012 190);
        --brand: oklch(0.78 0.10 186); --brand-fg: oklch(0.16 0.02 186);
        --pencil: oklch(0.78 0.09 245); --pencil-wash: oklch(0.27 0.04 250); --pencil-line: oklch(0.78 0.09 245 / 0.5);
        --stamp: oklch(0.74 0.14 32); --stamp-wash: oklch(0.27 0.05 30); --ok: oklch(0.78 0.12 155);
        --focus: oklch(0.82 0.11 245);
    }
    /* The admin sets p { line-height: 17px !important }, which crushes wrapped text; only !important answers it. */
    .lum-agent p { line-height: 1.5 !important; }
    .lum-agent .turn .text { line-height: 1.55 !important; }
    .lum-agent :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; border-radius: var(--r-1); }
    .lum-agent .num, .lum-agent time { font-variant-numeric: tabular-nums; }
    .lum-agent .nowrap { white-space: nowrap; }
    .lum-agent .slips { display: flex; flex-direction: column; gap: 16px; }
    .lum-agent .pager { padding: 12px 16px; }
    .lum-agent .reason.tight { margin-top: 6px; }
    .lum-agent .mono { font-family: var(--mono); font-size: 13px; }
    .lum-agent .head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
    .lum-agent .head h1 { font-size: 24px; line-height: 1.2; font-weight: 600; letter-spacing: -0.02em; text-wrap: balance; }
    .lum-agent .head p { margin-top: 4px; max-width: 46rem; color: var(--ink-2); font-size: 15px; text-wrap: pretty; }
    .lum-agent .head p b { font-weight: 500; color: var(--pencil); }
    .lum-agent h2 { font-size: 18px; font-weight: 600; display: flex; align-items: baseline; gap: 10px; flex-wrap: wrap; margin-bottom: 12px; }
    .lum-agent h2 small { font-size: 14px; font-weight: 400; color: var(--ink-2); }

    .lum-agent .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 36px; padding: 0 14px; border-radius: var(--r-1); border: 1px solid var(--rule-2); background: var(--sheet); color: var(--ink); font-weight: 500; white-space: nowrap; cursor: pointer; text-decoration: none; transition: background-color .12s; }
    .lum-agent .btn:hover { background: var(--well); }
    .lum-agent .btn:disabled { opacity: .5; cursor: not-allowed; }
    .lum-agent .btn.sm { min-height: 30px; padding: 0 10px; font-size: 13px; }
    .lum-agent .btn.sign { background: var(--brand); color: var(--brand-fg); border-color: transparent; }
    .lum-agent .btn.sign:hover { background: oklch(from var(--brand) calc(l - 0.04) c h); }
    .lum-agent .btn.danger { color: var(--stamp); border-color: color-mix(in oklch, var(--stamp) 45%, transparent); }
    .lum-agent .btn.danger[aria-pressed="true"] { background: var(--stamp); color: var(--sheet); }

    /* Proposal slip: dashed pencil until a person signs it. */
    .lum-agent .slip { position: relative; display: grid; gap: 14px; padding: 18px 20px; border-radius: var(--r-2); background: var(--sheet); border: 1.5px dashed var(--pencil-line); }
    .lum-agent .slip.destroy { border-color: color-mix(in oklch, var(--stamp) 55%, transparent); }
    .lum-agent .slip .meta { color: var(--ink-3); font-size: 13px; }
    .lum-agent .slip h3 { font-size: 16px; font-weight: 600; }
    .lum-agent .slip .who { color: var(--ink-2); }
    .lum-agent .slip .who b { color: var(--pencil); font-weight: 500; }
    .lum-agent table.diff { width: 100%; border-collapse: collapse; }
    .lum-agent table.diff th { text-align: start; font-size: 13px; font-weight: 500; color: var(--ink-3); padding: 0 12px 6px 0; }
    .lum-agent table.diff td { padding: 8px 12px 8px 0; border-top: 1px solid var(--rule); vertical-align: baseline; }
    .lum-agent table.diff :is(th, td):last-child { padding-inline-end: 0; text-align: end; white-space: nowrap; }
    .lum-agent .was { color: var(--ink-3); text-decoration: line-through; }
    .lum-agent .to { margin-inline-start: 4px; padding: 1px 6px; border-radius: 3px; color: var(--pencil); font-weight: 500; background: var(--pencil-wash); text-decoration: underline 1.5px dashed var(--pencil-line); text-underline-offset: 4px; }
    .lum-agent .gone { padding: 1px 6px; border-radius: 3px; color: var(--stamp); font-weight: 500; background: var(--stamp-wash); text-decoration: line-through 1.5px var(--stamp); }
    .lum-agent :is(.to, .gone) { -webkit-box-decoration-break: clone; box-decoration-break: clone; }
    .lum-agent .params { font-family: var(--mono); font-size: 13px; color: var(--ink-2); overflow-wrap: anywhere; }
    .lum-agent .reason { max-width: 44rem; padding-inline-start: 12px; border-inline-start: 2px dashed var(--pencil-line); color: var(--ink-2); overflow-wrap: anywhere; }
    .lum-agent .reason span { display: block; font-size: 13px; color: var(--ink-3); }
    .lum-agent .checks { display: flex; flex-wrap: wrap; gap: 4px 16px; font-size: 13px; color: var(--ink-2); }
    .lum-agent .checks span::before { content: ""; display: inline-block; width: 6px; height: 6px; margin-inline-end: 6px; border-radius: 50%; background: var(--ok); vertical-align: middle; }
    .lum-agent .checks span.warn::before { background: none; border: 1px solid var(--stamp); }
    .lum-agent .actions { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
    .lum-agent .actions form { display: contents; }
    .lum-agent .actions .note { font-size: 13px; color: var(--stamp); }

    /* Edit-to-Sign & feedback controls. */
    .lum-agent details.edit-drawer { border-top: 1px solid var(--rule); padding-top: 10px; }
    .lum-agent details.edit-drawer > summary { cursor: pointer; font-size: 13px; font-weight: 500; color: var(--pencil); user-select: none; }
    .lum-agent .edit-grid { display: grid; gap: 10px; margin-top: 10px; }
    .lum-agent .field { display: grid; gap: 4px; }
    .lum-agent .field label { font-size: 13px; font-weight: 500; color: var(--ink-2); }
    .lum-agent .field input[type="text"],
    .lum-agent .field input[type="number"],
    .lum-agent .field input[type="url"],
    .lum-agent .field select,
    .lum-agent .field textarea { width: 100%; padding: 8px 10px; border-radius: var(--r-1); border: 1px solid var(--rule-2); background: var(--sheet); color: var(--ink); font-size: 14px; line-height: 1.45; }
    .lum-agent .field textarea.mono { font-family: var(--mono); font-size: 13px; min-height: 88px; }
    .lum-agent .check-inline { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--ink-2); cursor: pointer; }
    .lum-agent .receipt-pill { display: inline-block; padding: 1px 6px; border-radius: var(--r-1); border: 1px solid var(--rule-2); background: var(--well); color: var(--ink-2); text-decoration: none; font-size: 12px; }
    .lum-agent .receipt-pill:hover { color: var(--ink); border-color: var(--pencil-line); }
    .lum-agent .pencil-tag { display: inline-block; padding: 1px 6px; border-radius: var(--r-1); background: var(--pencil-wash); color: var(--pencil); font-size: 12px; font-weight: 500; }
    .lum-agent .tag-gap { margin-top: 4px; }
    .lum-agent .intake-row { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .lum-agent .intake-row input[type="text"] { flex: 1 1 240px; min-width: 0; padding: 8px 10px; border-radius: var(--r-1); border: 1px solid var(--rule-2); background: var(--sheet); color: var(--ink); font-size: 14px; line-height: 1.45; }

    /* Ledger: every call as a receipt. */
    .lum-agent .ledger-wrap { overflow-x: auto; border-radius: var(--r-2); background: var(--sheet); box-shadow: inset 0 0 0 1px var(--rule); }
    .lum-agent table.ledger { width: 100%; border-collapse: collapse; }
    .lum-agent table.ledger th { text-align: start; font-size: 13px; font-weight: 500; color: var(--ink-3); padding: 10px 14px; border-bottom: 1px solid var(--rule); white-space: nowrap; }
    .lum-agent table.ledger td { padding: 10px 14px; border-bottom: 1px solid var(--rule); vertical-align: baseline; }
    .lum-agent table.ledger tr:last-child td { border-bottom: 0; }
    .lum-agent .state { font-size: 13px; font-weight: 500; white-space: nowrap; }
    .lum-agent .state.pending_approval, .lum-agent .state.running { color: var(--pencil); }
    .lum-agent .state.completed { color: var(--ok); }
    .lum-agent .state.failed { color: var(--stamp); }
    .lum-agent .state.rejected { color: var(--ink-3); }
    .lum-agent .empty { padding: 32px 16px; text-align: center; color: var(--ink-2); }

    /* Morning Briefing: asymmetric 7 + 5 Bento layout. */
    .lum-agent .skills-bar { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; padding: 10px 14px; border-radius: var(--r-2); background: var(--sheet); box-shadow: inset 0 0 0 1px var(--rule); }
    .lum-agent .skills-bar .kicker { font-size: 12px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em; color: var(--ink-3); margin-inline-end: 4px; }
    .lum-agent .bento-7-5 { display: grid; grid-template-columns: minmax(0, 7fr) minmax(0, 5fr); gap: 20px; align-items: start; }
    .lum-agent .stack { display: flex; flex-direction: column; gap: 20px; min-width: 0; }
    .lum-agent .sheet-card { background: var(--sheet); border-radius: var(--r-2); box-shadow: inset 0 0 0 1px var(--rule); padding: 18px 20px; display: grid; gap: 14px; }
    .lum-agent .sheet-card.pencil { box-shadow: none; border: 1.5px dashed var(--pencil-line); }
    .lum-agent .kpi-strip { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; }
    .lum-agent .kpi { padding: 10px 12px; border-radius: var(--r-1); background: var(--well); display: grid; gap: 2px; }
    .lum-agent .kpi .val { font-size: 20px; font-weight: 600; font-variant-numeric: tabular-nums; color: var(--ink); }
    .lum-agent .kpi .val.pencil { color: var(--pencil); }
    .lum-agent .kpi .lbl { font-size: 12px; color: var(--ink-2); }
    .lum-agent .quest-list { display: grid; gap: 8px; }
    .lum-agent .quest-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 10px 12px; border-radius: var(--r-1); border: 1px solid var(--rule); background: var(--paper); flex-wrap: wrap; }
    .lum-agent .quest-row.pencil { border-style: dashed; border-color: var(--pencil-line); background: var(--pencil-wash); }
    .lum-agent .preset-row { display: flex; flex-wrap: wrap; gap: 6px; }
    .lum-agent .token-banner { padding: 14px 18px; border-radius: var(--r-2); border: 1.5px dashed var(--pencil-line); background: var(--pencil-wash); display: grid; gap: 8px; }
    .lum-agent .token-banner pre { margin: 0; padding: 8px 10px; border-radius: var(--r-1); background: var(--sheet); border: 1px solid var(--rule); overflow-x: auto; font-family: var(--mono); font-size: 12px; }

    /* Assistant transcript. */
    .lum-agent .notice { padding: 12px 16px; border-radius: var(--r-2); border: 1px solid color-mix(in oklch, var(--stamp) 40%, transparent); background: var(--stamp-wash); }
    .lum-agent .log { display: flex; flex-direction: column; gap: 20px; min-height: 320px; max-height: calc(100vh - 360px); overflow-y: auto; padding: 20px; border-radius: var(--r-2); background: var(--sheet); box-shadow: inset 0 0 0 1px var(--rule); overscroll-behavior: contain; }
    .lum-agent .log .hint-empty { margin: auto; max-width: 28rem; text-align: center; color: var(--ink-2); font-size: 15px; }
    .lum-agent .turn { display: grid; gap: 6px; max-width: 46rem; }
    .lum-agent .turn .by { font-size: 13px; font-weight: 500; color: var(--ink-2); }
    .lum-agent .turn.assistant .by { color: var(--pencil); }
    .lum-agent .turn .text { font-size: 16px; line-height: 1.55; white-space: pre-wrap; overflow-wrap: anywhere; }
    .lum-agent .turn.assistant .text { padding-inline-start: 12px; border-inline-start: 2px solid var(--pencil-line); }
    .lum-agent .turn.error .text { padding-inline-start: 12px; border-inline-start: 2px solid var(--stamp); color: var(--stamp); }
    .lum-agent .steps { display: grid; gap: 4px; margin-inline-start: 1px; padding-inline-start: 14px; border-inline-start: 1px dashed var(--rule-2); font-size: 13px; color: var(--ink-2); }
    .lum-agent .steps li { display: flex; flex-wrap: wrap; gap: 8px; }
    .lum-agent .steps li .mono { color: var(--ink-3); }
    .lum-agent .steps li.pending_approval { color: var(--pencil); font-weight: 500; }
    .lum-agent .steps li.failed, .lum-agent .steps li.forbidden { color: var(--stamp); }
    .lum-agent .link { justify-self: start; padding: 4px 0; color: var(--pencil); font-weight: 500; text-decoration: underline; text-underline-offset: 3px; }
    .lum-agent .working { color: var(--ink-2); font-size: 13px; }
    .lum-agent .composer { display: grid; gap: 8px; }
    .lum-agent .composer textarea { width: 100%; min-height: 76px; resize: vertical; padding: 10px 12px; border-radius: var(--r-2); border: 1px solid var(--rule-2); background: var(--sheet); color: var(--ink); font-size: 16px; line-height: 1.5; }
    .lum-agent .composer textarea::placeholder { color: var(--ink-3); }
    .lum-agent .composer .row { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .lum-agent .composer .row span { font-size: 13px; color: var(--ink-3); }

    @media (max-width: 960px) {
        .lum-agent .bento-7-5 { grid-template-columns: minmax(0, 1fr); }
    }
    @media (max-width: 640px) {
        .lum-agent .btn { min-height: 44px; }
        .lum-agent .hide-sm { display: none; }
        .lum-agent .slip { padding: 16px; }
        /* Ledger rows stack: time, call and action on one line, then what it did, then its state. */
        .lum-agent table.ledger thead { position: absolute; width: 1px; height: 1px; overflow: hidden; clip-path: inset(50%); }
        .lum-agent table.ledger tr { display: grid; grid-template-columns: auto auto minmax(0, 1fr); gap: 4px 12px; padding: 12px 14px; border-bottom: 1px solid var(--rule); }
        .lum-agent table.ledger tr:last-child { border-bottom: 0; }
        .lum-agent table.ledger td { display: block; padding: 0; border: 0; }
        .lum-agent table.ledger td.hide-sm { display: none; }
        .lum-agent table.ledger td:nth-child(n+5) { grid-column: 1 / -1; }
    }
    @media (prefers-reduced-motion: reduce) {
        .lum-agent * { transition-duration: 0s !important; }
    }
</style>
@endPushOnce
