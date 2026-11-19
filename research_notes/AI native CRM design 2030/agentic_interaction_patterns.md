# Agentic interaction patterns for AI acting on business records (2025-2026)

Method note: ~30 tool calls (searches + page fetches). Search tool returns model-written summaries; where I fetched the primary page it is noted. Items marked [secondary] come only from search summaries/third-party posts. Today = Nov 2026. Several Attio/Linear/Notion pages are dated 2026; Smashing/UXmatters Dec 2025-Feb 2026; Microsoft HAX (2019) and PAIR (2019, updated 2021) are OLDER.

## 1. Human-in-the-loop approval: how products gate agent writes

### Takeaway
The converging pattern is a graded autonomy ladder: reads free, drafts free, writes/sends/deletes gated, with a user- or admin-set dial and an "inbox/queue" for work awaiting approval. Products differ mainly in (a) whether approval is per action or per plan, (b) whether the gate is a model-judged "review for me" or a deterministic rule, and (c) whether approvals are pushed to the user (notification) or pulled (inbox).

### Cited Findings
- Attio Ask Attio: takes action on notes, tasks, records, emails; "will show you exactly what it's about to do and wait for your go-ahead"; available in app and Slack. Page does not describe the approval UI, undo, or permissions. (primary fetch) — [Attio changelog](https://attio.com/changelog/2026/ask-attio-to-take-action)
- Attio MCP: by default the connected AI assistant asks approval before running most Attio tools; write tools may be restricted in managed workspaces [secondary summary] — [Attio MCP guide](https://attio.com/help/reference/how-to-guides/attio-mcp-prompts-and-best-practices). Attio workflows: you describe a goal, Attio assembles the workflow "for you to approve" [secondary] — [Attio agents](https://attio.com/platform/agents)
- Attio Suggestions: "suggests your next move"; docs have subpages for "review and accept", edit, and per-user settings on what is suggested. Details of reject/provenance not retrieved — [Attio suggestions](https://attio.com/help/reference/attio-ai/suggestions)
- Notion Agent has three approval modes chosen at the top of the chat: "Always ask" (every change to content or connected app; reads free), "Review for me" (default; agent self-reviews and pauses only on actions it flags unsafe, e.g. deleting pages, sending email/Slack), "Skip all approvals". Docs warn Review-for-me "relies on the agent's review" and may not flag what you would. Admins can disable Skip-all. Settings are per workspace; a mode change does not undo prior actions — [Notion help](https://www.notion.com/help/choose-how-notion-agent-asks-for-your-approval)
- Notion Custom Agents: each has its own permissions "like a teammate"; read/write scoped across Notion, Slack, Mail, Calendar, MCP; page-level access; run activity logs; full audit log is Enterprise [secondary] — [Notion Custom Agents](https://www.notion.com/help/custom-agent)
- HubSpot Agent Inbox (More > Agents > Agent Inbox): filter by status (Needs approval, Success, Running, Failed, Waiting), agent, source, owner; each run shows trigger origin, user, estimated credits, output, expandable "thinking steps", and sources; user can refine output via chat box and the revision appears beneath the previous one. "Needs approval" = run waiting for tool approval; page does not describe approve/reject controls — [HubSpot KB](https://knowledge.hubspot.com/ai/review-agent-output). Approval requests go out as notifications to approve/reject [secondary] and a "CRM Tool Approval Controls" setting exists, often set once and never revisited [secondary consultant source] — [Rework](https://resources.rework.com/news/sales-tech/hubspot-breeze-revops-governance)
- HubSpot Customer Agent "audit cards" listing property changes, qualifications, handoffs, filterable by action type [third-party blog; not confirmed in HubSpot docs] — [Digital Applied](https://www.digitalapplied.com/blog/hubspot-breeze-ai-agent-workflows-2026-guide)
- Lightfield: "For Review" page aggregates drafted emails, record updates and new records for approval before they land [secondary; competitor-adjacent sources]; its own llms.txt describes event-triggered autonomous workflows, so what runs without approval is unclear — [Lightfield llms.txt](https://lightfield.app/llms.txt), [SaaStr demo write-up](https://www.saastr.com/watching-an-ai-native-crm-run-the-full-gtm-loop-live-lightfield-ceo-keith-peiris-demos-a-stalled-deal-an-automation-and-ten-new-prospects/), [Lightfield automations post](https://lightfield.app/blog/automations-in-open-beta-favorites-find-in-view)
- Superhuman: Auto Drafts are placed in the drafts folder, never auto-sent; editing a draft converts it to a regular draft that is no longer auto-updated. Auto Labels apply without approval, correctable per message, and can be switched off per label [secondary] — [Superhuman help](https://help.superhuman.com/hc/en-us/articles/46183279736461-Superhuman-Mail-for-Gmail), [TechCrunch](https://techcrunch.com/2025/02/19/superhuman-introduces-ai-powered-categorization-to-reduce-spammy-emails-in-your-inbox)
- Claude Code: manual mode asks before Bash, file edits, web fetch; read-only commands inside the working dir are free; "yes, don't ask again" scopes differ by tool (per-session for edits, permanent per repo+command for Bash). Rules evaluated deny > ask > allow, first match wins. In auto mode a classifier reviews actions instead of the user — [Claude Code permissions](https://code.claude.com/docs/en/permissions)
- Cursor: edits are written to disk immediately; "Keep" confirms, "Undo" reverts; floating bar for accept/reject per file; partial acceptance by rejecting lines. Forum reports show per-change Keep/Undo is inconsistent across versions and the Agents Window offers only file-level discard — [Cursor docs](https://docs.cursor.com/agent/review), [Cursor forum](https://forum.cursor.com/t/per-change-keep-undo-buttons-missing-after-agent-edits-only-undo-all-available/158983)
- GitHub Copilot coding agent: session logs show work and tools used; one PR per task; rulesets/branch protection can block the agent; read-only without write access; docs say logs "do not replace your own review" — [GitHub docs](https://docs.github.com/en/copilot/concepts/agents/coding-agent/about-coding-agent)
- Salesforce Agentforce: guardrails configurable per agent type; handoff-to-human rules written in topic instructions; suggested oversight such as copying a sales manager on AI-generated emails — [Trailhead](https://trailhead.salesforce.com/content/learn/modules/trusted-agentic-ai/explore-agentforce-guardrails-and-trust-patterns)
- Shape of AI "Verification" pattern: require confirmation scaled to harm, scope, reversibility; allow repeated actions to skip after an initial check; offer "never show again" but make skipped verification visible (example: Cofounder shows red alert when "Always Ask" is turned off); alert users out-of-band (Slack/email/SMS) so runs don't stall; verify costs too. Named examples: Notion, Dovetail (approve suggested highlights before they enter report), Replit (approve plan before build), ChatGPT operator mode (hands over at payment) — [Shape of AI Verification](https://www.shapeof.ai/patterns/verification)
- Clay: Claygent Builder test runs on sample rows reportedly cost no credits; "only run if" conditions and row limits gate cost; community reports of runaway credit use (one user ~20k credits). Not an approval-of-records gate; closest analogue is run-on-sample-then-scale [secondary/community] — [Clay blog](https://www.clay.com/blog/agent-use-cases-gtm), [Clay community](https://community.clay.com/x/general/msg_h33b2Xft5xIe/how-to-save-claygent-credits-by-pre-filtering-data)

### Inferences
- Notion's "Review for me" default is a model-judged gate; Notion itself warns it may miss things. For a CRM, pair any model-judged gate with deterministic rules on field/object class (e.g. always gate deletes, owner changes, outbound email, stage moves to closed).
- HubSpot and Lightfield converge on an inbox-of-proposals as the approval surface (pull model), Notion/Claude Code on inline prompts in the conversation (push model). The inbox suits batch review; inline suits a conversation the user is watching.

### Gaps
- Attio approval UI, undo, permission scoping: docs fetched did not specify. Folk, Day.ai, Height, Raycast AI: no primary sources on approval/gating found (Day.ai sources were thin and partly competitor marketing). Linear "triage intelligence" not covered by pages fetched. Height was not researched (and I could not confirm its current status).

## 2. Research on trust calibration, over-reliance, approval fatigue, reversibility

### Takeaway
Frameworks agree: scale friction to risk and reversibility, show reasoning/confidence so users calibrate scrutiny, keep correction cheap, and avoid blanket prompts. Evidence is mostly guideline/practitioner, not controlled studies; metric thresholds in Smashing are illustrative.

### Cited Findings
- Smashing (Yocco, Feb 2026) names six patterns across pre/in/post-action: Intent Preview, Autonomy Dial, Explainable Rationale, Confidence Signal, Action Audit & Undo, Escalation Pathway. Intent Preview is "non-negotiable" for irreversible, financial or data-sharing actions; suggested metrics: plan acceptance without edits >85%, "handle it myself" override >10% triggers model review, reversion rate >5% for a task means disable automation for it, escalation 5-15% healthy; Autonomy Dial has four levels (Observe & Suggest, Plan & Propose, Act with Confirmation, Act Autonomously) per task type; show when an undo window closes (e.g. "Undo available for 15 minutes"). Article does not directly address approval fatigue (my reading of fetched page); thresholds are labeled representative — [Smashing](https://smashingmagazine.com/2026/02/designing-agentic-ai-practical-ux-patterns/)
- Smashing Confidence Signal: confidence percentage plus scope statement plus visual cue; proposes "calibration score" (confidence vs acceptance correlation >0.8) and "scrutiny delta" (users should spend more review time on low-confidence plans) — same source.
- Shape of AI: Action plan can be "advisory" or "contractual" (must approve); show before resources are spent; let user modify/regenerate; explain deviations during execution — [Shape of AI Action plan](https://shapeof.ai/patterns/action-plan) (via search summary). Governors category lists 13 patterns including Action plan, Stream of Thought, Controls, Cost estimates, Sample response, Shared vision, Verification; no pattern named "provenance" or "undo" — [Shape of AI governors](https://www.shapeof.ai/pattern-types/governors)
- Microsoft HAX (2019, OLDER): 18 guidelines incl. G2 "Make clear how well the system can do what it can do", G9 "Support efficient correction", G10 "Scope services when in doubt", G11 "Make clear why the system did what it did", G14 "Update and adapt cautiously", G16 "Convey the consequences of user actions", G17 "Provide global controls", G18 "Notify users about changes" — [HAX library](https://www.microsoft.com/en-us/haxtoolkit/library/)
- Google PAIR Errors chapter (OLDER, ~2019/2021): scale safeguards to stakes; act proactively only with high confidence; interactions with errors calibrate trust; let users take over and correct; PAIR's excerpt fetched did not address undo — [PAIR Errors + Graceful Failure](https://pair.withgoogle.com/chapter/errors-failing/)
- Anthropic "Building effective agents" (Dec 2024, OLDER but canonical): agents can "pause for human feedback at checkpoints or when encountering blockers"; show planning steps for transparency; set stopping conditions (max iterations); sandbox and guardrails because errors compound; design tools (agent-computer interface) with the care of HCI, "poka-yoke" arguments — [Anthropic](https://www.anthropic.com/engineering/building-effective-agents)
- OpenAI "A practical guide to building agents" (Apr 2025): human intervention on failure thresholds and high-risk actions, tool risk ratings, layered guardrails [only secondary summaries read; primary PDF not read] — [Maginative](https://www.maginative.com/article/how-to-build-ai-agents-a-detailed-practical-guide-from-openai/), [OpenAI PDF](https://cdn.openai.com/business-guides-and-resources/a-practical-guide-to-building-agents.pdf)
- NN/g: search returned NN/g 2026 definition of an AI agent (iteratively acts, evaluates progress, chooses next steps) and a quote that review/correction effort erodes AI benefit; all via third-party blogs, not nngroup.com. A "40% higher retention for explainable interfaces" figure appears in a blog attributed to NN/g and is UNVERIFIED — do not cite — [ParallelHQ](https://www.parallelhq.com/blog/ai-agent-ux-design)
- Notion help states hard-to-reverse actions (replace page content, post in connected app) run unchecked under Skip-all, and that undo path depends on where the mistake happened (ask agent, page history, or fix in the connected app) — [Notion help](https://www.notion.com/help/choose-how-notion-agent-asks-for-your-approval)

### Inferences
- For a CRM, "reversible" should be classified per field/object: internal field edits reversible via history; outbound email, external sync (e.g. to billing), merges, and deletes are not. Gate on that classification, not on agent confidence alone.
- Using reversion rate and plan-acceptance rate per task type as telemetry to move tasks up/down the autonomy dial is directly implementable.

### Gaps
- No primary NN/g articles retrieved; no controlled-study evidence on approval fatigue or automation bias for agent UIs found. Smashing says nothing explicit about fatigue (my inference only). HAX guideline text beyond titles not fetched.

## 3. Showing plan, diff/preview, batching, streaming progress

### Takeaway
Best-documented patterns: a plain-language numbered plan with Proceed / Edit / Do-it-myself; field-level before/after diffs; a review queue that aggregates many proposals; and a typed activity stream (thought, tool action, question, result) with explicit session states.

### Cited Findings
- Intent Preview UI: numbered steps in user language ("Cancel flight UA456", not API calls) with three choices: Proceed, Edit Plan, Handle it Myself — [Smashing](https://smashingmagazine.com/2026/02/designing-agentic-ai-practical-ux-patterns/)
- Linear Agent Interaction: five agent-emitted activity types (thought, elicitation, action, response, error); `action` carries a tool call and optional result; users' replies are `prompt` activities and agents cannot fabricate those; only thought/action can be ephemeral; session states pending, active, error, awaitingInput, complete, stale are visible to users; "Plans" (technology preview) = checklist of steps with pending/inProgress/completed/canceled where updates replace the whole array; agent must acknowledge a new session within 10 seconds; PR URL can be attached to session as external link — [Linear agent interaction](https://linear.app/developers/agent-interaction)
- Linear delegation: assigning an agent sets it as delegate, human stays assignee/responsible [secondary search summary]; delegated issues stay in the user's My issues; Views/Insights filterable by Delegate — [Linear AI agents docs](https://linear.app/docs/agents-in-linear.md), [Linear developer docs](https://linear.app/developers/agents.md)
- HubSpot Agent Inbox: expandable "thinking steps" and sources per run; refine in chat and new output stacks under the old — [HubSpot KB](https://knowledge.hubspot.com/ai/review-agent-output)
- Cursor: floating accept/reject bar, per-file navigation across pending changes, "Review changes" shows full diff after an agent turn — [Cursor docs](https://docs.cursor.com/agent/review)
- Shape of AI Stream of Thought / Shared vision / Controls: reveal reasoning and tool use; live visibility in a shared workspace; pause mid-stream — [governors list](https://www.shapeof.ai/pattern-types/governors)
- Salesforce session tracing records each turn, message, LLM call, action invocation and guardrail check; exportable through an OTel API (beta, single session) — [Salesforce dev docs](https://developer.salesforce.com/docs/ai/agentforce/guide/otel-api.html), [Salesforce news](https://www.salesforce.com/news/stories/agentforce-studio-observability-tools-announcement/)

### Inferences
- A CRM change-set proposal needs: grouped by record, field-level old/new, source evidence per field (email/call snippet), per-field accept/reject plus "accept all from this source", and a visible "affects N other records" count for bulk actions.
- Batching: approve-by-class (e.g. "all enrichment fills where field was empty") is lower risk than approve-all; overwrites of non-empty human-entered values should not batch silently.

### Gaps
- No product found that publishes a field-level diff UI spec for CRM records (Attio/Lightfield/Day.ai UIs not retrieved in detail). Folk and Clay approval previews not found. Visual details of Lightfield's For Review page are unverified.

## 4. Action receipts, provenance, audit trail, undo/rollback, permission scoping

### Takeaway
Receipts and provenance are mostly implemented as logs/timelines plus attribution to a distinct agent identity; the strongest concrete examples are Granola's gray/black text ownership, Linear's delegate model, GitHub's PR-as-receipt, and Notion/Salesforce audit logs. Undo is typically via page/version history, not true per-action rollback.

### Cited Findings
- Granola: user-typed notes show black, AI-added text gray; editing gray text turns it black (ownership transfer); AI bullets link to the transcript moment (secondary summary of help docs) — [Granola help](https://www.granola.ai/docs/docs/101/afteryourmeeting/editing-your-enhanced-notes), [Granola AI-enhanced notes](https://help.granola.ai/article/ai-enhanced-notes)
- Superhuman: editing an Auto Draft changes its status to a regular draft (provenance ends on human edit) — [Superhuman help](https://help.superhuman.com/hc/en-us/articles/46183279736461-Superhuman-Mail-for-Gmail)
- Linear: agents appear in assignee menu as app users with their own user pages showing activity; name collisions get a number suffix (Charlie1); not billable seats; cannot sign in, access admin or manage users; admins choose installed agents and which teams they can access (Settings > AI & Agents); workspace and team-level agent guidance — [Linear agents docs](https://linear.app/docs/agents-in-linear.md)
- GitHub Copilot agent: PR is the deliverable and review surface; session logs show tools used; branch protection/rulesets constrain the agent; read-only without write access — [GitHub docs](https://docs.github.com/en/copilot/concepts/agents/coding-agent/about-coding-agent)
- Notion: run-level activity logs; configuration changes in audit log (Enterprise for full visibility); undo = ask agent, page history, or fix in connected app — [Notion Custom Agents](https://www.notion.com/help/custom-agent), [Notion approvals help](https://www.notion.com/help/choose-how-notion-agent-asks-for-your-approval)
- Salesforce Einstein Trust Layer Audit Trail: shows what the agent did, why, and outcomes — [Trailhead](https://trailhead.salesforce.com/content/learn/modules/trusted-agentic-ai/explore-agentforce-guardrails-and-trust-patterns); session tracing enabled in Setup, no backfill [secondary] — [Salesforce Break](https://salesforcebreak.com/2026/05/28/setting-up-agentforce-observability/)
- Smashing Action Audit & Undo: chronological timeline with status (successful, in progress, undone), undo windows that expire, reversion rate as a kill-switch metric; Explainable Rationale puts "why I did this" next to an Undo button — [Smashing](https://smashingmagazine.com/2026/02/designing-agentic-ai-practical-ux-patterns/)
- Claude Code deny/ask/allow rules checked into version control and shareable across a team, with managed policies — [Claude Code permissions](https://code.claude.com/docs/en/permissions)
- Cursor: changes applied to disk immediately, Keep/Undo; checkpoints are local, separate from Git, revert files only [third-party sources conflict on scope] — [Cursor forum](https://forum.cursor.com/t/why-does-cursor-apply-diffs-immediately-before-the-user-accepts-them/145070), [Checkpoints overview](https://flaviocopes.com/ai-glossary/checkpoints/)
- HAX G11 "Make clear why the system did what it did"; G18 "Notify users about changes" — [HAX library](https://www.microsoft.com/en-us/haxtoolkit/library/)

### Inferences
- Provenance without sparkle icons: use (1) ownership via text weight/colour that changes on human edit (Granola), (2) a distinct non-human actor identity in timelines and assignee/owner fields (Linear's app user), (3) a source link to the evidence (transcript moment, email), (4) a "delegated by <human>" line so accountability stays human.
- Per-field provenance record to store: actor (human/agent identity + on-whose-behalf), run id, evidence refs, prior value, timestamp, approval state (auto/approved by X/unreviewed). This enables undo by run and an "unreviewed AI changes" filter.
- True rollback for external side-effects is not offered by any product I found; Notion explicitly says fix in the connected app.

### Gaps
- Attio, Folk, Day.ai, Lightfield: no primary documentation found on provenance markers or undo. Salesforce/HubSpot field-level "changed by agent" display in record timelines not confirmed. No source found for Height (not verified as a still-active product).

## 5. Where the agent lives in the UI

### Takeaway
Products distribute the agent across several surfaces: conversational panel/command, chat in external tools (Slack), assignable teammate, review inbox, and background/event-triggered workflows. The inbox plus assignable-teammate combination covers async work best; chat covers ad-hoc.

### Cited Findings
- Attio: Ask Attio in-app and in Slack; Suggestions surface "next move" proactively; Workflows for background automation — [Attio changelog](https://attio.com/changelog/2026/ask-attio-to-take-action), [Attio suggestions](https://attio.com/help/reference/attio-ai/suggestions), [Attio workflows](https://attio.com/changelog/2026/the-new-workflows)
- Linear: agent is an assignee/delegate or @mention in comments, with a session UI showing activity; "Linear Agent works by default in every workspace" per docs — [Linear agents docs](https://linear.app/docs/agents-in-linear.md)
- HubSpot: Agent Inbox as central review surface, runs triggered from Breeze Studio or automations — [HubSpot KB](https://knowledge.hubspot.com/ai/review-agent-output)
- Lightfield: "For Review" queue; automations on CRM events [secondary] — [Lightfield llms.txt](https://lightfield.app/llms.txt)
- Notion: chat-level approval selector plus Custom Agents running autonomously per trigger — [Notion help](https://www.notion.com/help/choose-how-notion-agent-asks-for-your-approval)
- Superhuman: ambient (auto labels, auto drafts appear in the existing inbox/drafts) rather than a chat panel — [Superhuman help](https://help.superhuman.com/hc/en-us/articles/46183279736461-Superhuman-Mail-for-Gmail)
- Day.ai described as a "talk to it" conversational CRM that captures meetings/email automatically; sources thin and partly competitor marketing; Lightfield's competitor blog argues Day's interaction model adds overhead vs a background agent — [Folk review of Day](https://www.folk.app/articles/day-ai-review), [Lightfield on Day](https://lightfield.app/blog/day-ai-review) (treat as biased)
- Claude Code: background sessions in an "agent view" and Remote Control sessions that stall when login expires (illustrates ambient-agent failure mode: silent stall) — [Claude Code auth docs](https://code.claude.com/docs/en/iam)
- Shape of AI: alert users out-of-band (Slack/email/SMS) when input is needed so async runs don't stall — [Shape of AI Verification](https://www.shapeof.ai/patterns/verification)

### Inferences
- Recommended mapping for a CRM: command bar/side panel for synchronous asks; inline suggestion chips on records for context-bound proposals; a single "Review" inbox for async/batch proposals; ambient background for low-risk reversible enrichment with a digest receipt.

### Gaps
- No source comparing effectiveness of these surfaces. Raycast AI, Folk, Clay UI placement not researched with primary sources. Linear triage intelligence docs not fetched.

## Source reliability notes
- Primary pages fetched: Smashing, Shape of AI (verification, governors), Attio changelog, Linear developer agent-interaction, Linear agents doc, HubSpot KB, Notion help, Claude Code permissions, Anthropic engineering, GitHub docs, Microsoft HAX library (titles only), PAIR errors chapter.
- Search-summary only: Lightfield, Day.ai, Granola, Superhuman, Clay, Salesforce observability, OpenAI guide, NN/g, Cursor checkpoints, Attio MCP/workflows.
- Vendor-competitor sources (Lightfield blog on Day, Coffee, BuildBetter, Folk on Day) are biased.
