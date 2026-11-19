---
name: Luminara — Drafting table
version: 0.1 (proposal, 15 Nov 2026)
live_reference: docs/prototype/agent-desk.html
research: reports/AI native CRM design 2030.md
colors:
  # OKLCH, light / dark. Ink hue 190 (brand teal), pencil hue 250 (non-photo blue), stamp hue 30 (vermilion).
  paper:        { light: "oklch(0.985 0.004 190)", dark: "oklch(0.165 0.012 190)" }
  sheet:        { light: "oklch(1 0 0)",           dark: "oklch(0.195 0.013 190)" }
  well:         { light: "oklch(0.965 0.006 190)", dark: "oklch(0.225 0.014 190)" }
  rule:         { light: "oklch(0.25 0.03 190 / 0.12)", dark: "oklch(1 0 0 / 0.10)" }
  ink:          { light: "oklch(0.22 0.025 190)", dark: "oklch(0.94 0.008 190)" }   # 16.5:1 / 15.8:1
  ink-2:        { light: "oklch(0.45 0.02 190)",  dark: "oklch(0.76 0.012 190)" }   # 7.1:1 / 7.9:1
  ink-3:        { light: "oklch(0.52 0.016 190)", dark: "oklch(0.66 0.012 190)" }   # floor for text, ≥4.5:1
  brand:        { light: "oklch(0.45 0.085 186)", dark: "oklch(0.78 0.10 186)" }    # = #0F766E family; primary action only
  pencil:       { light: "oklch(0.47 0.10 250)",  dark: "oklch(0.78 0.09 245)" }    # agent, proposed, unreviewed
  pencil-wash:  { light: "oklch(0.95 0.025 245)", dark: "oklch(0.27 0.04 250)" }
  stamp:        { light: "oklch(0.52 0.19 30)",   dark: "oklch(0.74 0.14 32)" }     # irreversible only
  ok:           { light: "oklch(0.48 0.11 155)",  dark: "oklch(0.78 0.12 155)" }
typography:
  sans: "IBM Plex Sans (+ IBM Plex Sans Arabic / JP / KR for ar, fa, ja, ko)"
  mono: "IBM Plex Mono — call ids, action names, timestamps on receipts only"
  scale: { xs: 13px, sm: 14px, md: 16px, lg: 18px, xl: 24px, 2xl: "24→32px fluid" }
  weights: [400, 500, 600]
  numbers: tabular-nums wherever digits align or change
spacing: 4px base (4, 8, 12, 16, 20, 24, 32, 40)
radii: { control: 4px, object: 10px }          # concentric: object = control + 6px
motion:
  ease_out: "cubic-bezier(0.22, 1, 0.36, 1)"
  inking: 500ms clip-path wipe, once per signature
  arrive: 360ms scale(.975)+opacity, new proposal only
  repeated_actions: ≤120ms (hover, focus, buttons)
  reduced_motion: all instant, same end states
---

# Luminara design: the agent drafts in pencil, people sign in ink

## Why this direction

The old UI was stock Krayin, and the reference shots you shared are the 2025 Dribbble look: near-black glass, neon donuts, sparkle-and-orb AI. The research ([report](../reports/AI%20native%20CRM%20design%202030.md)) shows why that look dates. NN/g found nobody links the sparkle icon to AI. Linear's 2026 refresh went the other way, with the line "don't compete for attention you haven't earned". Analysts expect the job to shift from operating a CRM to supervising the work an agent does in it. So the screen has to answer one question: **what did the agent do, and what is waiting for me?**

The answer comes from two old trades that already have a visual language for "proposed, not final":

- **Technical drawing**: dashed = proposed, solid = built, hatched = to be removed.
- **Copy-editing**: editors' non-photo blue pencil marks notes that never reach the final print.

| Agent state | Drawn as |
|---|---|
| Proposed / waiting for a signature | dashed pencil-blue outline, pencil value with a dashed underline |
| Created by the agent, not yet reviewed | pencil value in a solid card ("unreviewed") |
| Signed / done | the outline inks to solid, values turn ink |
| Irreversible (delete) | stamp vermilion, struck through, two-step confirm |
| Rejected | outline goes plain, values struck in grey |

Colour is never the only signal (WCAG 1.4.1): every state also changes line style and has a text label.

## Rules

1. **One accent job each.** Brand teal is only the primary action (Sign and run, Send). Pencil blue is only the agent and unsigned work. Vermilion is only irreversible actions. Nothing is decorative.
2. **No AI iconography.** No sparkle, orb or glow. The agent is named "Agent" in pencil type, and its panel states it is an AI model and which one (EU AI Act Art. 50(1), at first interaction).
3. **Friction scales with harm.** Reads run silently. Creates run and stay pencil until reviewed. Multi-lead moves wait for a signature. Deletes wait for a signature plus a second confirm. This matches `AgentAction::approval()` exactly, so the UI never claims a gate the server doesn't enforce.
4. **The reason is labelled as a claim.** "The agent's reason (its own words, not checked)", which is what `AgentActionCall::$reason` is.
5. **Show the re-check.** Each slip shows "Records unchanged since the request", backed by the `subject` snapshot the server compares before running.
6. **Every call is a receipt.** The ledger lists call id, time, action, what and state for every call, including reads.
7. **Stop is always there.** While the agent works, Send becomes Stop (Art. 14-style oversight).
8. **Charts are drawn to scale** and say what a signature would change, in pencil.
9. **Density over decoration.** Tables, hairlines, 14px data, 16px reading text, no shadows on cards. Hide nothing behind hover.

