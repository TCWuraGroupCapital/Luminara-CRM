// Luminara Home prototype: the review, ask and move flows, wired to the 3D model or (on phones) the stage bars.
import { stages, proposals, timeline } from './data.js';

const $ = (s, r = document) => r.querySelector(s);
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;
const now = () => new Date().toTimeString().slice(0, 5);
const money = v => (Math.abs(v) >= 1e6 ? `$${(v / 1e6).toFixed(2)}M` : Math.abs(v) >= 1e4 ? `$${Math.round(v / 100) / 10}k` : `$${Math.round(v).toLocaleString('en')}`);
const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c]);
const hue = s => [...s].reduce((a, c) => (a * 31 + c.charCodeAt(0)) % 360, 7);
const initials = s => s.replace(/[^\p{L}\s]/gu, '').split(/\s+/).filter(Boolean).slice(0, 2).map(w => w[0]).join('').toUpperCase();
const face = name => `<span class="face" style="--fh:${hue(name)}" aria-hidden="true">${esc(initials(name))}</span>`;
const icon = {
  check: '<svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>',
  arrow: '<svg viewBox="0 0 24 24"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
  warn: '<svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>',
  trash: '<svg viewBox="0 0 24 24"><path d="M3 6h18M8 6V4h8v2M6 6l1 14h10l1-14"/></svg>',
  close: '<svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6 6 18"/></svg>',
};

proposals.forEach(p => { p.state = 'wait'; });
const open = () => proposals.filter(p => p.state === 'wait');
const stageOf = key => stages.find(s => s.key === key);
const find = id => { for (const s of stages) { const deal = s.deals.find(x => x.id === id); if (deal) return { deal, stage: s }; } return {}; };
const idsOf = p => (p.type === 'move' ? p.dealIds : [p.dealId]);
const valueOf = p => idsOf(p).reduce((a, id) => a + (find(id).deal?.value ?? 0), 0);
const pendingFor = id => open().find(p => idsOf(p).includes(id));

/* ---------- Changing the data (the only place it changes) ---------- */
let view = null; // the 3D model or the phone bars; both expose layout / highlight / clearMark
function moveDeals(ids, toKey) {
  const to = stageOf(toKey), back = [];
  ids.forEach(id => {
    const { deal, stage } = find(id);
    if (!deal || stage === to) return;
    back.push([id, stage.key]);
    stage.deals.splice(stage.deals.indexOf(deal), 1);
    stage.n -= 1; stage.v -= deal.value;
    to.deals.push(deal); to.n += 1; to.v += deal.value;
  });
  to.deals.sort((a, b) => b.value - a.value);
  // A person moved a deal the agent had a suggestion about: like the server, close that suggestion unrun.
  open().forEach(p => { if (idsOf(p).some(id => back.some(([b]) => b === id)) && !p.applying) staleProposal(p); });
  refresh(ids);
  return back;
}
function removeDeal(id) {
  const { deal, stage } = find(id);
  if (!deal) return;
  stage.deals.splice(stage.deals.indexOf(deal), 1);
  stage.n -= 1; stage.v -= deal.value;
  refresh([]);
}
function addDeal(stageKey, deal) {
  const s = stageOf(stageKey);
  s.deals.push(deal); s.n += 1; s.v += deal.value;
  s.deals.sort((a, b) => b.value - a.value);
  refresh([deal.id]);
}
function refresh(moved) {
  view?.layout(moved);
  drawTotals(); drawSugs(); drawTimeline(); drawList(); drawHeadline();
}

