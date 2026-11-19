# Emerging 2026-2030 UI directions for business software with built-in AI agents

Research date: 2026-11-15. Labels: FACT = observed/published; FORECAST = prediction by a named source; INFERENCE = my reasoning. Many search snippets came via secondary summaries; those are flagged. Source-quality note: NN/g Liquid Glass critique is expert review of screenshots, not user testing.

## 1. What did Liquid Glass introduce, how was it received (NN/g, accessibility), and what does it imply for web B2B UIs?

### Takeaway
Liquid Glass (WWDC June 2025, shipped in iOS/iPadOS/macOS 26) is a translucent, refractive control layer floating over content. NN/g and others criticised legibility, motion, tap-target and navigation-stability regressions; Apple responded within about four months with a user-facing opacity toggle (iOS 26.1). Lesson for B2B web: translucency is optional decoration; legibility, stable navigation and user-controlled contrast are the durable parts.

### Cited Findings
- FACT: Apple describes Liquid Glass as a translucent material that "reflects and refracts its surroundings", adapts colour to nearby content, and reacts to motion with specular highlights. It applies to iOS 26, iPadOS 26, macOS Tahoe 26, watchOS 26, tvOS 26. Controls float as a layer above content; tab bars shrink on scroll and expand on scroll back; macOS Tahoe gets a fully transparent menu bar; users can choose light/dark/tinted/clear icon looks. — [Apple Newsroom](https://www.apple.com/newsroom/2025/06/apple-introduces-a-delightful-and-elegant-new-software-design/)
- FACT (expert critique): NN/G (Raluca Budiu, 10 Oct 2025) says translucent controls over busy backgrounds are hard or impossible to read (text on images in Messages, text on text in Mail); "Motion for motion's sake is not usability"; tap-target spacing fell below the long-standing ~1 cm guideline; navigation is unpredictable (Safari forward button appears/disappears; tab bar collapses during search); conventions changed (search moved to bottom, tabs into overflow, back buttons lost breadcrumb labels); floating search bars blend into the background. — [NN/g: Liquid Glass Is Cracked](https://www.nngroup.com/articles/liquid-glass/)
- NN/g's accessibility points: low contrast hurts low-vision users, unnecessary animation can distract and cause nausea, cramped targets hurt users with motor impairments, shifting controls raise cognitive load. The article does not evaluate Apple's accessibility settings. — [NN/g](https://www.nngroup.com/articles/liquid-glass/)
- Secondary report: Fast Company quotes Jakob Nielsen saying low-vision users are likely hurt most; Apple's stated position was that people can enable Reduced Transparency, Increased Contrast, Reduced Motion; a UX Collective piece called these "optional patches, not embedded design foundations". (Via search summary; primary articles not opened.) — [anderegg.ca summary of NN/g reaction](https://anderegg.ca/2025/10/12/nielsen-norman-group-on-ios-26-usability); [UX Collective](https://uxdesign.cc/liquid-glass-is-beautiful-but-is-the-chemistry-missing-00b4f79ff529); [MobileSyrup](https://mobilesyrup.com/2025/06/10/how-apple-is-making-sure-liquid-glass-as-legible-as-possible/)
- FACT: iOS 26.1 (beta 4, Oct 2025) added Settings > Display & Brightness > Liquid Glass with Clear (default) or Tinted (more opaque, more contrast), system-wide. — [TechCrunch](https://techcrunch.com/2025/10/20/apple-will-let-users-roll-back-the-liquid-glass-look-with-new-tinted-option); [Tom's Guide](https://www.tomsguide.com/phones/iphones/ios-26-1-lets-you-adjust-liquid-glass-transparency-on-your-iphone-heres-how-to-do-it); [Engadget](https://www.engadget.com/mobile/smartphones/how-to-adjust-the-liquid-glass-effect-in-ios-261-203634681.html)
- NN/g glassmorphism guidance for web: check contrast against sampled complex backgrounds; use more blur on busy backgrounds; prefer simple backgrounds you control; let users reduce transparency (solid fallback) or otherwise meet WCAG; use sparingly for depth. — [NN/g Glassmorphism](https://www.nngroup.com/articles/glassmorphism/)
- FACT (spatial): visionOS 26 added persistent widgets, shared in-room experiences, enterprise APIs, more realistic Personas; Apple says companies use Vision Pro for design, training, sales, education. Apple does not publish sales; one analyst estimate is under 1M cumulative units (unverified). Enterprise use reported stronger than consumer (training/simulation). — [BusinessWire/Apple visionOS 26](https://www.businesswire.com/news/home/20250609148562/en/visionOS-26-introduces-powerful-new-spatial-experiences-for-Apple-Vision-Pro); [MacRumors](https://www.macrumors.com/2025/09/15/apple-releases-visionos-26/); [iClarified report](https://www.iclarified.com/98356/apple-vision-pro-gaining-traction-in-business-despite-slow-consumer-sales-report)

### Inferences
- INFERENCE: For data-dense B2B web UI (tables, forms), the transferable parts are a user-controlled contrast/transparency setting and no text over variable backgrounds; the glass effect itself is likely to date (fad), as NN/g's earlier iOS 7 flat-design critique pattern suggests (iOS 7 comparison is from a search summary, not verified).
- INFERENCE: The fast Apple retreat (toggle in ~4 months) is evidence that legibility controls are the durable layer. Spatial UI for CRM is a niche (training/visualisation) through 2030, not a primary surface.

### Gaps
- No primary Apple HIG text on Liquid Glass accessibility opened; no quantitative user-testing data on Liquid Glass found; NN/g's reported statistics via wecandevelopit.com were unverifiable and excluded. No reliable Vision Pro sales numbers.

## 2. What is generative UI as of 2026, and what are its usability risks?

### Takeaway
Generative UI is now productised in three forms: model-authored full interfaces (Google), component-selection via tool calls (Vercel AI SDK), and sandboxed interactive widgets returned by tools (MCP Apps, standardised Jan 2026). NN/g's core risk is inconsistency harming learnability; the pragmatic pattern is constrained generation inside a stable shell.

### Cited Findings
- FACT: Google Research "Generative UI" (Nov 18 2025): Gemini 3 Pro with tools, system instructions and post-processing outputs HTML/CSS/JS; live as "dynamic view"/"visual layout" in Gemini app and in Search AI Mode (AI Pro/Ultra US at the time). Human-expert-designed sites were preferred most, generative UI "closely" behind and well ahead of other formats; evaluation ignored generation speed; generation can take a minute or more; occasional inaccuracies. — [Google Research blog](https://research.google/blog/generative-ui-a-rich-custom-visual-interactive-user-experience-for-any-prompt/) (via search summary; paper not opened)
- FACT: MCP Apps became the first official MCP extension (26 Jan 2026): tools carry `_meta.ui.resourceUri` pointing to a `ui://` HTML/JS resource, rendered in a sandboxed iframe, JSON-RPC over postMessage; hosts can review HTML, audit logged messages and require approval for UI-initiated tool calls. Clients: Claude web/desktop, Goose, VS Code Insiders, ChatGPT. Rationale: text responses make sort/filter/drill-down slow; UI gives live updates and direct manipulation. Builds on MCP-UI and OpenAI Apps SDK. — [MCP blog](https://blog.modelcontextprotocol.io/posts/2026-01-26-mcp-apps/); [The New Stack](https://thenewstack.io/anthropic-extends-mcp-with-an-app-framework/)
- FACT: Vercel AI SDK's RSC `streamUI` path is labelled experimental, with docs recommending AI SDK UI (`streamText` + `useChat`, client-rendered components) for production; known RSC issues: remounting/flicker, no parallel or multi-step tool calls; RSC development paused. — [AI SDK migration guide](https://ai-sdk.dev/docs/ai-sdk-rsc/migrating-to-ui) (via search summary)
- FACT (NN/g on generative UI): "Constantly changing UIs will cause usability problems"; users speed up on frequently used sites so constant relearning frustrates; understanding depends on conventions; hallucination/bias inherited; compute cost; privacy/security risk from deep personalisation; upside is accessibility (e.g. auto-adjusted font/contrast for dyslexia). NN/g separately notes buttons/checkboxes/forms still clarify intent and reduce friction. — [NN/g Generative UI](https://www.nngroup.com/articles/generative-ui/); [NN/g GenUI buttons and checkboxes](https://nngroup.com/articles/genui-buttons-and-checkboxes)
- Weaker sources (opinion/vendor): stale data in dynamic UIs misleads unless timestamped; need dismiss/reset. — [MetaCTO](https://www.metacto.com/blogs/generative-ui-production-guide); Punchcut: freedom to the model is not the most usable option — [Punchcut](https://punchcut.com/perspectives/generative-ui/)
- Research: arXiv "Generative Interfaces for Language Models" flags accessibility barriers and risk of unintended persuasion in high-stakes contexts (via summary). — [arXiv 2508.19227](https://arxiv.org/pdf/2508.19227)

### Inferences
- INFERENCE: Durable form for a CRM: generate within a fixed component library and stable shell (record pages, nav, action bars deterministic; ephemeral panels for one-off analyses/confirmations). MCP Apps-style sandboxing also gives an audit trail, matching provenance needs.
- INFERENCE: Fully free-form per-user layout of core workflows is likely a fad for business software through 2030 given NN/g's learnability argument and the minute-long latency.

### Gaps
- No longitudinal usability study of generative UI in B2B tools found. Google paper's full results not read. Vercel's current (AI SDK 6/7) status unconfirmed.

## 3. What do forecasters say about interfaces as agents do more work?

### Takeaway
Consistent direction: shift from operating tools to delegating and supervising/reviewing; UI differentiation shrinks while trust, transparency and control become the design problem. Disagreement on whether incumbents' systems of record or a new agent layer capture value. Gartner simultaneously forecasts adoption and heavy project cancellation.

### Cited Findings
- FORECAST: NN/g State of UX 2026 (Jan 2026): 2026 is "the year of AI fatigue"; winners treat AI as a background tool rather than headline feature; trust (transparency, user control, consistency, support when failing) is a core design problem; agents will ship "often before they're ready"; people will spend less time using interfaces and more delegating to agents; standardised design systems make UI cheap so interface is less of a differentiator; anyone can produce a plausible UI, so taste/research/judgement matter. — [NN/g State of UX 2026](https://www.nngroup.com/articles/state-of-ux-2026/)
- NN/g: interactive assistants keep users in control vs autonomous agents; reusing familiar UI conventions lets users focus on decisions; interfaces will need to serve human and agent users. — [NN/g AI Agents as Users](https://www.nngroup.com/articles/ai-agents-as-users/); [NN/g Qwen agent lessons](https://www.nngroup.com/articles/designing-ai-agents/) (via search summary)
- FORECAST: Sequoia "Services: The New Software" (Julien Bek, 5 Mar 2026): copilots sell the tool, autopilots sell the work; copilot-to-autopilot shift already under way; pitched at the larger "work budget". Thesis-driven, not a measured forecast. — [Sequoia](https://sequoiacap.com/article/services-the-new-software)
- FORECAST: a16z "Is Software Losing Its Head?" (May 2026): systems of record were defensible because humans lived in the interface; that weakens when agents read context, plan, call tools, act. Counter-view in "The Incumbents Are Coming": systems of record become more important. a16z Big Ideas 2026 "The Agentic Interface" (Dec 2025): an agent layer becomes primary interface for work execution. Headless architecture decoupling logic from UI discussed July 2026. (Mostly via podcast/summary pages.) — [a16z](https://a16z.com/is-software-losing-its-head/); [a16z](https://a16z.com/the-incumbents-are-coming/); [podwise summary](https://podwise.ai/episodes/6538049)
- FORECAST: Gartner: ~15% of day-to-day work decisions autonomous and 33% of enterprise software with agentic AI by 2028 (secondary reporting; original 2024 figures). Gartner June 2025 press release: over 40% of agentic AI projects will be cancelled by end-2027 (cost, unclear value, risk controls). — [Gartner press release](https://www.gartner.com/en/newsroom/press-releases/2025-06-25-gartner-predicts-over-40-percent-of-agentic-ai-projects-will-be-canceled-by-end-of-2027); [Process Excellence Network](https://www.processexcellencenetwork.com/ai/news/gartner-almost-half-of-agentic-ai-projects-will-be-cancelled-by-2028)
- Research on supervision UIs: split-screen agent views showed what the agent did but not why and strained users; oversight works better when agent supports rather than substitutes judgement; delegation redistributes supervisory work (specify, oversee, evaluate, intervene). — [arXiv 2509.10723](https://arxiv.org/pdf/2509.10723); [arXiv 2609.24234](https://arxiv.org/pdf/2609.24234) (both via summaries; second arXiv ID looks odd, verify before citing)
- FACT: Figma Config 2026 (24 Jun 2026): code layers on canvas ("Code is material for design"), Figma Motion, agent Skills, generative plugins; coverage is secondary. — [Figma recap](https://www.figma.com/blog/config-2026-recap/) (content not retrieved); [The Next Web](https://thenextweb.com/news/figma-config-code-layers-ai-skills-plugins-animations)

### Inferences
- INFERENCE: The primary CRM surface shifts toward review queues, diff/approval views, explanations and audit, with forms secondary. Gartner cancellation forecast supports designing for graceful failure and visible ROI rather than assuming autonomy.

### Gaps
- Config 2025/2026 talks specific to supervision UX not found; a16z and Sequoia original texts mostly read via summaries; no primary Gartner research page for the 2028 stats.

## 4. Calm technology, ambient agents, notifications, voice, command surfaces, inbox/feed

### Takeaway
Calm technology and command palettes have the strongest durability evidence; ambient agents fit calm principles only if notifications are sparse and provenance-rich. Evidence on voice and natural-language command bars in B2B is thin.

### Cited Findings
- FACT: Calm technology introduced by Weiser and Seely Brown (1995); Amber Case formalised principles (2015 book *Calm Technology*): minimise attention demands, use the periphery, inform without overwhelming. Her site says principles can apply to AI, primarily smart products; no AI-specific official list found. Case keynote at SciPy 2026 "Calm Technology and the History of AI". — [Wikipedia](https://en.wikipedia.org/wiki/Calm_technology); [Case Organic](https://www.caseorganic.com/post/principles-of-calm-technology); [SciPy 2026](https://pretalx.com/scipy-2026/talk/7UJUMK/)
- NN/g forecast links background AI and trust (see Q3). — [NN/g State of UX 2026](https://www.nngroup.com/articles/state-of-ux-2026/)
- FACT: Cmd+K palettes are a standard pattern (Slack, Superhuman, Linear, Stripe); Superhuman says the palette teaches shortcuts and must be the single place to find every command. Natural-language in command bars is described as an emerging next step; one case study separates NLP for generative actions from deterministic routing for operations. Sources are vendor/blog. — [Superhuman](https://blog.superhuman.com/how-to-build-a-remarkable-command-palette/); [Intercom](https://www.intercom.com/blog/?p=20790); [CommandBar](https://command.ai/blog/command-palette-past-present-and-future)
- FACT: OKLCH and color-mix() are Baseline (MDN: oklch widely available since May 2023); light-dark() and relative colour syntax are newer (version cutoffs vary by blog; verify on MDN/caniuse); gamut mapping caveat for high chroma. — [MDN oklch](https://developer.mozilla.org/docs/Web/CSS/color_value/oklch); [web.dev Baseline colour themes](https://web.dev/articles/baseline-in-action-color-theme)

### Inferences
- INFERENCE: Ambient agent design: default to silent work, surface a digest/queue with provenance, interrupt only for approvals or failures (calm principles plus NN/g trust points).
- INFERENCE: Natural-language command bar is best as an extension of the palette (typed actions first, NL fallback with preview-and-confirm), not a replacement.

### Gaps
- No credible evidence found on voice input adoption in B2B desktop software, notification design for agents (NN/g smart-home notifications article exists but not read), or inbox/feed-of-work efficacy data. Density controls and variable fonts/dynamic type: not researched with primary sources.

## 5. Which design traits will age well vs date quickly?

### Takeaway
Synthesised judgement (no source ranks these directly): durable = legibility, restraint, stable navigation/conventions, user-controlled contrast/density/motion, keyboard-first command surfaces, provenance and audit, tokenised theming. Dating quickly = translucency effects, decorative motion, per-user free-form generated layouts, chat-only interfaces, spatial UI as a primary surface.

### Cited Findings (support only)
- Legibility, stable controls, purposeful motion: [NN/g Liquid Glass](https://www.nngroup.com/articles/liquid-glass/); user-controlled transparency: [NN/g Glassmorphism](https://www.nngroup.com/articles/glassmorphism/); Apple reversal: [TechCrunch](https://techcrunch.com/2025/10/20/apple-will-let-users-roll-back-the-liquid-glass-look-with-new-tinted-option)
- Conventions/learnability: [NN/g Generative UI](https://www.nngroup.com/articles/generative-ui/)
- Keyboard command surfaces: [Superhuman](https://blog.superhuman.com/how-to-build-a-remarkable-command-palette/)
- Provenance/audit: MCP Apps logged, reviewable UI messages — [MCP blog](https://blog.modelcontextprotocol.io/posts/2026-01-26-mcp-apps/); NN/g trust/transparency — [State of UX 2026](https://www.nngroup.com/articles/state-of-ux-2026/)
- Tokens: [MDN oklch](https://developer.mozilla.org/docs/Web/CSS/color_value/oklch)
- Chat limits: MCP Apps rationale that text-only is slow for exploration — [MCP blog](https://blog.modelcontextprotocol.io/posts/2026-01-26-mcp-apps/)

### Inferences
- INFERENCE (all of the section takeaway): real-time collaboration was not researched; its durability is assumed, not evidenced here.

### Gaps
- No source directly classifying design traits as durable vs fad; real-time collaboration, density controls, variable fonts, dynamic type lack citations.
