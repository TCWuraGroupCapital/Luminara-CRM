# Regulation and accessibility constraints on an AI-agent CRM interface, late 2026 to 2030

Research date: 15 Nov 2026. Not legal advice. Binding law is marked [LAW]; guidance, codes and drafts are marked [GUIDANCE]/[DRAFT]. Most findings come via law-firm summaries; primary pages were fetched only where a primary URL is cited. Items to verify on EUR-Lex/OAIC are flagged.

## 1. What must an in-app AI assistant disclose under Art. 50, and when? What labelling of AI-generated text/records is expected?

### Takeaway
Art. 50(1) (chatbot disclosure) applies from 2 Aug 2026 with no grace period; the Omnibus delayed only the Art. 50(2) machine-readable marking duty, and only for generative systems already on the market before 2 Aug 2026 (to 2 Dec 2026). Art. 50(4) human-visible text labelling covers only text published to inform the public on public-interest matters, so ordinary CRM notes, emails and records are most likely out of scope of the deployer label duty, though the provider marking duty (50(2)) may still apply to generated text outputs.

### Cited Findings
- [LAW] Art. 50(1): providers must design AI systems intended to interact directly with people so the people are informed they are interacting with AI, unless obvious to a "reasonably well-informed, observant and circumspect" person; Art. 50(5): information must be clear, distinguishable, given at the latest at first interaction/exposure, and meet applicable accessibility requirements — [Art. 50 text](https://artificialintelligenceact.eu/article/50/)
- [LAW] Art. 50(2): providers of systems (including general-purpose) generating synthetic audio, image, video or text must mark outputs in a machine-readable format, detectable as artificial; solutions effective, interoperable, robust, reliable as far as technically feasible; not applicable to standard editing assistance or systems not substantially altering input data — [Art. 50 text](https://artificialintelligenceact.eu/article/50/)
- [LAW] Art. 50(3): deployers of emotion-recognition or biometric categorisation systems must inform exposed people — [Art. 50 text](https://artificialintelligenceact.eu/article/50/)
- [LAW] Art. 50(4): deployers must disclose deepfakes and AI-generated/manipulated text published to inform the public on matters of public interest, unless human review/editorial control with editorial responsibility — [Art. 50 text](https://artificialintelligenceact.eu/article/50/)
- [LAW] Digital Omnibus on AI = Regulation (EU) 2026/1744, published in the OJ 24 July 2026, in force 27 July 2026 — [White & Case](https://www.whitecase.com/insight-alert/eu-ai-omnibus-enters-force-amending-ai-act), [Lewis Silkin](https://www.lewissilkin.com/insights/2026/07/27/the-digital-omnibus-on-ai-enters-into-force-today) (search summary; verify on EUR-Lex)
- [LAW] Omnibus left the Art. 50(1), (3), (4) obligations unchanged (apply from 2 Aug 2026); Art. 50(2) marking has until 2 Dec 2026 only for generative systems placed on the market before 2 Aug 2026; systems placed on the market later get no grace — [CSA research note](https://labs.cloudsecurityalliance.org/research/csa-research-note-eu-ai-act-article50-watermarking-deadline/), [McCann FitzGerald Part 1](https://www.mccannfitzgerald.com/knowledge/data-privacy-and-cyber-risk/ai-transparency-european-commissions-guidelines-on-article-50-part-1-provider-obligations). An earlier provisional-deal summary was unclear on whether the grace covers only legacy systems — [Simmons & Simmons](https://www.simmons-simmons.com/en/publications/cmoveo6ke00icuxskpgueb0od/ai-omnibus-update) (superseded by the enacted-text summaries above)
- [GUIDANCE, non-binding] Commission Art. 50 Guidelines published 20 July 2026 (draft 8 May 2026): disclosure may be textual/auditory/visual/multimodal; standardised labels recommended; adapt for vulnerable users; a single prominent notice usually suffices but riskier contexts may warrant periodic reminders; terms-and-conditions-buried disclosure or imperceptible marking is insufficient; system must disclose its nature when asked; "obvious" exception construed narrowly — [McCann FitzGerald Part 1](https://www.mccannfitzgerald.com/knowledge/data-privacy-and-cyber-risk/ai-transparency-european-commissions-guidelines-on-article-50-part-1-provider-obligations); Commission page: [guidelines](https://digital-strategy.ec.europa.eu/en/library/guidelines-transparency-obligations-providers-and-deployers-ai-systems). One source says the 20 July text approved content with formal adoption after translation — [Modulos via search summary](https://docs.modulos.ai/frameworks/eu-ai-act/commission-guidance/transparency)
- [GUIDANCE] Guidelines: Art. 50(2) may apply to AI agents whose user-facing outputs are AI-generated content but not intermediate processing steps; excludes standard editing (spell/grammar/basic translation), short outputs, simple data processing — [McCann FitzGerald Part 1](https://www.mccannfitzgerald.com/knowledge/data-privacy-and-cyber-risk/ai-transparency-european-commissions-guidelines-on-article-50-part-1-provider-obligations)
- [GUIDANCE] Guidelines on deployers: disclosures must be "clear, distinguishable and accessible", via written notices, icons, oral announcements; machine-readable marking alone insufficient for deepfakes; Art. 50(5) adds no new accessibility rules itself, check other regimes — [McCann FitzGerald Part 2](https://www.mccannfitzgerald.com/knowledge/data-privacy-and-cyber-risk/ai-transparency-european-commissions-guidelines-on-article-50-part-2-deployer-obligations)
- [GUIDANCE, voluntary] Final Code of Practice on marking and labelling AI-generated content published 10 June 2026; Section 1 providers (marking/detection), Section 2 deployers (labelling); Commission and AI Board confirmed it as adequate voluntary tool; optional common EU icon set; ~190 signatories by end July 2026 — [European Commission](https://digital-strategy.ec.europa.eu/en/policies/code-practice-ai-generated-content)
- [GUIDANCE] Code requires signatories to implement interoperable watermark detection by 2 Feb 2027 (secondary source) — [CSA](https://labs.cloudsecurityalliance.org/research/csa-research-note-eu-ai-act-article50-watermarking-deadline/)
- Guidelines do not clearly cover B2B/professional users, AI-generated text in emails/records, or human-edited exemption for non-public text (not addressed in the summaries read) — [McCann Part 1](https://www.mccannfitzgerald.com/knowledge/data-privacy-and-cyber-risk/ai-transparency-european-commissions-guidelines-on-article-50-part-1-provider-obligations)

### Inferences
- Luminara's in-app Assistant is a system "intended to interact directly with natural persons": show a persistent, visible "AI" label in the panel at first open (not only in ToS), and have it state it is AI when asked. B2B users are plausibly still "natural persons"; "obvious" exception is narrow, so do not rely on it.
- Who is "provider" vs "deployer": if Luminara ships the Assistant it is likely the provider (50(1)/(2)); CRM customers are deployers (50(4)). The software should give customers the tools to label (per-record "AI-drafted" badge, export metadata).
- Outbound AI-drafted emails to the public/customers are probably not "public-interest text", but the 50(2) provider marking duty for generated text may still apply unless an exemption (standard editing, assistive) fits; a cheap safe design is a machine-readable provenance field on every AI-generated record/draft (author_type, model, run id, timestamp, human-approved flag) plus a visible badge.
- Design for 2030: treat 50(2)-style provenance metadata and a visible AI badge as baseline, since the Code and guidelines point that way and adequacy is assessed individually for non-signatories.

### Gaps
- Exact EUR-Lex text of Reg. 2026/1744 and the final OJ version of the guidelines were not read directly; dates come from law-firm summaries.
- No source found addressing whether the guidelines treat text drafted by an assistant and sent by a human (B2B email) as in scope of 50(2)/(4).
- Guidelines' treatment of AI agents under 50(1) not found.

## 2. Human oversight UI affordances (stop, override, logs); is a CRM likely high-risk?

### Takeaway
A general CRM is unlikely to be Annex III high-risk, but specific features (staff performance monitoring, recruitment, credit/insurance scoring) could be. Art. 14 therefore need not bind the whole product, yet its "understand, avoid automation bias, interpret, override, stop" list is the best checklist for agent UI, and Australia's guidance independently asks for human control. High-risk dates were delayed.

### Cited Findings
- [LAW] Art. 14(1)-(4): high-risk systems must be designed for effective human oversight with suitable human-machine interface tools; overseers must be able to understand capabilities and limits and monitor anomalies, remain aware of automation bias, interpret output, decide not to use / disregard / override / reverse output, and intervene or halt via a "stop" button or similar — [Art. 14](https://artificialintelligenceact.eu/article/14/)
- [LAW] Annex III covers employment (4(a) recruitment/selection incl. targeted job ads; 4(b) decisions on work terms, promotion, termination, task allocation, monitoring and evaluating performance and behaviour), and access to essential services (5(b) creditworthiness/credit scoring; 5(c) risk assessment/pricing for life and health insurance); lead scoring/segmentation is not listed — [Annex III](https://artificialintelligenceact.eu/annex/3/)
- [LAW] High-risk application dates delayed by the Omnibus: Annex III to 2 Dec 2027, Annex I to 2 Aug 2028 — [Art. 14 page (Art. 113(c))](https://artificialintelligenceact.eu/article/14/), [Simmons & Simmons](https://www.simmons-simmons.com/en/publications/cmoveo6ke00icuxskpgueb0od/ai-omnibus-update); Annex I date also in [Abreu Advogados](https://abreuadvogados.com/en/conhecimento/publications/digital-omnibus-regulation-on-ai-the-key-amendments-to-the-ai-act/)
- [GUIDANCE, voluntary] Australia: "Guidance for AI Adoption" (21 Oct 2025) replaced the 2024 Voluntary AI Safety Standard, condensing 10 guardrails to 6 practices: accountability; understand impacts; measure and manage risks; share essential information; test and monitor; maintain human control — [Babl summary](https://babl.ai/australia-releases-updated-guidance-for-responsible-ai-adoption-across-all-sectors/), [industry.gov.au](https://www.industry.gov.au/publications/guidance-ai-adoption), [Foundations PDF](https://www.industry.gov.au/sites/default/files/2025-10/guidance-for-ai-adoption-foundations.pdf)

### Inferences
- UI affordances to build regardless of legal classification: global and per-run Stop control (keyboard-reachable), approve/reject/edit before any outbound or destructive action, undo/reverse for agent writes, an action log showing what the agent read/changed and why (inputs, tools, model, confirm status), confidence/limitation notes, and nudges against rubber-stamping (e.g. show the diff, not only "Approve").
- Feature-gating: keep staff-monitoring, recruitment and credit-like scoring out of the default product or flag as high-risk modules.

### Gaps
- Whether the Omnibus changed the Annex III scope text itself was not checked; no authoritative classification opinion on CRMs found.
- Exact audience for Art. 14 logging (Art. 12 record-keeping) not researched.

## 3. GDPR Article 22 automated decision-making

### Takeaway
Art. 22 bites when a decision (including a score that plays a determining role) has legal or similarly significant effects; the CJEU read this broadly in SCHUFA and required meaningful explanation in Dun & Bradstreet. CRM lead scoring is usually not covered, but credit/contract-refusal-type automation is.

### Cited Findings
- [LAW/case law] CJEU C-634/21 SCHUFA (Dec 2023): a credit score can itself be a "decision" under Art. 22(1) when it plays a determining role in a third party's decision — [Clyde & Co](https://clydeco.com/en/insights/2025/03/ecj-ruling-on-automated-decision-making-and-data-s), [CDBF](https://cdbf.ch/en/1400/)
- [LAW/case law] CJEU C-203/22 Dun & Bradstreet Austria (27 Feb 2025): Art. 15(1)(h) "meaningful information about the logic involved" means explaining the procedure and principles applied so the person can understand how data was used and how variations would change the outcome, not the algorithm itself; trade secrets are weighed by the authority/court — [Clyde & Co](https://clydeco.com/en/insights/2025/03/ecj-ruling-on-automated-decision-making-and-data-s), [Thomas Helbing](https://www.thomashelbing.com/en/wissen/llms.mdx/dsgvo-hub/rechtsprechung/1.4.52-eugh-dun-bradstreet)

### Inferences
- Provide per-decision explanation views and a "request human review" path wherever the agent's output determines a decision about an individual (refusing service, pricing, eligibility). Keep a human decision step for such flows.

### Gaps
- Art. 22 text itself and exceptions (22(2)-(4)) not fetched; judgments' full text not read; reach beyond credit scoring is contested among commentators.

## 4. WCAG 2.2 criteria most affecting chat/agent UIs

### Takeaway
WCAG 2.2 (W3C Rec, 5 Oct 2023) adds 2.4.11, 2.5.7, 2.5.8, 3.2.6, 3.3.7, 3.3.8 at A/AA; for a chat/agent panel the heaviest are 2.4.11 (sticky panels and composers), 2.5.8 (icon buttons like stop/approve), 2.5.7 (drag-to-reorder alternatives) and 4.1.3 (status messages, WCAG 2.1) for streamed replies.

### Cited Findings
- WCAG 2.2 new criteria: 2.4.11 Focus Not Obscured (Minimum) AA; 2.5.7 Dragging Movements AA; 2.5.8 Target Size (Minimum) AA, 24x24 CSS px or spacing exceptions; 3.2.6 Consistent Help A; 3.3.7 Redundant Entry A; 3.3.8 Accessible Authentication (Minimum) AA; 4.1.1 Parsing removed; AAA: 2.4.12, 2.4.13 Focus Appearance, 3.3.9 — [W3C WAI What's new in 2.2](https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/)
- 4.1.3 Status Messages (AA, from WCAG 2.1): status messages must be programmatically determinable without focus — see [WCAG 2.2 Rec](https://www.w3.org/TR/WCAG22/#status-messages) (cited from knowledge of the spec; not fetched this session)

### Inferences
- Chat/agent mapping (design implications): streamed or finished replies, "agent running", "action needs approval", errors via `role="status"`/polite live region (4.1.3), and do not announce every token; sticky composer/consent bars must not cover the focused element (2.4.11, use scroll-padding); Stop/Approve/Reject/copy icon buttons at least 24px with spacing, ideally 44px; any drag (kanban, reordering) needs click alternative (2.5.7); help/"Ask AI" entry in consistent place (3.2.6); do not re-ask entered data across multi-step agent forms (3.3.7); no CAPTCHA/cognitive tests in login (3.3.8); keep focus management predictable when the panel opens/closes (return focus), and avoid focus stealing on new messages.

### Gaps
- 4.1.3 and WCAG 2.2 normative wording not fetched; no source tested screen reader behaviour of streaming chat.

## 5. WCAG 3.0 status in 2026 and APCA

### Takeaway
WCAG 3.0 is still a Working Draft (latest 10 Sep 2026), with no fixed finish date (commentary says 2028-2030 at earliest); WCAG 2.2 stays the compliance reference. The contrast algorithm is explicitly undecided and APCA is not in the draft, so a 2030-proof design should meet WCAG 2 ratios as a floor and use APCA Lc only as an advisory check.

### Cited Findings
- W3C WCAG 3.0 Working Draft dated 10 September 2026 on the Recommendation track; text contrast contains placeholders, with the editor's note "The contrast algorithm used in WCAG 3 is yet to be determined"; WCAG 3 is a successor but does not deprecate WCAG 2; WCAG 2.2 A/AA content is expected to meet most of the WCAG 3 minimum conformance level; mapping/transition support planned — [W3C WCAG 3](https://w3.org/TR/WCAG3/)
- Earlier WCAG 3 draft announcement: [W3C WAI, March 2026 draft](https://www.w3.org/WAI/news/2026-03-03/wcag3)
- APCA was dropped from the July 2023 WCAG 3 working draft, no replacement selected (as of 8 Apr 2026 Editor's Draft); the author estimates WCAG 3 will not be final before ~2030; advice: pick colours that also pass WCAG 2 ratios or document exceptions; APCA's developers say it is draft guidance under testing and WCAG 2 conformance should not be dropped — [Adrian Roselli, Apr 2026](https://adrianroselli.com/2026/04/wcag3-contrast-as-of-april-2026.html)
- Secondary commentary expects Recommendation status not before 2028-2030 — [Qualibooth](https://www.qualibooth.com/resources/wcag-22-vs-30-whats-coming/), [Yatil](https://www.yatil.net/blog/wcag-3-is-not-ready-yet)

### Inferences
- Check both: WCAG 2.x ratio (4.5:1 text, 3:1 UI/large) as the contractual pass/fail; APCA Lc as a non-binding perceptual cross-check (especially dark mode and thin type). Where they disagree, satisfy WCAG 2 and consider raising contrast.

### Gaps
- Did not read the WCAG 3 draft past the first 100k characters; no authoritative confirmation of whether APCA could return.
- Conflicting secondary sources said APCA was "part of the draft"; the primary W3C text and Roselli contradict that.

## 6. European Accessibility Act applicability to B2B SaaS

### Takeaway
The EAA (Directive 2019/882, applying from 28 June 2025) targets consumer-facing products and services; pure B2B SaaS is generally outside, but a CRM used by customers to serve consumers, or national rules on workplace/public-sector software, can pull it in. Treat EN 301 549 / WCAG 2.2 AA as the practical target.

### Cited Findings
- Sources describe the EAA as primarily consumer-facing (e-commerce, banking, e-books, transport) and say scope for B2B SaaS is contested; microenterprise exemption: under 10 staff and turnover or balance sheet at most EUR 2m (services) — [Karl Groves](https://karlgroves.com/the-european-accessibility-act-and-b2b-software-what-internal-platforms-must-comply-with/), [accessibility.works](https://www.accessibility.works/blog/saas-eaa-compliance-european-accessibility-act-en-301-549-requirements/), [sota.io](https://sota.io/blog/european-accessibility-act-2019-882-saas-web-app-compliance-guide-2026)
- Member-state rules differ for internal/B2B platforms (France Art. 47 for large organisations' intranets; Italy, Spain for public-sector suppliers; Germany's BFSG consumer-facing only); EN 301 549 is the harmonised standard — [Karl Groves](https://karlgroves.com/the-european-accessibility-act-and-b2b-software-what-internal-platforms-must-comply-with/)
- Art. 50(5) AI Act separately requires disclosures to meet applicable accessibility requirements — [Art. 50](https://artificialintelligenceact.eu/article/50/)

### Inferences
- If Luminara has any customer-facing surface (booking forms, portals, quote acceptance, public chat widget), that layer is a likelier EAA target; the internal admin UI less so. Public sector buyers will contractually require EN 301 549.

### Gaps
- Directive text and the claimed 28 June 2030 microenterprise sunset not confirmed (single unverified claim); sources are vendor blogs. EN 301 549 version tracking WCAG 2.2 not checked.

## 7. Australia: Privacy Act ADM transparency (from 10 Dec 2026), DDA/WCAG

### Takeaway
From 10 Dec 2026 APP entities must describe in their privacy policy, under APP 1.7-1.9, the kinds of personal information used by and the kinds of decisions made (or substantially and directly informed) by computer programs that significantly affect individuals. It is a transparency duty, not a right to contest; it is broad (includes rule-based and generative tools) and sits with the entity using the software, so Luminara must give its customers the information needed.

### Cited Findings
- Commencement 10 Dec 2026 under the Privacy and Other Legislation Amendment Act 2024; OAIC final guidance (fact sheet, flowchart, updated APP 1 Guidelines) released 30 Sep 2026 — [Allens, 2 Oct 2026](https://www.allens.com.au/insights-news/insights/2026/10/mandatory-automated-decision-making-disclosure-requirements-are-coming-key-takeaways-from-the-oaic-guidance/); OAIC consultation page: [OAIC](https://www.oaic.gov.au/engage-with-us/consultations/consultation-on-guidance-for-transparency-in-automated-decision-making)
- APP 1.7 trigger: computer program makes or does something substantially and directly related to making a decision that could reasonably be expected to significantly affect an individual's rights or interests, using personal information; APP 1.8: policy must disclose kinds of personal information used, kinds of decisions made solely by programs, and kinds where the program is substantially and directly related; technical detail not required; sensitive data called out — [Allens](https://www.allens.com.au/insights-news/insights/2026/10/mandatory-automated-decision-making-disclosure-requirements-are-coming-key-takeaways-from-the-oaic-guidance/)
- OAIC reading: "computer program" is broad (rule-based, ML, spreadsheets, generative AI); human review does not exempt a tool that materially informs the decision; "arranged for" captures embedded vendor ADM; decisions can be beneficial or adverse; targeted advertising may be captured when it involves differential pricing or access to significant goods/services — [Allens](https://www.allens.com.au/insights-news/insights/2026/10/mandatory-automated-decision-making-disclosure-requirements-are-coming-key-takeaways-from-the-oaic-guidance/)
- Obligations are transparency only: no right to contest or direct notice to individuals — [Allens](https://www.allens.com.au/insights-news/insights/2026/10/mandatory-automated-decision-making-disclosure-requirements-are-coming-key-takeaways-from-the-oaic-guidance/)
- Enforcement: OAIC infringement and compliance notices and civil penalties can apply to non-compliant policies — [Gadens-referenced summary via search](https://www.gadens.com/insights/five-weeks-to-10-december-as-breaches-grow-and-frontier-ai-sharpens-cyber-threats,-transparency-on-adm-has-never-mattered-more) (secondary)
- Australian voluntary guidance: see section 2 (Guidance for AI Adoption, 6 practices) — [industry.gov.au](https://www.industry.gov.au/publications/guidance-ai-adoption)

### Inferences
- Luminara should ship an "automated decision register" export: per feature, the kinds of personal data used, whether decision is solely automated or substantially related, and a plain-language description customers can paste into their privacy policy. Lead scoring that drives differential pricing/offers or access to significant services is the likeliest in-scope CRM feature.
- Given the breadth, assume any agent suggestion that materially shapes how an individual is treated (priority, offer, eligibility) may count even with a human approving.

### Gaps
- Not found: DDA 1992 / Australian Human Rights Commission position on WCAG (WCAG 2.x via the Disability Standards/Guidance Note, not researched this session) — cannot cite.
- OAIC primary guidance not fetched; Privacy Act text on legislation.gov.au not read; no CRM-specific example in the guidance as summarised.
- Australia's National AI Plan (Dec 2025) link to guidance replacement not confirmed.