/* ---------- Decisions ---------- */
function approve(p) {
  p.applying = true;
  p.state = 'ok'; p.decidedAt = now();
  view?.clearMark(p.id);
  if (p.type === 'remove') {
    removeDeal(p.dealId);
    toast('Removed the duplicate lead.');
  } else {
    const back = moveDeals(p.dealIds, p.to);
    toast(`Moved ${p.dealIds.length} deals to ${stageOf(p.to).name}.`, 'Undo', () => undoMove(back, `Moved them back`));
  }
  p.applying = false;
}
function reject(p) {
  p.state = 'no'; p.decidedAt = now();
  view?.clearMark(p.id);
  refresh([]);
}
function staleProposal(p) {
  p.state = 'stale'; p.decidedAt = now();
  view?.clearMark(p.id);
}
function undoMove(back, text) {
  const groups = {};
  back.forEach(([id, key]) => (groups[key] ??= []).push(id));
  Object.entries(groups).forEach(([key, ids]) => moveDeals(ids, key));
  timeline.push({ at: now(), text, sub: 'Undone by you', state: 'no', fresh: true });
  drawTimeline();
  toast('Undone.');
}
function humanMove(id, toKey) {
  const { deal } = find(id);
  const back = moveDeals([id], toKey);
  if (!back.length) return;
  timeline.push({ at: now(), text: `You moved ${deal.company} to ${stageOf(toKey).name}`, state: 'ok', fresh: true });
  drawTimeline();
  toast(`Moved ${deal.company} to ${stageOf(toKey).name}.`, 'Undo', () => undoMove(back, `Moved ${deal.company} back`));
}

/* ---------- Headline, totals, suggestions, timeline, list ---------- */
function drawHeadline() {
  const n = open().length;
  $('#needCount').textContent = n === 0 ? 'Nothing' : n === 1 ? '1 thing' : `${n} things`;
  $('#h1').lastChild.textContent = n > 1 ? ' need your OK' : ' needs your OK';
  $('#navBadge').textContent = n || '';
  $('#reviewAll').hidden = !n;
}
function pendingDelta(key) {
  return open().reduce((a, p) => {
    const v = valueOf(p);
    if (p.type === 'move' && p.to === key) return a + v;
    if (p.type === 'move' && p.from === key) return a - v;
    if (p.type === 'remove' && find(p.dealId).stage?.key === key) return a - v;
    return a;
  }, 0);
}
function drawTotals() {
  $('#totals').innerHTML = stages.map(s => {
    const dv = pendingDelta(s.key);
    return `<div><dt>${s.name}${s.note ? ` · ${s.note}` : ''}</dt><dd class="num">${money(s.v)}<small>${s.n} deals${dv ? ` · <span class="delta">${dv > 0 ? '+' : '−'}${money(Math.abs(dv))} if approved</span>` : ''}</small></dd></div>`;
  }).join('');
}
function drawSugs() {
  const items = open();
  if (!items.length) {
    $('#sugList').innerHTML = `<div class="sug-empty"><b>You’re all caught up</b><p>When the agent prepares something that needs you, it appears here and on the model.</p><button class="btn" type="button" data-ask>Ask the agent</button></div>`;
    return;
  }
  $('#sugList').innerHTML = items.map(p => {
    const v = valueOf(p), deals = idsOf(p).map(id => find(id).deal).filter(Boolean);
    return `<article class="sug ${p.type}" data-sug="${p.id}" aria-labelledby="s-${p.id}">
      <span class="kind"><i>${p.type === 'remove' ? icon.trash : icon.arrow}</i>${p.kind}</span>
      <h4 id="s-${p.id}">${p.title}</h4>
      <p>${p.sentence}</p>
      <div class="faces">${deals.map(x => face(x.company)).join('')}<span class="value num">${p.type === 'remove' ? `${money(v)} duplicate` : `${money(v)} → ${stageOf(p.to).name}`}</span></div>
      <div class="acts">
        ${p.type === 'remove' ? `<button class="btn danger-soft" type="button" data-review="${p.id}">Review and remove</button>` : `<button class="btn primary" type="button" data-approve="${p.id}">Approve</button>`}
        <button class="btn quiet" type="button" data-review="${p.id}">Details</button>
        <button class="btn quiet" type="button" data-reject="${p.id}" aria-label="Say no to: ${p.title}">No</button>
      </div>
    </article>`;
  }).join('');
}
function drawTimeline() {
  $('#timeline').innerHTML = timeline.map(t => {
    const p = t.pid && proposals.find(x => x.id === t.pid);
    const state = p ? { wait: 'wait', ok: 'ok', no: 'no', stale: 'no' }[p.state] : t.state ?? '';
    const tag = p ? { wait: 'Waiting for your OK', ok: `Approved by you · ${p.decidedAt}`, no: 'You said no · nothing changed', stale: 'Closed unrun · you changed those deals yourself' }[p.state] : t.tag;
    return `<li class="${state} ${t.fresh ? 'fresh' : ''}"><time class="num">${t.at}</time><span class="dot"></span>
      <div class="what"><b>${esc(t.text)}</b>${t.sub ? `<span>${esc(t.sub)}</span>` : ''}${tag ? `<span class="tag">${tag}</span>` : ''}</div></li>`;
  }).join('');
  timeline.forEach(t => { t.fresh = false; });
}
function drawList() {
  $('#stageList').innerHTML = `<table><caption class="sr">Every deal on the model, by stage</caption><thead><tr><th scope="col">Stage</th><th scope="col">Deal</th><th scope="col">Value</th><th scope="col">Agent suggests</th></tr></thead><tbody>${
    stages.flatMap(s => s.deals.slice(0, 10).map(x => `<tr><td>${s.name}</td><td><button class="linkish" type="button" data-deal="${x.id}">${esc(x.company)}</button> — ${esc(x.title)}</td><td class="num">${money(x.value)}</td><td>${pendingFor(x.id)?.short ?? ''}</td></tr>`)).join('')}</tbody></table>`;
}

