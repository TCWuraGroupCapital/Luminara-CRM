    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        :root, .lum-tma[data-theme="light"] {
            --paper: oklch(0.985 0.004 190);
            --sheet: oklch(1 0 0);
            --well: oklch(0.965 0.006 190);
            --rule: oklch(0.25 0.03 190 / 0.12);
            --rule-2: oklch(0.25 0.03 190 / 0.22);
            --ink: oklch(0.22 0.025 190);
            --ink-2: oklch(0.45 0.02 190);
            --ink-3: oklch(0.52 0.016 190);
            --brand: oklch(0.45 0.085 186);
            --brand-fg: oklch(0.99 0.005 186);
            --pencil: oklch(0.47 0.10 250);
            --pencil-wash: oklch(0.95 0.025 245);
            --pencil-line: oklch(0.47 0.10 250 / 0.55);
            --stamp: oklch(0.52 0.19 30);
            --stamp-wash: oklch(0.96 0.025 30);
            --ok: oklch(0.48 0.11 155);
            --focus: oklch(0.47 0.14 250);
            --r-1: 4px;
            --r-2: 10px;
            --ease: cubic-bezier(0.22, 1, 0.36, 1);
            --sans: "IBM Plex Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", system-ui, sans-serif;
            --mono: "IBM Plex Mono", ui-monospace, "SF Mono", Consolas, monospace;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --paper: oklch(0.165 0.012 190);
                --sheet: oklch(0.195 0.013 190);
                --well: oklch(0.225 0.014 190);
                --rule: oklch(1 0 0 / 0.10);
                --rule-2: oklch(1 0 0 / 0.18);
                --ink: oklch(0.94 0.008 190);
                --ink-2: oklch(0.76 0.012 190);
                --ink-3: oklch(0.66 0.012 190);
                --brand: oklch(0.78 0.10 186);
                --brand-fg: oklch(0.16 0.02 186);
                --pencil: oklch(0.78 0.09 245);
                --pencil-wash: oklch(0.27 0.04 250);
                --pencil-line: oklch(0.78 0.09 245 / 0.50);
                --stamp: oklch(0.74 0.14 32);
                --stamp-wash: oklch(0.27 0.05 30);
                --ok: oklch(0.78 0.12 155);
                --focus: oklch(0.82 0.11 245);
            }
        }
        .lum-tma[data-theme="dark"] {
            --paper: oklch(0.165 0.012 190);
            --sheet: oklch(0.195 0.013 190);
            --well: oklch(0.225 0.014 190);
            --rule: oklch(1 0 0 / 0.10);
            --rule-2: oklch(1 0 0 / 0.18);
            --ink: oklch(0.94 0.008 190);
            --ink-2: oklch(0.76 0.012 190);
            --ink-3: oklch(0.66 0.012 190);
            --brand: oklch(0.78 0.10 186);
            --brand-fg: oklch(0.16 0.02 186);
            --pencil: oklch(0.78 0.09 245);
            --pencil-wash: oklch(0.27 0.04 250);
            --pencil-line: oklch(0.78 0.09 245 / 0.50);
            --stamp: oklch(0.74 0.14 32);
            --stamp-wash: oklch(0.27 0.05 30);
            --ok: oklch(0.78 0.12 155);
            --focus: oklch(0.82 0.11 245);
        }
        html, body {
            min-height: 100%;
            background: var(--paper);
            color: var(--ink);
            font-family: var(--sans);
            font-size: 15px;
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }
        .lum-tma {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            max-width: 540px;
            margin: 0 auto;
            padding: max(12px, env(safe-area-inset-top)) 14px calc(76px + env(safe-area-inset-bottom));
            gap: 14px;
        }
        .lum-tma :focus-visible { outline: 2px solid var(--focus); outline-offset: 2px; border-radius: var(--r-1); }
        .num { font-variant-numeric: tabular-nums; }
        .mono { font-family: var(--mono); font-size: 13px; }
        .topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--rule);
        }
        .brandmark { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 16px; letter-spacing: -0.02em; }
        .hex {
            width: 22px; height: 22px; display: inline-grid; place-items: center;
            border: 1.5px solid var(--ink); border-radius: var(--r-1); font-family: var(--mono); font-size: 12px; font-weight: 600;
        }
        .kicker { font-size: 12px; color: var(--pencil); font-weight: 500; }
        .user-pill { font-size: 12px; color: var(--ink-2); font-family: var(--mono); }
        .sheet {
            background: var(--sheet);
            border: 1px solid var(--rule);
            border-radius: var(--r-2);
            padding: 16px;
            display: grid;
            gap: 12px;
        }
        .slip {
            background: var(--sheet);
            border: 1.5px dashed var(--pencil-line);
            border-radius: var(--r-2);
            padding: 16px;
            display: grid;
            gap: 12px;
            animation: arrive 0.28s var(--ease);
        }
        .slip.inked { border-style: solid; border-color: var(--ok); }
        .slip.rejected { border-style: solid; border-color: var(--rule-2); opacity: 0.78; }
        @keyframes arrive {
            from { opacity: 0; transform: scale(0.975); }
            to { opacity: 1; transform: scale(1); }
        }
        h1, h2 { font-size: 18px; font-weight: 600; letter-spacing: -0.02em; text-wrap: balance; }
        .sub { color: var(--ink-2); font-size: 14px; text-wrap: pretty; }
        .meta { color: var(--ink-3); font-size: 12px; display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
        .params-box {
            background: var(--well);
            border-radius: var(--r-1);
            padding: 10px 12px;
            font-family: var(--mono);
            font-size: 13px;
            color: var(--ink);
            overflow-wrap: anywhere;
        }
        .reason {
            padding-inline-start: 10px;
            border-inline-start: 2px dashed var(--pencil-line);
            font-size: 14px;
            color: var(--ink-2);
        }
        .reason small { display: block; font-size: 12px; color: var(--ink-3); }
        .checkline { font-size: 12px; color: var(--ink-2); display: flex; align-items: center; gap: 6px; }
        .checkline::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: var(--ok); flex-shrink: 0; }
        .btn-row { display: flex; flex-wrap: wrap; gap: 8px; }
        .btn {
            min-height: 44px;
            padding: 0 14px;
            border-radius: var(--r-1);
            border: 1px solid var(--rule-2);
            background: var(--sheet);
            color: var(--ink);
            font-family: inherit;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: background-color 0.12s;
        }
        .btn:active { background: var(--well); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .btn.primary { background: var(--brand); color: var(--brand-fg); border-color: transparent; flex: 1 1 auto; }
        .btn.danger { color: var(--stamp); border-color: color-mix(in oklch, var(--stamp) 45%, transparent); }
        input[type="text"], textarea {
            width: 100%;
            min-height: 44px;
            padding: 10px 12px;
            border-radius: var(--r-1);
            border: 1px solid var(--rule-2);
            background: var(--sheet);
            color: var(--ink);
            font-family: inherit;
            font-size: 16px;
        }
        textarea { min-height: 112px; resize: vertical; line-height: 1.5; }
        .code-input {
            font-family: var(--mono) !important;
            font-size: 22px !important;
            letter-spacing: 0.16em;
            text-transform: uppercase;
            text-align: center;
        }
        .edit-drawer { display: grid; gap: 8px; padding-top: 8px; border-top: 1px dashed var(--rule-2); }
        .edit-drawer textarea { min-height: 84px; font-family: var(--mono); font-size: 13px; }
        .chk { display: flex; align-items: center; gap: 8px; font-size: 13px; color: var(--ink-2); min-height: 36px; }
        .receipt-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: var(--r-1);
            background: var(--well);
            font-family: var(--mono);
            font-size: 13px;
            color: var(--ok);
            font-weight: 500;
        }
        .alert {
            padding: 12px;
            border-radius: var(--r-1);
            border: 1px solid color-mix(in oklch, var(--stamp) 45%, transparent);
            background: var(--stamp-wash);
            color: var(--stamp);
            font-size: 13px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .stats-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
        .stat-cell {
            background: var(--sheet);
            border: 1px solid var(--rule);
            border-radius: var(--r-2);
            padding: 12px;
            display: grid;
            gap: 2px;
        }
        .stat-val { font-size: 22px; font-weight: 600; font-variant-numeric: tabular-nums; }
        .stat-lbl { font-size: 12px; color: var(--ink-2); }
        .skeleton {
            height: 92px;
            border-radius: var(--r-2);
            background: linear-gradient(90deg, var(--well) 25%, var(--sheet) 50%, var(--well) 75%);
            background-size: 200% 100%;
            animation: pulse 1.2s infinite;
            border: 1px solid var(--rule);
        }
        @keyframes pulse { to { background-position: -200% 0; } }
        .tabbar {
            position: fixed;
            inset-inline: 0;
            bottom: 0;
            background: var(--sheet);
            border-top: 1px solid var(--rule);
            padding: 6px 12px calc(6px + env(safe-area-inset-bottom));
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 6px;
            max-width: 540px;
            margin: 0 auto;
        }
        .tab-btn {
            min-height: 44px;
            border: 0;
            border-radius: var(--r-1);
            background: transparent;
            color: var(--ink-2);
            font-family: inherit;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .tab-btn[aria-selected="true"] { background: var(--well); color: var(--ink); font-weight: 600; }
        .badge {
            padding: 1px 6px;
            border-radius: 99px;
            background: var(--pencil-wash);
            color: var(--pencil);
            font-size: 12px;
            font-variant-numeric: tabular-nums;
        }
        [hidden] { display: none !important; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0s !important; transition-duration: 0s !important; }
        }
    </style>
