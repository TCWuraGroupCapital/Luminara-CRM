# Generic AI-product UI tells ("AI slop") and what premium B2B products do instead (2024–2026)

Evidence quality note: many "slop" catalogues are blog/tool/vendor material (often tied to AI-design-skill products) and opinion rather than studies. Primary sources used: Linear, NN/g. Nothing found directly from Rauno Freiberg, Paco Coursey, Emil Kowalski, Jordan Singer, Figma/Config, Smashing, or Sidebar in this pass (see Gaps).

## Q1. Which visual tropes do designers and critics call out as AI-generic?

### Takeaway
Critics converge on a short list: purple/indigo-to-blue gradients, glassmorphism/glow, Inter by default, identical icon-card grids, rounded-everything, sparkle icons, bento sprawl. Most say the problem is unchosen defaults (model/tool convergence), not the styles themselves.

### Cited Findings
- Named tells catalogue (Impeccable, a design-anti-pattern tool/site; vendor material): purple-to-blue gradients on buttons/text/backgrounds; "glass panels, neon edges, and glowing orbs"; glassmorphism ("blur effects, glass cards, and glow borders"); radial-gradient halo on dark pages; "dark mode with glowing accents"; gradient text; side-tab coloured-stripe cards; nested cards ("cards inside cards create visual noise"); identical icon-heading-text card grids; icon tile above heading; extreme border-radius; "Inter everywhere" / overused fonts "Inter and Geist"; badge pill above headline; italic serif display headline; tiny numbered section labels; hero-metric layout; pulsing status dot with no status; decorative grid backgrounds; auto-scrolling logo marquee; hairline border plus wide shadow on same card; monotonous equal spacing; redundant UX writing; em-dash overuse; "supercharge"/"world-class" copy. The page does not name sparkles or bento. — [Impeccable slop](https://impeccable.style/slop)
- Why AI output converges: "safe design choices that work universally and offend no one just dominate web training data"; tells = Inter/Roboto, purple/indigo accent (traced to Tailwind default `bg-indigo-500`), centred hero + three boxed icon features, rounded corners and faint shadows everywhere, glassmorphism, lavender accents, badge above headline, left-border cards, numbered 1-2-3 steps; cites a Developers Digest audit of Show HN launches finding more than half carried the fingerprint; coding agents avoid tilted/glass/3D directions as harder to implement. Jason Zhou, Superdesign, 15 Jun 2026 — [Superdesign](https://www.superdesign.dev/blog/why-ai-design-looks-generic)
- Sparkle icon: NN/g (Kate Kaplan, 20 Sep 2024) found no participant linked sparkles to "artificial intelligence"; top associations in n=107 study were favouriting/saving and visual effects/optimisation (16.82% each); users read sparkle-stars as stars (73% associate star with save/favourite); "magical" look may gloss over AI risks. Recommends always adding a label/tooltip, preferring established icons. — [NN/g](https://www.nngroup.com/articles/ai-sparkles-icon-problem/)
- Further sparkle critiques surfaced in search (not fetched): [Nobody asked for the sparkle button (uxdesign.cc)](https://uxdesign.cc/nobody-asked-for-the-sparkle-button-ae54ab5065c6), [Big Medium: your sparkles are fizzling](https://bigmedium.com/ideas/your-sparkles-are-fizzling.html), [Studio Output: when did AI brands become just a sparkle](https://www.studio-output.com/thought/when-did-ai-brands-become-just-a-sparkle/), [Geoff Graham on AI iconography](https://geoffgraham.me/struggling-with-ai-iconography-for-ui-design).
- Bento fatigue: critiques say bento (popularised by Apple product pages ~2023) became the default SaaS feature section; one says "instead of building a case, the page presents a tray of facts"; another says teams composed around their weakest visual by making its card smaller; defenders say it is a scanning aid. All blog/vendor-level, some dated 2026. — [Bento Box Sprawl](https://aismells.pages.dev/bento-box-sprawl.html); [webtrends bento fatigue](https://webtrends.pages.dev/news/2026-05-04-trend-dispatch-bento-fatigue); [Creative Boom, 10 trends creatives are over in 2026](https://www.creativeboom.com/insight/10-trends-creatives-are-so-over-in-2026/); [SaaSFrame guide (defends)](https://www.saasframe.io/blog/designing-bento-grids-that-actually-work-a-2026-practical-guide)
- Other catalogues listing the same set (not fetched): [Hallmark anti-patterns](https://instagit.com/Nutlope/hallmark/common-tells-of-ai-slop-hallmark/), [925 Studios](https://www.925studios.co/blog/ai-slop-design-tells), [Spot the slop (Kosta Canatselis)](https://world.hey.com/kostac/spot-the-slop-a-ui-designer-s-guide-to-fixing-ai-defaults-4c448c9c).

### Inferences
- Specific tells for this brief (gradient chat bubbles, "Good morning, Name! How can I assist you?" empty states, fake avatars, meaningless donut charts) had no direct primary citation in my searches; treat as the user's/team's own heuristics unless sourced later.
- Impeccable lists Geist among "overused defaults", so adopting Geist/Inter does not itself signal quality; the signal is whether other choices (spacing, colour, density) are deliberate.

### Gaps
- No primary posts found from Rauno Freiberg, Paco Coursey, Emil Kowalski, Jordan Singer, Smashing, Sidebar, Config talks naming AI-generic tropes.
- No source found on gradient chat bubbles, greeting empty states, fake avatars, or rounded-2xl specifically.

## Q2. What did Linear change in its 2024 and later redesigns and why?

### Takeaway
2024: reduce visual noise, tighten alignment, raise hierarchy and density of navigation, Inter Display headings, LCH theme generator driven by 3 inputs. 2026: a "calmer" refresh that dims the sidebar, softens/removes borders, cuts icons and warms neutrals.

### Cited Findings
- March 2024 redesign goal: "reduce visual noise, maintain visual alignment, and increase the hierarchy and density of navigation elements"; headings moved to Inter Display, body stays Inter; theme generator moved HSL to LCH ("perpetually uniform"); light and dark share one generator; themes defined by 3 variables (base colour, accent colour, contrast) instead of 98; chrome blue used less for "a more neutral and timeless appearance"; text/neutral icons darker in light and lighter in dark; ~6 weeks; navigation deliberately left out as too risky. — [How we redesigned the Linear UI](https://linear.app/now/how-we-redesigned-the-linear-ui); changelog [2024-03-20](https://linear.app/changelog/2024-03-20-new-linear-ui)
- Karri Saarinen, "A design reset (part I)", 27 Mar 2024: design debt from small feature additions should be paid in "larger sweeps" every 2–3 years; "Functionality no longer fits in a coherent way." — [A design reset](https://linear.app/now/a-design-reset)
- 12 Mar 2026 refresh, "A calmer interface for a product in motion" (Charlie Aufmann, Maxime Heckel): principles "Don't compete for attention you haven't earned" and "Structure should be felt not seen"; dimmer nav sidebar so content dominates; compact icon-only tabs; fewer and smaller icons, removed coloured team-icon backgrounds; separators rounded, softened or removed; default themes moved from cool blue-ish to warmer, less saturated grey (too warm = muddy); colour tool (hue/chroma/lightness per token) built with Claude Code and exported to Figma; "most of what makes software feel good is what you aren't likely to see." — [Behind the latest design refresh](https://linear.app/now/behind-the-latest-design-refresh); changelog [2026-03-12](https://linear.app/changelog/2026-03-12-ui-refresh)
- Linear iOS (Robb Böhnke, 21 Oct 2025): built its own glass material instead of Apple Liquid Glass; skipped refraction because "refraction can make dense professional interfaces harder to read"; shows solid outlines under Increase Contrast. Shows blur used for function (scroll-edge, tab bar), not as card style. — [Linear liquid glass](https://linear.app/now/linear-liquid-glass)

### Inferences
- Linear's moves are the inverse of the slop list: fewer colours, quieter chrome, borders de-emphasised, content-first contrast.
- The 3-variable theme model (base/accent/contrast in a perceptual space) is a directly reusable tokenisation pattern for a CRM.

### Gaps
- The 2026 post does not discuss typography; the "calmer" wording is from 2026, not the 2024 post. Authorship of the 2026 post is Aufmann/Heckel, not Saarinen.

## Q3. Typography and colour in premium B2B software 2025–2026

### Takeaway
Evidence is thin from primary sources: Linear uses Inter + Inter Display with LCH tokens; Geist Sans/Mono is Vercel's system; Attio reportedly uses Inter at ~14px/20px in a ~36px row, single density (unofficial).

### Cited Findings
- Linear: Inter Display headings, Inter body, LCH tokens (see Q2). — [Linear](https://linear.app/now/how-we-redesigned-the-linear-ui)
- Vercel Geist system covers colours, typography, materials, layout, React components; typeface Geist Sans and Geist Mono. — [Geist](https://vercel.com/design/geist). Third-party extractions (unofficial, self-disclaimed): weights 400/500/600, tight negative tracking at display sizes, mono for technical labels — [shadcn.io Vercel-inspired](https://www.shadcn.io/design/vercel)
- Attio (unofficial reverse-engineered sources): no compact/comfortable toggle, ~36px rows, Inter ~14px/20px, small caps labels for metadata. — [nadesign Attio layout patterns](https://nadesign.pages.dev/references/attio-layout-patterns/); no official Attio design principles found.

### Inferences
- Tinted neutrals, one accent, hairlines, tabular numbers and light-vs-dark-first are standard practice but I found no primary citation for them in this pass.

### Gaps
- Nothing found on Söhne, Berkeley Mono, Inter Display adoption beyond Linear, Stripe dashboard, Folk, Bloomberg, Nothing OS, Teenage Engineering, Are.na, Read.cv, Things, Arc/Dia.
- Vercel type scale values unverified against vercel.com/geist/typography.

## Q4. Depicting AI presence without sparkle/orb

### Takeaway
Shape of AI documents function-specific icons (pencil, wand, loop, quote) rather than a universal symbol; Granola is the clearest "calm, invisible AI" case.

### Cited Findings
- Alternatives to sparkles: sparkly pencil for edit, wand for auto-fill, looped arrows for remix, paragraph/quote symbol for summarise, two-star for suggestions, dice for randomise; AI-native products "need not rely on magic imagery". Adobe Firefly pairs sparkle with a brand icon. — [Shape of AI: symbols](https://www.shapeof.ai/patterns/symbols)
- NN/g: pair icon with label/tooltip; prefer established icons. — [NN/g](https://www.nngroup.com/articles/ai-sparkles-icon-problem/)
- Granola co-founder Sam Stephenson: principle "Be calming: Granola should lower your blood pressure"; hides live transcript; no visible meeting bot. — [Cognitive Revolution episode](https://www.cognitiverevolution.ai/calm-ai-for-crazy-days-inside-granola-s-design-philosophy-with-co-founder-sam-stephenson/) (via search summary; quotes not verified on the page itself)
- Raycast (Petr Nikolaev): "keyboard first" required custom UI beyond AppKit defaults. — [Console.dev Q&A](https://console.dev/qa/raycast-petr-nikolaev/) (via search summary)

### Inferences
- Counter-move: label the AI action by verb (Summarise, Draft), show provenance/diff in place, not an avatar or orb.

### Gaps
- No primary source on Linear Agents, Attio AI, Arc/Dia, Superhuman presentation of AI.

## Q5. Dribbble dashboard shots vs production software with real data

### Takeaway
Critique is long-standing and mostly opinion: shots omit empty states, long strings and large numbers, and are built without engineering constraints.

### Cited Findings
- Paul Adams, Intercom, 18 Sep 2013 (OLD, flagged): "Things that look great but don't work well"; "A huge majority of the product design work on Dribbble looks the same"; visual polish comes after resolving deeper layers. — [Intercom](https://intercom.com/blog/the-dribbblisation-of-design)
- Search-summary claims (not fetched, treat as secondary): Dribbble dashboards almost never show the new-user no-data state; mockups break with long strings, nine-digit numbers, negative percentages; smoothed chart lines distort data. — [UX Magic dashboard UI design](https://uxmagic.ai/ru/blog/dashboard-ui-design); [Design with ugly data (DEV)](https://dev.to/domhabersack/design-with-ugly-data-3pf5); [Benn Stancil, design flaw in data products](https://benn.substack.com/p/the-design-flaw-at-the-heart-of-every)
- Counterpoint: [In defence of Dribbble (uxdesign.cc)](https://uxdesign.cc/in-defence-of-dribbble-and-artsy-uis-a0a2388994b6)

### Gaps
- No 2024–2026 primary critique of dark/donut/glass dashboard shots specifically.
- Searches blocked from fully covering Stripe, Things, Bloomberg, Nothing OS, TE, Are.na, Read.cv, Superhuman; no cited evidence for them.