/* ---------- Phone view: one bar per stage, drawn to scale ---------- */
function createBars(root) {
  root.innerHTML = stages.map(s => `<div class="bar-row" data-stage="${s.key}"><div class="bar-label"><b>${s.name}</b><span class="num"></span></div>
    <div class="track"><i class="fill"></i><i class="pend"></i></div></div>`).join('');
  const rows = [...root.children];
  function layout() {
    const max = Math.max(...stages.map(s => s.v + Math.max(0, pendingDelta(s.key))));
    stages.forEach((s, i) => {
      const dv = pendingDelta(s.key), row = rows[i];
      row.querySelector('.num').textContent = `${money(s.v)} · ${s.n} deals${dv ? ` · ${dv > 0 ? '+' : '−'}${money(Math.abs(dv))} if approved` : ''}`;
      const solid = dv < 0 ? s.v + dv : s.v;
      row.querySelector('.fill').style.transform = `scaleX(${solid / max})`;
      const pend = row.querySelector('.pend');
      pend.className = `pend ${dv > 0 ? 'in' : dv < 0 ? 'out' : ''}`;
      pend.style.insetInlineStart = `${solid / max * 100}%`;
      pend.style.width = `${Math.abs(dv) / max * 100}%`;
    });
  }
  layout();
  return {
    layout,
    clearMark: () => {},
    highlight(pid) {
      const p = proposals.find(x => x.id === pid);
      const keys = p ? [p.from, p.to, p.type === 'remove' ? find(p.dealId).stage?.key : null] : [];
      rows.forEach(r => r.classList.toggle('lit', keys.includes(r.dataset.stage)));
    },
    showSuggestions() {}, resetView() {}, destroy() {},
  };
}

