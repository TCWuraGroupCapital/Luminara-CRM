@verbatim
/*
 * The Desk: approve or reject agent calls beside a live model of the pipeline, move deals yourself,
 * and ask the assistant. Every change goes to the server first or immediately after an optimistic
 * move; the page then reloads its data so it always ends where the server says.
 */
function startDesk(root, config) {
    const $ = s => root.querySelector(s);
    const T = config.t, AT = config.assistantT;
    const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const { stages, proposals, can } = config.desk;
    let activity = config.desk.activity;
    const http = window.axios;

    /* ---------- Words, numbers, faces ---------- */
    const fill = (s, rep = {}) => String(s).replace(/:(\w+)/g, (m, k) => (k in rep ? rep[k] : m));
    function choice(s, n, rep = {}) { // Laravel's "{0} …|{1} …|[2,*] …" plural strings
        const parts = String(s).split('|');
        for (const part of parts) {
            const m = part.match(/^\s*(?:\{(\d+)\}|\[(\d+),(\d+|\*)\])\s*([\s\S]*)$/);
            if (m && (m[1] !== undefined ? +m[1] === n : n >= +m[2] && (m[3] === '*' || n <= +m[3]))) return fill(m[4], { count: n, ...rep });
        }
        return fill(parts[parts.length - 1].replace(/^\s*(?:\{\d+\}|\[\d+,(?:\d+|\*)\])\s*/, ''), { count: n, ...rep });
    }
    const compact = new Intl.NumberFormat(config.locale, { style: 'currency', currency: config.currency, notation: 'compact', maximumFractionDigits: 1 });
    const full = new Intl.NumberFormat(config.locale, { style: 'currency', currency: config.currency, maximumFractionDigits: 0 });
    const money = v => (Math.abs(v) >= 10000 ? compact : full).format(v);
    const clock = new Intl.DateTimeFormat(config.locale, { hour: '2-digit', minute: '2-digit' });
    const at = iso => (iso ? clock.format(new Date(iso)) : '');
    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const hue = s => [...String(s)].reduce((a, c) => (a * 31 + c.charCodeAt(0)) % 360, 7);
    const initials = s => String(s).replace(/[^\p{L}\s]/gu, '').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
    const face = name => `<span class="face" style="--fh:${hue(name)}" aria-hidden="true">${esc(initials(name))}</span>`;
    const icon = {
        check: '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>',
        arrow: '<svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
        warn: '<svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
        trash: '<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>',
        close: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>',
    };
    const errorText = e => e?.response?.data?.message ?? T['error-generic'];

    /* ---------- Data helpers ---------- */
    const stageById = id => stages.find(s => s.id === id);
    const find = id => { for (const s of stages) { const deal = s.deals.find(x => x.id === id); if (deal) return { deal, stage: s }; } return {}; };
    const open = () => proposals.filter(p => p.state === 'wait');
    const valueOf = p => p.deal_ids.reduce((a, id) => a + (find(id).deal?.value ?? Number(p.subject.find(r => r.id === id)?.lead_value ?? 0)), 0);
    const pendingFor = id => open().find(p => p.deal_ids.includes(id));
    function prepare(p) {
        p.state ??= 'wait';
        const n = p.deal_ids.length, to = stageById(p.to_stage_id)?.name ?? p.subject[0]?.moves_to ?? '';
        p.title = p.type === 'move' ? choice(T['title-move'], n, { stage: to }) : choice(T['title-remove'], n);
        p.kind = p.type === 'move' ? T['kind-move'] : T['kind-remove'];
        p.short = p.type === 'move' ? fill(T['short-move'], { count: n, stage: to }) : T['short-remove'];
        p.toName = to;
    }
    proposals.forEach(prepare);

    function moveLocal(ids, toId) {
        const to = stageById(toId), back = [];
        ids.forEach(id => {
            const { deal, stage } = find(id);
            if (!deal || !to || stage === to) return;
            back.push([id, stage.id]);
            stage.deals.splice(stage.deals.indexOf(deal), 1);
            stage.count -= 1; stage.value -= deal.value;
            to.deals.push(deal); to.count += 1; to.value += deal.value;
        });
        to?.deals.sort((a, b) => b.value - a.value);
        return back;
    }
    function removeLocal(id) {
        const { deal, stage } = find(id);
        if (!deal) return;
        stage.deals.splice(stage.deals.indexOf(deal), 1);
        stage.count -= 1; stage.value -= deal.value;
    }
    function redraw(moved = []) {
        view?.layout(moved);
        drawHeadline(); drawTotals(); drawSugs(); drawTimeline(); drawList();
    }
    // Ask the server what is true now and settle the page onto it.
    let refreshing = null;
    function refresh(delay = 0) {
        clearTimeout(refreshing);
        refreshing = setTimeout(async () => {
            try {
                const { data } = await http.get(config.urls.data);
                data.stages.forEach(fresh => {
                    const s = stageById(fresh.id);
                    if (!s) return;
                    Object.assign(s, { count: fresh.count, value: fresh.value, name: fresh.name });
                    s.deals.splice(0, s.deals.length, ...fresh.deals);
                });
                const kept = new Map(proposals.map(p => [p.id, p]));
                proposals.splice(0, proposals.length, ...data.proposals.map(p => Object.assign(kept.get(p.id) ?? {}, p, { state: 'wait' })));
                proposals.forEach(prepare);
                activity = data.activity;
                redraw([]);
                view?.syncMarks?.();
            } catch (e) {
                toast(T['load-failed']);
            }
        }, delay);
    }

    /* ---------- Decisions (server first) ---------- */
    async function decide(p, approve) {
        try {
            const { data } = await http.post(approve ? p.approve_url : p.reject_url, {}, { headers: { Accept: 'application/json' } });
            return { ok: true, status: data.status, message: data.message };
        } catch (e) {
            return { ok: false, message: errorText(e) };
        }
    }
    function applyDecision(p) {
        view?.clearMark(p.id);
        if (p.state === 'ok' && p.type === 'remove') {
            p.deal_ids.forEach(removeLocal);
            redraw([]);
            toast(T.removed);
        } else if (p.state === 'ok') {
            const back = moveLocal(p.deal_ids, p.to_stage_id);
            redraw(p.deal_ids);
            if (can.move && back.length) toast(choice(T['moved-n'], back.length, { stage: p.toName }), T.undo, () => undoMoves(back));
            else toast(choice(T['moved-n'], back.length, { stage: p.toName }));
        } else {
            redraw([]);
        }
        refresh(reduced ? 0 : 1600);
    }
    async function inlineApprove(p, card) {
        card?.classList.add('leaving');
        card?.querySelectorAll('button').forEach(b => { b.disabled = true; });
        const r = await decide(p, true);
        if (!r.ok || r.status !== 'completed') {
            toast(r.message);
            drawSugs();
            return refresh();
        }
        p.state = 'ok';
        applyDecision(p);
    }
    async function inlineReject(p) {
        const r = await decide(p, false);
        if (!r.ok) { toast(r.message); return refresh(); }
        p.state = 'no';
        applyDecision(p);
    }

    /* ---------- Moving a deal yourself (optimistic, then the CRM's own stage route) ---------- */
    async function putStage(id, stageId) {
        await http.put(fill(config.urls.stage, { id }), { lead_pipeline_stage_id: stageId });
    }
    async function humanMove(id, stageId) {
        if (!can.move) return toast(T['no-move-permission']);
        const { deal } = find(id), to = stageById(stageId);
        const back = moveLocal([id], stageId);
        if (!back.length) return;
        redraw([id]);
        try {
            await putStage(id, stageId);
            toast(fill(T.moved, { company: deal.company, stage: to.name }), T.undo, () => undoMoves(back));
        } catch (e) {
            moveLocal([id], back[0][1]);
            redraw([id]);
            toast(fill(T['move-failed'], { message: errorText(e) }));
        }
        refresh(reduced ? 0 : 1600);
    }
    async function undoMoves(back) {
        const ids = back.map(([id]) => id);
        back.forEach(([id, stageId]) => moveLocal([id], stageId));
        redraw(ids);
        try {
            for (const [id, stageId] of back) await putStage(id, stageId);
            toast(T.undone);
        } catch (e) {
            toast(fill(T['move-failed'], { message: errorText(e) }));
        }
        refresh(reduced ? 0 : 1600);
    }

    /* ---------- Headline, totals, suggestions, activity, list ---------- */
    function drawHeadline() {
        const n = open().length;
        const text = choice(T.need, n);
        $('#lum-h1').innerHTML = n ? esc(text).replace(String(n), `<span>${n}</span>`) : esc(text);
        $('#lum-lede').textContent = n ? T.lede : T['lede-none'];
        $('#lum-review-all').hidden = !n;
    }
    function pendingDelta(stage) {
        return open().reduce((a, p) => {
            const v = valueOf(p);
            if (p.type === 'move' && p.to_stage_id === stage.id) return a + v;
            if (p.deal_ids.some(id => find(id).stage === stage)) return a - p.deal_ids.reduce((b, id) => b + (find(id).stage === stage ? find(id).deal.value : 0), 0);
            return a;
        }, 0);
    }
    function drawTotals() {
        $('#lum-totals').innerHTML = stages.map(s => {
            const dv = pendingDelta(s);
            return `<div><dt>${esc(s.name)}</dt><dd class="num">${money(s.value)}<small>${esc(choice(T.deals, s.count))}${dv ? ` · <span class="delta">${esc(fill(T['if-approved'], { delta: (dv > 0 ? '+' : '−') + money(Math.abs(dv)) }))}</span>` : ''}</small></dd></div>`;
        }).join('');
    }
    function drawSugs() {
        const items = open();
        if (!items.length) {
            $('#lum-sugs').innerHTML = `<div class="sug-empty"><b>${esc(T['caught-up'])}</b><p>${esc(T['caught-up-info'])}</p>${config.assistant ? `<button class="btn" type="button" data-ask>${esc(T.ask)}</button>` : ''}</div>`;
            return;
        }
        $('#lum-sugs').innerHTML = items.map(p => {
            const deals = p.deal_ids.map(id => find(id).deal ?? { company: p.subject.find(r => r.id === id)?.title ?? `#${id}` });
            const value = p.type === 'remove' ? fill(T['value-remove'], { value: money(valueOf(p)) }) : fill(T['value-move'], { value: money(valueOf(p)), stage: p.toName });
            return `<article class="sug ${p.type}" data-sug="${p.id}" aria-labelledby="lum-s-${p.id}">
                <span class="kind"><i>${p.type === 'remove' ? icon.trash : icon.arrow}</i>${esc(p.kind)}</span>
                <h4 id="lum-s-${p.id}">${esc(p.title)}</h4>
                <p>${esc(deals.map(d => d.company).join(', '))}</p>
                <div class="faces">${deals.slice(0, 5).map(d => face(d.company)).join('')}<span class="value num">${esc(value)}</span></div>
                <div class="acts">
                    ${p.type === 'remove' ? `<button class="btn danger-soft" type="button" data-review="${p.id}">${esc(T['review-remove'])}</button>` : `<button class="btn primary" type="button" data-approve="${p.id}">${esc(T.approve)}</button>`}
                    <button class="btn quiet" type="button" data-review="${p.id}">${esc(T.details)}</button>
                    <button class="btn quiet" type="button" data-reject="${p.id}" aria-label="${esc(fill(T['no-label'], { title: p.title }))}">${esc(T.no)}</button>
                </div>
            </article>`;
        }).join('');
    }
    function activityText(a) {
        const r = { query: a.query ?? '', id: a.lead_id ?? '', title: a.title ?? '', stage: a.stage ?? '' };
        if (a.action === 'leads_search') return a.query ? fill(T['act-search'], r) : T['act-search-all'];
        if (a.action === 'leads_get') return fill(T['act-read'], r);
        if (a.action === 'leads_create') return fill(T['act-create'], r);
        if (a.action === 'leads_move_stage') return choice(a.status === 'pending_approval' ? T['act-suggest-move'] : T['act-move'], a.count ?? 1, r);
        if (a.action === 'leads_delete') return fill(a.status === 'pending_approval' ? T['act-suggest-delete'] : T['act-delete'], r);
        const label = String(a.action ?? '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
        return r.title ? `${label}: ${r.title}` : (r.id ? `${label} #${r.id}` : label);
    }
    function drawTimeline() {
        const list = $('#lum-timeline');
        list.innerHTML = activity.map(a => {
            const state = { pending_approval: 'wait', completed: 'ok', rejected: 'no', failed: 'no' }[a.status] ?? '';
            return `<li class="${state}"><time datetime="${esc(a.at)}">${esc(at(a.at))}</time><span class="dot"></span>
                <div class="what"><b>${esc(activityText(a))}</b><span class="tag">${esc(T.tags[a.status] ?? a.status)}</span></div></li>`;
        }).join('');
        $('#lum-timeline-empty').hidden = activity.length > 0;
    }
    function drawList() {
        $('#lum-list').innerHTML = `<table><caption class="sr">${esc(T['list-caption'])}</caption><thead><tr><th scope="col">${esc(T['col-stage'])}</th><th scope="col">${esc(T['col-deal'])}</th><th scope="col">${esc(T['col-value'])}</th><th scope="col">${esc(T['col-suggests'])}</th></tr></thead><tbody>${
            stages.flatMap(s => s.deals.map(x => `<tr><td>${esc(s.name)}</td><td><button class="linkish" type="button" data-deal="${x.id}">${esc(x.company)}</button> — ${esc(x.title)}</td><td class="num">${money(x.value)}</td><td>${esc(pendingFor(x.id)?.short ?? '')}</td></tr>`)).join('')}</tbody></table>`;
    }

    /* ---------- Narrow screens: one bar per stage, drawn to scale ---------- */
    function createBars(el) {
        el.innerHTML = stages.map(s => `<div class="bar-row" data-stage="${s.id}" data-code="${esc(s.code)}"><div class="bar-label"><b>${esc(s.name)}</b><span class="num"></span></div><div class="track"><i class="fill"></i><i class="pend"></i></div></div>`).join('');
        const rows = [...el.children];
        function layout() {
            const max = Math.max(1, ...stages.map(s => s.value + Math.max(0, pendingDelta(s))));
            stages.forEach((s, i) => {
                const dv = pendingDelta(s), row = rows[i];
                row.querySelector('.num').textContent = `${money(s.value)} · ${choice(T.deals, s.count)}${dv ? ` · ${fill(T['if-approved'], { delta: (dv > 0 ? '+' : '−') + money(Math.abs(dv)) })}` : ''}`;
                const solid = dv < 0 ? s.value + dv : s.value;
                row.querySelector('.fill').style.transform = `scaleX(${Math.max(0, solid) / max})`;
                const pend = row.querySelector('.pend');
                pend.className = `pend ${dv > 0 ? 'in' : dv < 0 ? 'out' : ''}`;
                pend.style.insetInlineStart = `${Math.max(0, solid) / max * 100}%`;
                pend.style.width = `${Math.abs(dv) / max * 100}%`;
            });
        }
        layout();
        return {
            layout, syncMarks() {}, clearMark() {}, resetView() {},
            highlight(id) {
                const p = proposals.find(x => x.id === id);
                const keys = p ? [p.to_stage_id, ...p.deal_ids.map(d => find(d).stage?.id)] : [];
                rows.forEach(r => r.classList.toggle('lit', keys.includes(Number(r.dataset.stage))));
            },
        };
    }

    /* ---------- Choosing the view ---------- */
    const card = $('.stage-card');
    let view = null, model = null, bars = null, modelState = 'idle';
    function describe(deal, stage) {
        const p = pendingFor(deal.id);
        return `<b>${esc(deal.company)}</b><span>${esc(deal.title)} · ${esc(stage.name)}</span><br><span class="num">${money(deal.value)}${deal.owner ? ` · ${esc(deal.owner)}` : ''}</span>${p ? `<br><span class="sug-note">${esc(fill(T['agent-suggests-short'], { what: p.short }))}</span>` : ''}`;
    }
    async function startModel() {
        modelState = 'loading';
        try {
            model = await createScene({
                canvas: $('#lum-scene'), labelsEl: $('#lum-labels'), tipEl: $('#lum-tip'), stages, proposals, reduced, describe,
                onSelect: openDeal, onDrop: humanMove,
                dropText: stage => fill(T.drop, { stage }),
                shownText: (shown, total) => fill(T['lane-shown'], { shown, total }),
            });
        } catch (e) {
            console.warn('Desk 3D model unavailable', e);
        }
        $('#lum-skeleton').hidden = true;
        modelState = model ? 'ready' : 'failed';
        if (!model) { $('#lum-model-note').hidden = false; }
        choose();
    }
    function choose() {
        const w = card.clientWidth;
        card.toggleAttribute('data-narrow', w < 1000);
        const use3d = w >= 640 && modelState !== 'failed' && stages.length > 0;
        card.dataset.mode = use3d ? 'model' : 'bars';
        $('#lum-bars').hidden = use3d;
        if (use3d && modelState === 'idle') return startModel();
        view = use3d ? model : (bars ??= createBars($('#lum-bars')));
        view?.layout([]);
    }
    new ResizeObserver(() => {
        const mode = card.dataset.mode, narrow = card.hasAttribute('data-narrow');
        const w = card.clientWidth;
        if ((w >= 640) !== (mode === 'model') || (w < 1000) !== narrow) choose();
    }).observe(card);

    /* ---------- Review flow: decisions go to the server at once; the model moves when you close ---------- */
    const review = $('#lum-review');
    let order = [], at_ = 0, decided = [];
    function openReview(startId) {
        order = open();
        if (!order.length) return;
        at_ = Math.max(0, order.findIndex(p => p.id === Number(startId)));
        decided = [];
        showStep();
        review.showModal();
    }
    const dots = () => { $('#lum-rv-dots').innerHTML = order.map((p, i) => `<i class="${p.decision === 'ok' ? 'ok' : i === at_ ? 'on' : ''}"></i>`).join(''); };
    function showStep() {
        const p = order[at_], body = $('#lum-rv-body');
        $('#lum-rv-step').textContent = fill(T.step, { n: at_ + 1, total: order.length });
        dots();
        const rows = p.deal_ids.map(id => {
            const { deal, stage } = find(id);
            const snap = p.subject.find(r => r.id === id) ?? {};
            return { name: deal?.company ?? snap.title ?? `#${id}`, title: deal?.title ?? snap.title ?? '', value: deal?.value ?? Number(snap.lead_value ?? 0), owner: deal?.owner ?? '', from: stage?.name ?? snap.stage ?? '' };
        });
        const effects = p.type === 'move'
            ? [['ok', fill(T['effect-move'], { stage: p.toName })], ['ok', T['effect-check']], ...(can.move ? [['ok', T['effect-undo']]] : [])]
            : [['ok', T['effect-check']], ['warn', T['effect-remove']]];
        body.innerHTML = `
            <div><span class="rv-kind ${p.type}">${esc(p.kind)}</span><h3 id="lum-rv-title">${esc(p.title)}</h3></div>
            <div class="changes">${rows.map(r => `<div class="change">${face(r.name)}<div><b>${esc(r.name)}</b><small>${esc([r.title !== r.name ? r.title : '', money(r.value), r.owner].filter(Boolean).join(' · '))}</small></div>
                <span class="moving">${p.type === 'move' ? `<span class="pill was">${esc(r.from)}</span>${icon.arrow}<span class="pill to">${esc(p.toName)}</span>` : `<span class="pill gone">${esc(T['removed-pill'])}</span>`}</span></div>`).join('')}</div>
            <div class="why"><span>${esc(T.why)}</span>${esc(p.reason || T['no-reason'])}</div>
            <ul class="effects">${effects.map(([k, t]) => `<li class="${k}">${k === 'warn' ? icon.warn : icon.check}${esc(t)}</li>`).join('')}</ul>
            ${p.type === 'remove' ? `<label class="confirm"><input type="checkbox" id="lum-rv-confirm"> ${esc(T['confirm-remove'])}</label>` : ''}
            <details class="tech"><summary>${esc(T.tech)}</summary>${esc(fill(T['tech-line'], { id: p.id, action: p.action, at: at(p.requested_at), by: p.acting_as ?? '', via: p.via === 'token' ? T['via-token'] : T['via-assistant'] }))}</details>
            <div class="rv-foot">
                <button type="button" class="btn quiet" data-rv="no">${esc(T['no-dont'])}</button>
                <button type="button" class="btn" data-rv="skip">${esc(T['not-now'])}</button>
                <button type="button" class="btn ${p.type === 'remove' ? 'danger' : 'primary'}" data-rv="ok" ${p.type === 'remove' ? 'disabled' : ''}>${esc(p.type === 'remove' ? T['remove-btn'] : T.approve)}</button>
            </div>`;
        body.classList.remove('swap'); void body.offsetWidth; body.classList.add('swap');
    }
    async function reviewDecide(approve) {
        const p = order[at_];
        review.querySelectorAll('[data-rv]').forEach(b => { b.disabled = true; });
        const r = await decide(p, approve);
        const good = r.ok && (approve ? r.status === 'completed' : r.status === 'rejected');
        p.decision = good ? (approve ? 'ok' : 'no') : 'error';
        decided.push(p);
        const next = order.find((x, j) => j > at_ && !x.decision);
        $('#lum-rv-body').innerHTML = `<div class="result" role="status">${good && approve ? '<span class="seal"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>' : ''}
            <h3 id="lum-rv-title">${esc(good ? (approve ? T['result-ok'] : T['result-no']) : T['result-error'])}</h3><p>${esc(good && approve ? T['result-ok-info'] : r.message)}</p>
            <div class="rv-foot">${next ? `<button type="button" class="btn primary" data-rv="next">${esc(fill(T.next, { title: next.title }))}</button>` : ''}<button type="button" class="btn ${next ? '' : 'primary'}" data-rv="close">${esc(T['close-watch'])}</button></div></div>`;
        dots();
        $('#lum-rv-body [data-rv]').focus();
    }
    review.addEventListener('click', e => {
        if (e.target === review) return review.close();
        const a = e.target.closest('[data-rv]')?.dataset.rv;
        if (a === 'ok' || a === 'no') reviewDecide(a === 'ok');
        if (a === 'skip' || a === 'next') {
            const i = order.findIndex((x, j) => j > at_ && !x.decision);
            if (i < 0) return review.close();
            at_ = i; showStep();
        }
        if (a === 'close') review.close();
    });
    review.addEventListener('change', e => { if (e.target.id === 'lum-rv-confirm') review.querySelector('[data-rv="ok"]').disabled = !e.target.checked; });
    review.addEventListener('close', () => {
        const plays = decided.splice(0).map(p => [p, p.decision]);
        order.forEach(p => delete p.decision);
        if (!plays.length) return;
        card.scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
        plays.forEach(([p, d], i) => setTimeout(() => {
            if (d === 'error') return refresh();
            p.state = d;
            applyDecision(p);
        }, (reduced ? 0 : 450) + i * (reduced ? 0 : 1100)));
    });

    /* ---------- Deal sheet ---------- */
    const dealSheet = $('#lum-deal');
    function openDeal(id) {
        const { deal, stage } = find(id);
        if (!deal) return;
        const p = pendingFor(id);
        dealSheet.dataset.id = id;
        $('#lum-deal-body').innerHTML = `
            <header class="sheet-top"><div class="deal-id">${face(deal.company)}<div><h2 id="lum-deal-title">${esc(deal.company)}</h2><p>${esc(deal.title)}</p></div></div>
                <button class="icon-btn" type="button" data-close aria-label="${esc(T.close)}">${icon.close}</button></header>
            <div class="sheet-body">
                <dl class="facts"><div><dt>${esc(T['deal-value'])}</dt><dd class="num">${money(deal.value)}</dd></div><div><dt>${esc(T['deal-stage'])}</dt><dd>${esc(stage.name)}</dd></div><div><dt>${esc(T['deal-owner'])}</dt><dd>${esc(deal.owner ?? '—')}</dd></div></dl>
                ${p ? `<div class="why"><span>${esc(T['agent-suggests'])}</span><b>${esc(p.title)}</b><button class="btn" type="button" data-review="${p.id}">${esc(T['review-suggestion'])}</button></div>` : ''}
                ${can.move ? `<form class="move-form" id="lum-move-form"><label for="lum-move-to">${esc(T['move-to'])}</label>
                    <div class="row"><select id="lum-move-to">${stages.map(s => `<option value="${s.id}" ${s === stage ? 'selected disabled' : ''}>${esc(s.name)}</option>`).join('')}</select>
                    <button class="btn primary" type="submit">${esc(T.move)}</button></div><p class="hint">${esc(T['move-hint'])}</p></form>` : ''}
                ${can.view ? `<a class="btn" href="${esc(deal.url)}">${esc(T['open-deal'])}</a>` : ''}
            </div>`;
        dealSheet.showModal();
    }
    dealSheet.addEventListener('click', e => { if (e.target === dealSheet || e.target.closest('[data-close]')) dealSheet.close(); });
    dealSheet.addEventListener('submit', e => {
        e.preventDefault();
        dealSheet.close();
        humanMove(Number(dealSheet.dataset.id), Number($('#lum-move-to').value));
    });

    /* ---------- Ask the agent (the real assistant) ---------- */
    const ask = $('#lum-ask'), log = $('#lum-ask-log'), say = $('#lum-say'), send = $('#lum-send'), stopBtn = $('#lum-stop');
    function bubble(cls, by, text) {
        const el = Object.assign(document.createElement('div'), { className: `msg ${cls}` });
        el.innerHTML = `<span class="by"></span><p></p>`;
        el.firstChild.textContent = by;
        el.lastChild.textContent = text;
        log.append(el);
        log.scrollTop = log.scrollHeight;
        return el;
    }
    let asking = false;
    stopBtn?.addEventListener('click', () => {
        if (!asking || stopBtn.disabled || !config.urls.stop) return;
        stopBtn.disabled = true;
        http.post(config.urls.stop).catch(() => {});
    });
    $('#lum-composer')?.addEventListener('submit', async e => {
        e.preventDefault();
        const message = say.value.trim();
        if (!message || asking) return;
        asking = true; send.disabled = say.disabled = true; send.hidden = true;
        if (stopBtn) { stopBtn.hidden = false; stopBtn.disabled = false; }
        log.setAttribute('aria-busy', 'true');
        $('#lum-suggest').hidden = true;
        bubble('', AT.you, message);
        say.value = '';
        const wait = bubble('agent', AT.agent, AT.working);
        try {
            const { data } = await http.post(config.urls.send, { message });
            wait.remove();
            const m = bubble('agent', AT.agent, data.reply);
            if (data.calls?.length) {
                const plan = document.createElement('ol');
                plan.className = 'plan';
                plan.setAttribute('aria-label', AT.steps);
                data.calls.forEach(c => {
                    const li = document.createElement('li');
                    li.className = c.status;
                    li.textContent = `${c.call_id ? `#${c.call_id} ` : ''}${c.action} · ${config.statuses[c.status] ?? c.status}`;
                    plan.append(li);
                });
                m.insertBefore(plan, m.lastChild);
                refresh();
            }
        } catch (err) {
            wait.remove();
            bubble('error', AT.agent, errorText(err) || AT.errors.unavailable);
        }
        asking = false; send.disabled = say.disabled = false; send.hidden = false;
        if (stopBtn) { stopBtn.hidden = true; stopBtn.disabled = false; }
        log.setAttribute('aria-busy', 'false');
        say.focus();
        log.scrollTop = log.scrollHeight;
    });
    say?.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#lum-composer').requestSubmit(); } });
    $('#lum-suggest')?.addEventListener('click', e => { const b = e.target.closest('[data-say]'); if (b) { say.value = b.dataset.say; $('#lum-composer').requestSubmit(); } });
    $('#lum-ask-close')?.addEventListener('click', () => ask.close());
    ask?.addEventListener('click', e => { if (e.target === ask) ask.close(); });

    /* ---------- Toast ---------- */
    let toastTimer;
    function toast(text, label, fn) {
        const t = $('#lum-toast');
        t.innerHTML = '<span></span>';
        t.firstChild.textContent = text;
        if (label) {
            const b = Object.assign(document.createElement('button'), { className: 'btn', type: 'button', textContent: label });
            b.onclick = () => { t.hidden = true; fn(); };
            t.append(b);
        }
        t.hidden = false;
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => { t.hidden = true; }, 9000);
    }

    /* ---------- Wiring ---------- */
    root.addEventListener('click', e => {
        const t = e.target.closest('[data-review], [data-approve], [data-reject], [data-deal], [data-ask]');
        if (!t) return;
        const p = id => proposals.find(x => x.id === Number(id));
        if (t.dataset.review) { if (dealSheet.open) dealSheet.close(); openReview(t.dataset.review); }
        if (t.dataset.approve) inlineApprove(p(t.dataset.approve), t.closest('.sug'));
        if (t.dataset.reject) inlineReject(p(t.dataset.reject));
        if (t.dataset.deal) openDeal(Number(t.dataset.deal));
        if (t.hasAttribute('data-ask') && ask) { ask.showModal(); say.focus(); }
    });
    const sugs = $('#lum-sugs');
    const light = e => view?.highlight(Number(e.target.closest('[data-sug]')?.dataset.sug) || null);
    sugs.addEventListener('pointerover', light);
    sugs.addEventListener('focusin', light);
    sugs.addEventListener('pointerleave', () => view?.highlight(null));
    sugs.addEventListener('focusout', e => { if (!sugs.contains(e.relatedTarget)) view?.highlight(null); });
    $('#lum-review-all').addEventListener('click', () => openReview(open()[0]?.id));
    $('#lum-reset').addEventListener('click', () => model?.resetView());
    $('#lum-list-toggle').addEventListener('click', e => {
        const on = e.currentTarget.getAttribute('aria-pressed') !== 'true';
        e.currentTarget.setAttribute('aria-pressed', on);
        $('#lum-stage').hidden = on;
        $('#lum-list').hidden = !on;
    });
    $('#lum-stage').addEventListener('pointerdown', () => $('#lum-drag-hint').classList.add('used'), { once: true });

    drawHeadline(); drawTotals(); drawSugs(); drawTimeline(); drawList();
    if (!stages.length) { card.hidden = true; $('#lum-no-pipeline').hidden = false; } else choose();
}
@endverbatim