## Components (see the prototype)

- **Proposal slip**: meta line (call #, time, closes in), title in plain words, the diff table (was → proposed), the reason, the checks, then the actions.
- **Agent ledger**: a receipt table, newest first, states in words.
- **Agent column**: disclosure header, transcript (speaker labels, no bubbles), numbered steps with call ids, composer with Enter to send and Stop.
- **Pipeline bar**: open stages on one bar drawn to scale, pending moves hatched in pencil, won/lost beside it.

## Built so far

Agent Approvals and the Assistant use this system (`packages/Webkul/Agent/src/Resources/views`, tokens in `partials/styles.blade.php`, scoped to `.lum-agent` and following the admin's `.dark` class). They keep the admin's Inter for now. Switching to IBM Plex only makes sense for the whole admin at once.

**The Desk (v2, "Pipeline you can hold")** is built from `docs/prototype/v2`. The files are in `views/desk/`:
- `index` holds the markup and the Vue mount.
- `scene` is the three.js model.
- `app` holds the flows.
- `styles` holds the CSS, scoped to `.lum-desk`.

Data comes from `Support/DeskData`.

How the Desk differs from the prototype:
- **Real data:** it uses the default pipeline and real waiting calls. Decisions go through `ApprovalController`, and moves go through Krayin's `admin.leads.stage.update`. After every change the page reloads its data from `admin.agent.desk.data`.
- **Stop, "unreviewed" & Edit-to-Sign:** delivered in Phase 5 (see below). The Ask sheet calls the real assistant.
- **Krayin class names:** Krayin already styles `.stage`, so the model container is `.model-stage`. Check new class names against the admin build before adding them.
- **three.js:** it loads by exact URL rather than an import map, because the admin layout loads a module script before any page can add one. Vendor it if a Content-Security-Policy is added or the CRM must work offline.

## Built in Phase 5 (Verifiable Ledger, Simple Mode Briefing, Edit-to-Sign & TMA)

- **Assistant server-side `Stop`**: `POST /admin/agent/assistant/stop` halts the server-side tool loop cleanly between turns while preserving valid `tool_result` transcripts.
- **"Unreviewed" state & **Mark reviewed****: Agent-created records carry `reviewed_at` on `agent_action_calls` and transition from pencil to ink via `POST /admin/agent/approvals/{id}/review` (or `POST /admin/agent/briefing/{id}/review`).
- **Edit-to-Sign & Playbook Memory**: Approvers can edit proposed parameters inline before signing (**Sign with edits**) and optionally check **Remember as a playbook rule** to update `agent_playbook_blocks`.
- **Owner Simple Mode (`/admin/agent/briefing`)**: Asymmetric `7+5` Bento Drafting Table (`packages/Webkul/Agent/src/Resources/views/partials/styles.blade.php`) with one-line intake capture, Today's Quests, unreviewed pencil drafts, Agent Passports, Playbook Memory editor, and Data Vault chain head.
- **Verified Receipts (`Webkul\Ledger`) & Telegram Mini App (`Webkul\Tma`)**: Every settled call and CRM mutation mints a `LUM-<ULID>` cryptographic receipt (`/receipts/{receiptCode}`), and `/tma` brings the same `.lum-tma` OKLCH Drafting Table tokens (`packages/Webkul/Tma/src/Resources/views/partials/styles.blade.php` — `paper`, `sheet`, `ink`, `brand`, `pencil`, `stamp`, `ok`) to mobile Telegram with WebAuthn biometric Passkey signing.

## The Workbench shell (v3 prototype, 19 Nov 2026)

`docs/prototype/v3/index.html` is the reference for the rest of the admin. It uses sample data modelled on the Gourwest demo.

**Built into `packages/Webkul/Admin`:**
- Neutrals: the Tailwind `gray` ramp is remapped to hue 190 in `tailwind.config.js`, so every admin view follows. Tokens for pencil, hairline and focus are `--lum-*` in `assets/css/app.css`.
- Type: IBM Plex Sans and Mono, loaded in `components/layouts/index.blade.php`.
- Shell: `components/layouts/sidebar/desktop` (grouped, full height, collapsible) and `components/layouts/header`. The group an item lands in is decided by its menu key at the top of the sidebar view.
- Leads board: `leads/index/kanban.blade.php`, with the open pipeline drawn to scale.

**Not built yet:** the agent's proposal slip on the board and the "Waiting for you" and agent-call widgets (they need data from `Webkul\Agent`), the Dashboard layout, lead counts per stage in the sidebar, and the mobile drawer restyle.

- **Structure** comes from the tasks-board reference the team chose: a grouped sidebar with live counts per stage, a widget strip above the work, Board / Table tabs, and a four-column lead board. Light and dark are both designed.
- **Meaning** is unchanged from the rules above: teal is the primary action, pencil blue is the agent and unsigned work, charts are drawn to scale and hatch what a signature would change.
- **Signature move:** the board's dashed drop slot is the agent's proposal. A proposed move sits as a dashed pencil slip in the destination column, with the reason and the re-check, until a person signs or rejects it.
- **Token changes against the front matter:** radii move to 6px control / 12px object, and `paper` becomes the sidebar and column ground with `sheet` as the main surface. Colours and type are the same.

## Brand note

The current logo puts an AI sparkle on the hexagon. The prototype keeps the hexagon and "L" without the sparkle. Changing the logo is a brand decision for the team.