/* ---------- Choosing the view: 3D on wide screens with WebGL, bars on phones ---------- */
const wide = matchMedia('(min-width: 761px)');
let model = null, modelFailed = false;
function describe(deal, stage) {
  const p = pendingFor(deal.id);
  return `<b>${esc(deal.company)}</b><span>${esc(deal.title)} · ${stage.name}</span><br><span class="num">${money(deal.value)} · ${esc(deal.owner)}</span>${p ? `<br><span class="sug-note">Agent suggests: ${p.short}</span>` : ''}`;
}
async function startModel() {
  try {
    const { createScene } = await import('./scene.js');
    model = createScene({ canvas: $('#scene'), labelsEl: $('#labels'), tipEl: $('#tip'), stages, proposals, reduced, describe, onSelect: openDeal, onDrop: humanMove });
  } catch (err) {
    console.warn('3D model unavailable', err);
  }
  $('#skeleton').hidden = true;
  if (!model) {
    modelFailed = true;
    $('#modelNote').hidden = false;
    $('#modelNote').textContent = 'The 3D model can’t run on this device, so your pipeline is shown as bars. Everything else works the same.';
  }
  chooseView();
}
function chooseView() {
  const use3d = wide.matches && !modelFailed;
  $('.stage-card').dataset.mode = use3d ? 'model' : 'bars';
  $('#bars').hidden = use3d;
  if (use3d && !model) return startModel();
  view = use3d ? model : (bars ??= createBars($('#bars')));
  view.layout([]);
}
let bars = null;
wide.addEventListener('change', chooseView);

/* ---------- Review flow (one by one; changes play on the model when you close it) ---------- */
const review = $('#review');
let order = [], at = 0, decided = [];
function openReview(startId) {
  order = open();
  if (!order.length) return;
  at = Math.max(0, order.findIndex(p => p.id === startId));
  decided = [];
  showStep();
  review.showModal();
}
function dots() { $('#rv-dots').innerHTML = order.map((p, i) => `<i class="${p.decision === 'ok' ? 'ok' : i === at ? 'on' : ''}"></i>`).join(''); }
function showStep() {
  const p = order[at], body = $('#rv-body');
  $('#rv-step').textContent = `${at + 1} of ${order.length}`;
  dots();
  const deals = idsOf(p).map(id => find(id)).filter(x => x.deal);
  body.innerHTML = `
    <div><span class="rv-kind ${p.type}">${p.kind}</span><h3 id="rv-title">${p.title}</h3></div>
    <div class="changes">${deals.map(({ deal, stage }) => `<div class="change">${face(deal.company)}<div><b>${esc(deal.company)}</b><small>${esc(deal.title)} · ${money(deal.value)} · ${esc(deal.owner)}</small></div>
      <span class="moving">${p.type === 'move' ? `<span class="pill was">${stage.name}</span>${icon.arrow}<span class="pill to">${stageOf(p.to).name}</span>` : '<span class="pill gone">Removed</span>'}</span></div>`).join('')}</div>
    <div class="why"><span>Why the agent suggests this — its own words, so check them</span>${esc(p.reason)}</div>
    <ul class="effects">${p.effects.map(([k, t]) => `<li class="${k}">${k === 'warn' ? icon.warn : icon.check}${t}</li>`).join('')}</ul>
    ${p.type === 'remove' ? '<label class="confirm"><input type="checkbox" id="rv-confirm"> I understand the copy is deleted permanently</label>' : ''}
    <details class="tech"><summary>Technical details</summary>Agent call <code>#${p.call}</code> · action <code>${p.action}</code> · requested ${p.at} · runs only if these records haven’t changed since</details>
    <div class="rv-foot">
      <button type="button" class="btn quiet" data-rv="no">No, don’t do this</button>
      <button type="button" class="btn" data-rv="skip">Not now</button>
      <button type="button" class="btn ${p.type === 'remove' ? 'danger' : 'primary'}" data-rv="ok" ${p.type === 'remove' ? 'disabled' : ''}>${p.type === 'remove' ? 'Remove permanently' : 'Approve'}</button>
    </div>`;
  body.classList.remove('swap'); void body.offsetWidth; body.classList.add('swap');
}
function decide(ok) {
  const p = order[at];
  p.decision = ok ? 'ok' : 'no';
  decided.push(p);
  const n = idsOf(p).length;
  const done = !ok ? 'Nothing will change.' : p.type === 'move' ? `${n === 1 ? 'The deal moves' : `${n} deals move`} to ${stageOf(p.to).name}.` : 'The copy will be removed.';
  const next = order.find((x, j) => j > at && !x.decision);
  $('#rv-body').innerHTML = `<div class="result" role="status">${ok ? '<span class="seal"><svg viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg></span>' : ''}
    <h3 id="rv-title">${done}</h3><p>${ok ? 'It happens as soon as you close this, so you can watch it on your pipeline.' : 'The agent’s suggestion is closed. You can ask it again any time.'}</p>
    <div class="rv-foot">${next ? `<button type="button" class="btn primary" data-rv="next">Next: ${next.title}</button><button type="button" class="btn" data-rv="close">Close and watch</button>` : '<button type="button" class="btn primary" data-rv="close">Close and watch</button>'}</div></div>`;
  dots();
  $('#rv-body [data-rv]').focus();
}
review.addEventListener('click', e => {
  if (e.target === review) return review.close();
  const a = e.target.closest('[data-rv]')?.dataset.rv;
  if (a === 'ok' || a === 'no') decide(a === 'ok');
  if (a === 'skip' || a === 'next') {
    const i = order.findIndex((x, j) => j > at && !x.decision);
    if (i < 0) return review.close();
    at = i; showStep();
  }
  if (a === 'close') review.close();
});
review.addEventListener('change', e => { if (e.target.id === 'rv-confirm') review.querySelector('[data-rv="ok"]').disabled = !e.target.checked; });
review.addEventListener('close', () => {
  const plays = decided.splice(0).map(p => [p, p.decision]);
  order.forEach(p => delete p.decision);
  if (!plays.length) return;
  $('.stage-card').scrollIntoView({ behavior: reduced ? 'auto' : 'smooth', block: 'center' });
  plays.forEach(([p, ok], i) => setTimeout(() => (ok === 'ok' ? approve(p) : reject(p)), (reduced ? 0 : 450) + i * (reduced ? 0 : 1100)));
});

/* ---------- Deal sheet: opened from a block, a list row or a bar ---------- */
const dealSheet = $('#deal');
function openDeal(id) {
  const { deal, stage } = find(id);
  if (!deal) return;
  const p = pendingFor(id);
  dealSheet.dataset.id = id;
  $('#dealBody').innerHTML = `
    <header class="sheet-top"><div class="deal-id">${face(deal.company)}<div><h2 id="deal-title">${esc(deal.company)}</h2><p>${esc(deal.title)}</p></div></div>
      <button class="icon-btn" type="button" data-close aria-label="Close">${icon.close}</button></header>
    <div class="sheet-body">
      <dl class="facts"><div><dt>Value</dt><dd class="num">${money(deal.value)}</dd></div><div><dt>Stage</dt><dd>${stage.name}</dd></div><div><dt>Owner</dt><dd>${esc(deal.owner)}</dd></div></dl>
      ${p ? `<div class="why"><span>The agent suggests</span><b>${p.title}</b><button class="btn" type="button" data-review="${p.id}">Review the suggestion</button></div>` : ''}
      <form class="move-form" id="moveForm"><label for="moveTo">Move this deal to</label>
        <div class="row"><select id="moveTo">${stages.map(s => `<option value="${s.key}" ${s === stage ? 'selected disabled' : ''}>${s.name}</option>`).join('')}</select>
        <button class="btn primary" type="submit">Move</button></div>
        <p class="hint">You can also drag the block on the model. Moving it yourself never needs approval.</p></form>
    </div>`;
  dealSheet.showModal();
}
dealSheet.addEventListener('click', e => { if (e.target === dealSheet || e.target.closest('[data-close]')) dealSheet.close(); });
dealSheet.addEventListener('submit', e => {
  e.preventDefault();
  dealSheet.close();
  humanMove(Number(dealSheet.dataset.id), $('#moveTo').value);
});

/* ---------- Ask flow ---------- */
const ask = $('#ask'), log = $('#askLog'), say = $('#say'), send = $('#sendBtn');
let running = null, created = null;
const scripts = [
  { match: /create|lead for/i, steps: ['Checking for an existing Dana Okafor lead', 'Creating the lead in New'], finish: createdLead },
  { match: /duplicate/i, steps: ['Searching for leads with the same person and email'], reply: 'The only duplicate I found is Priya Natarajan’s, and removing it is already waiting for your OK.', action: ['Review it', () => { ask.close(); openReview('p2'); }] },
  { match: /./, steps: ['Searching deals in Negotiation', 'Checking the last contact on each'], reply: 'Summit Mining ($72k) and Vantage Air ($41k) have been in Negotiation for 34 and 41 days with no reply to the last email. Nothing needs your OK. Want me to draft follow-up tasks?' },
];
function bubble(cls, by, html) {
  const el = Object.assign(document.createElement('div'), { className: `msg ${cls}`, innerHTML: `<span class="by">${by}</span>${html}` });
  log.append(el);
  log.scrollTop = log.scrollHeight;
  return el;
}
function setRunning(on) {
  send.textContent = on ? 'Stop' : 'Send';
  send.classList.toggle('primary', !on);
  say.disabled = on;
  $('#suggest').hidden = on;
}
$('#composer').addEventListener('submit', e => {
  e.preventDefault();
  if (running) {
    running.timers.forEach(clearTimeout);
    running.plan.querySelector('.run')?.replaceWith(Object.assign(document.createElement('li'), { textContent: 'Stopped by you. Nothing after this ran.' }));
    running = null; setRunning(false); say.focus();
    return;
  }
  const text = say.value.trim();
  if (!text) return;
  bubble('', 'You', '<p></p>').querySelector('p').textContent = text;
  say.value = '';
  const s = scripts.find(x => x.match.test(text));
  const m = bubble('agent', 'Agent (AI)', `<ol class="plan">${s.steps.map(t => `<li>${t}</li>`).join('')}</ol>`);
  const items = [...m.querySelector('.plan').children];
  running = { plan: m.querySelector('.plan'), timers: [] };
  setRunning(true);
  items[0].classList.add('run');
  items.forEach((li, i) => running.timers.push(setTimeout(() => {
    li.classList.replace('run', 'ok');
    items[i + 1]?.classList.add('run');
    if (i < items.length - 1) return;
    running = null; setRunning(false);
    if (s.finish) return s.finish(m);
    m.insertAdjacentHTML('beforeend', `<p>${s.reply}</p>`);
    if (s.action) m.append(Object.assign(document.createElement('button'), { className: 'btn', type: 'button', textContent: s.action[0], onclick: s.action[1] }));
    log.scrollTop = log.scrollHeight;
  }, (reduced ? 0 : 850) * (i + 1))));
});
say.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); $('#composer').requestSubmit(); } });
$('#suggest').addEventListener('click', e => { const b = e.target.closest('[data-say]'); if (b) { say.value = b.dataset.say; $('#composer').requestSubmit(); } });
$('#askClose').addEventListener('click', () => ask.close());
ask.addEventListener('click', e => { if (e.target === ask) ask.close(); });

function createdLead(m) {
  const deal = { id: 4183, company: 'Dana Okafor', title: 'Fleet renewal', value: 2500, owner: 'Mara Chen', byAgent: true };
  created = deal;
  addDeal('new', deal);
  m.insertAdjacentHTML('beforeend', `<p>Done. Creating a lead doesn’t need your OK. It’s marked as made by the agent, and you can undo it for 10 minutes.</p>
    <div class="made"><b>Dana Okafor — Fleet renewal</b><span>$2,500 · New · owner Mara Chen</span><div class="row"><button class="btn" type="button" data-undo>Undo</button></div></div>`);
  timeline.push({ at: now(), text: 'Created a lead: Dana Okafor — Fleet renewal ($2,500)', sub: 'Ran without asking: creating is allowed for your role', tag: 'Made by the agent · undo for 10 min', state: 'ok', fresh: true, created: true });
  drawTimeline();
  if (!ask.open) toast('The agent created Dana Okafor — Fleet renewal.', 'Undo', undoCreate);
  log.scrollTop = log.scrollHeight;
}
function undoCreate() {
  if (!created) return;
  removeDeal(created.id);
  created = null;
  Object.assign(timeline.find(x => x.created), { tag: 'You undid this · the lead was removed', state: 'no' });
  document.querySelectorAll('[data-undo]').forEach(b => { b.disabled = true; b.textContent = 'Undone'; });
  drawTimeline();
  toast('Undone. The lead was removed.');
}

/* ---------- Toast ---------- */
let toastTimer;
function toast(text, label, fn) {
  const t = $('#toast');
  t.innerHTML = `<span></span>${label ? `<button class="btn" type="button">${label}</button>` : ''}`;
  t.firstChild.textContent = text;
  if (fn) t.querySelector('button').onclick = () => { t.hidden = true; fn(); };
  t.hidden = false;
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => { t.hidden = true; }, 9000);
}

/* ---------- Wiring ---------- */
document.addEventListener('click', e => {
  const t = e.target.closest('[data-review], [data-approve], [data-reject], [data-deal], [data-ask], [data-undo]');
  if (!t) return;
  if (t.dataset.review) { dealSheet.open && dealSheet.close(); openReview(t.dataset.review); }
  if (t.dataset.approve) {
    const p = proposals.find(x => x.id === t.dataset.approve);
    t.closest('.sug')?.classList.add('leaving'); // the card leaves first, then its blocks move
    setTimeout(() => approve(p), reduced ? 0 : 170);
  }
  if (t.dataset.reject) reject(proposals.find(p => p.id === t.dataset.reject));
  if (t.dataset.deal) openDeal(Number(t.dataset.deal));
  if (t.hasAttribute('data-ask')) { ask.showModal(); say.focus(); }
  if (t.hasAttribute('data-undo')) undoCreate();
});
const sugList = $('#sugList');
const lightFrom = e => view?.highlight(e.target.closest('[data-sug]')?.dataset.sug ?? null);
sugList.addEventListener('pointerover', lightFrom);
sugList.addEventListener('focusin', lightFrom);
sugList.addEventListener('pointerleave', () => view?.highlight(null));
sugList.addEventListener('focusout', e => { if (!sugList.contains(e.relatedTarget)) view?.highlight(null); });
$('#askBtn').addEventListener('click', () => { ask.showModal(); say.focus(); });
$('#reviewAll').addEventListener('click', () => openReview(open()[0]?.id));
$('#resetView').addEventListener('click', () => model?.resetView());
$('#listView').addEventListener('click', e => {
  const on = e.currentTarget.getAttribute('aria-pressed') !== 'true';
  e.currentTarget.setAttribute('aria-pressed', on);
  $('#stage').hidden = on;
  $('#stageList').hidden = !on;
});
$('#stage').addEventListener('pointerdown', () => { $('#dragHint').classList.add('used'); }, { once: true });
$('#themeBtn').addEventListener('click', () => {
  const r = document.documentElement;
  const dark = r.dataset.theme ? r.dataset.theme === 'dark' : matchMedia('(prefers-color-scheme: dark)').matches;
  r.dataset.theme = dark ? 'light' : 'dark';
});
$('#menuBtn').addEventListener('click', e => { const o = $('#rail').classList.toggle('open'); e.currentTarget.setAttribute('aria-expanded', o); });

drawHeadline(); drawTotals(); drawSugs(); drawTimeline(); drawList();
chooseView();
