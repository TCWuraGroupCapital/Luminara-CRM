// The pipeline as a physical model: one lane per stage, one block per deal, height by value.
// Agent suggestions are dashed wireframes ("pencil"); approved changes move the real blocks ("ink").
// The model only draws what the data says: call layout() after changing the data and it animates there.
// Renders on demand only, so an idle page costs nothing.
import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { RoomEnvironment } from 'three/addons/environments/RoomEnvironment.js';

const LANE_W = 2.1, GAP = 0.32, DEPTH = 4.2, BLOCK = { w: 0.78, d: 0.62 }, PER_ROW = 2, MAX = 10;
const COLORS = { base: 0x1a2629, lane: 0x223236, won: 0x1f3c37, lost: 0x283033, deal: 0x5baea3, wonDeal: 0x84d4c6, lostDeal: 0x6e7a7c, pencil: 0x93bdf4, stamp: 0xf2907a, drop: 0x2c5a52 };
const ease = t => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const out = t => 1 - Math.pow(1 - t, 3);

export function createScene({ canvas, labelsEl, tipEl, stages, proposals, reduced, describe, onSelect, onDrop }) {
  let renderer;
  try {
    renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
  } catch {
    return null;
  }
  renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
  renderer.shadowMap.enabled = true;
  renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  renderer.toneMapping = THREE.ACESFilmicToneMapping;
  renderer.toneMappingExposure = 0.95;
  renderer.outputColorSpace = THREE.SRGBColorSpace;

  const scene = new THREE.Scene();
  // A soft studio environment gives the blocks real reflections instead of flat colour.
  const pmrem = new THREE.PMREMGenerator(renderer);
  scene.environment = pmrem.fromScene(new RoomEnvironment(), 0.04).texture;
  const camera = new THREE.PerspectiveCamera(30, 2, 0.1, 100);
  scene.add(new THREE.HemisphereLight(0xe2f1f1, 0x0a1213, 0.45));
  const key = new THREE.DirectionalLight(0xfff6ea, 2.0);
  key.position.set(-5, 10, 7);
  key.castShadow = true;
  key.shadow.mapSize.set(2048, 2048);
  Object.assign(key.shadow.camera, { left: -10, right: 10, top: 7, bottom: -7, near: 1, far: 32 });
  key.shadow.radius = 5;
  key.shadow.bias = -0.0004;
  scene.add(key);
  const rim = new THREE.DirectionalLight(0x9cc8ff, 0.7);
  rim.position.set(6, 5, -8);
  scene.add(rim);

  const width = stages.length * LANE_W + (stages.length - 1) * GAP;
  const laneX = i => -width / 2 + LANE_W / 2 + i * (LANE_W + GAP);
  const base = new THREE.Mesh(new RoundedBoxGeometry(width + 0.7, 0.24, DEPTH + 0.7, 4, 0.12), new THREE.MeshStandardMaterial({ color: COLORS.base, roughness: 0.92 }));
  base.position.y = -0.14;
  base.receiveShadow = true;
  scene.add(base);
  const lanes = stages.map((s, i) => {
    const color = s.key === 'won' ? COLORS.won : s.key === 'lost' ? COLORS.lost : COLORS.lane;
    const lane = new THREE.Mesh(new RoundedBoxGeometry(LANE_W, 0.06, DEPTH, 3, 0.03), new THREE.MeshStandardMaterial({ color, roughness: 0.85 }));
    lane.position.set(laneX(i), 0, 0);
    lane.receiveShadow = true;
    scene.add(lane);
    return lane;
  });

  const maxValue = Math.max(...stages.flatMap(s => s.deals.map(d => d.value)));
  const heightOf = v => 0.18 + (v / maxValue) * 3.2; // linear: twice the value stands twice as tall
  const slot = (li, n) => new THREE.Vector3(laneX(li) + ((n % PER_ROW) - 0.5) * (BLOCK.w + 0.18), 0, -DEPTH / 2 + 0.5 + Math.floor(n / PER_ROW) * (BLOCK.d + 0.14));
  const dealColor = k => (k === 'won' ? COLORS.wonDeal : k === 'lost' ? COLORS.lostDeal : COLORS.deal);
  // A lane draws its largest deals, but always includes deals with a pending suggestion or that just
  // moved, so nothing the person is deciding about, or just watched move, is ever left off the model.
  const recent = new Set();
  const pinned = () => new Set([...recent, ...proposals.filter(p => p.state === 'wait').flatMap(p => (p.type === 'move' ? p.dealIds : [p.dealId]))]);
  const pick10 = (deals, pins) => {
    const keep = deals.filter(d => pins.has(d.id)).slice(0, MAX);
    return [...keep, ...deals.filter(d => !pins.has(d.id)).slice(0, MAX - keep.length)].sort((a, b) => b.value - a.value);
  };
  const shown = s => pick10(s.deals, pinned());

  // ---- Tweens and on-demand rendering ----
  const tweens = [];
  const tween = (ms, delay, step, done, curve = ease) => tweens.push({ t0: performance.now() + (reduced ? 0 : delay), ms: reduced ? 0 : ms, step, done, curve });
  let frame = 0;
  const request = () => { if (!frame) frame = requestAnimationFrame(render); };

  // ---- Blocks ----
  const blocks = new Map(); // deal id -> { mesh, h, lane }
  const blockGeo = new RoundedBoxGeometry(BLOCK.w, 1, BLOCK.d, 3, 0.06);
  function addBlock(deal, li, n, delay) {
    const h = heightOf(deal.value);
    const mesh = new THREE.Mesh(blockGeo, new THREE.MeshStandardMaterial({ color: dealColor(stages[li].key), roughness: 0.38, metalness: 0.05, envMapIntensity: 0.55 }));
    const p = slot(li, n);
    mesh.position.set(p.x, 0.03, p.z);
    mesh.scale.y = 0.001;
    mesh.castShadow = mesh.receiveShadow = true;
    mesh.userData.id = deal.id;
    scene.add(mesh);
    const b = { mesh, h, lane: li };
    blocks.set(deal.id, b);
    tween(650, delay, k => { mesh.scale.y = Math.max(0.001, h * k); mesh.position.y = mesh.scale.y / 2 + 0.03; }, null, out);
    return b;
  }
  stages.forEach((s, li) => shown(s).forEach((deal, n) => addBlock(deal, li, n, li * 70 + n * 25)));

  // ---- The agent's pencil: dashed outlines on blocks, wireframe ghosts where they would land ----
  const dashed = (w, h, d, color) => {
    const lines = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(w, h, d)), new THREE.LineDashedMaterial({ color, dashSize: 0.09, gapSize: 0.06, transparent: true }));
    lines.computeLineDistances();
    return lines;
  };
  const marks = new Map(); // proposal id -> { group, anchor, label, ghosts, ids }
  function landing(p) { // where each moved deal would sit, given the destination lane sorted by value
    const to = stages.findIndex(s => s.key === p.to);
    const moving = p.dealIds.map(id => stages.flatMap(s => s.deals).find(x => x.id === id));
    const lane = pick10([...stages[to].deals, ...moving].sort((a, b) => b.value - a.value), new Set([...pinned(), ...p.dealIds]));
    return p.dealIds.map(id => slot(to, lane.findIndex(x => x.id === id)));
  }
  function drawProposal(p) {
    const group = new THREE.Group(), ghosts = [];
    const ids = p.type === 'move' ? p.dealIds : [p.dealId];
    const anchor = new THREE.Vector3(0, 0, 0);
    ids.forEach(id => {
      const b = blocks.get(id);
      if (!b) return;
      const outline = dashed(BLOCK.w + 0.06, b.h + 0.06, BLOCK.d + 0.06, p.type === 'move' ? COLORS.pencil : COLORS.stamp);
      outline.position.set(b.mesh.position.x, b.h / 2 + 0.03, b.mesh.position.z);
      group.add(outline);
      if (p.type === 'remove') anchor.set(b.mesh.position.x, b.h + 0.45, b.mesh.position.z);
    });
    if (p.type === 'move') {
      landing(p).forEach((target, i) => {
        const b = blocks.get(p.dealIds[i]);
        const h = b?.h ?? 0.6;
        const ghost = new THREE.Group();
        ghost.add(dashed(BLOCK.w, h, BLOCK.d, COLORS.pencil));
        const fill = new THREE.Mesh(new THREE.BoxGeometry(BLOCK.w, h, BLOCK.d), new THREE.MeshBasicMaterial({ color: COLORS.pencil, transparent: true, opacity: 0.08, depthWrite: false }));
        ghost.add(fill);
        ghosts.push(fill);
        ghost.position.set(target.x, h / 2 + 0.03, target.z);
        group.add(ghost);
        anchor.x += target.x / p.dealIds.length;
        anchor.z += target.z / p.dealIds.length;
        anchor.y = Math.max(anchor.y, h + 0.45);
      });
    }
    scene.add(group);
    const label = document.createElement('span');
    label.className = `ghost-label ${p.type}`;
    label.textContent = p.short;
    labelsEl.append(label);
    marks.set(p.id, { group, anchor, label, ghosts, ids });
  }
  proposals.filter(p => p.state === 'wait').forEach(drawProposal);

  // ---- HTML labels: lane names, and the company on the biggest deal in each lane ----
  const laneLabels = stages.map(() => labelsEl.appendChild(Object.assign(document.createElement('span'), { className: 'lane-label' })));
  const nameLabels = stages.map(() => labelsEl.appendChild(Object.assign(document.createElement('span'), { className: 'deal-name' })));
  function refreshLabels() {
    stages.forEach((s, i) => {
      laneLabels[i].innerHTML = `<b>${s.name}</b>${Math.min(s.deals.length, MAX)} of ${s.n} shown`;
      nameLabels[i].textContent = s.deals[0]?.company ?? '';
    });
  }
  refreshLabels();

  // ---- Camera: orbit within limits, eased toward a goal ----
  const HOME = { az: -0.2, pol: 0.86 };
  const view = { ...HOME, r: 21 }, goal = { ...HOME, r: 21 };
  const center = new THREE.Vector3(0, 0.25, 0.2);
  function placeCamera() {
    camera.position.set(center.x + view.r * Math.sin(view.pol) * Math.sin(view.az), center.y + view.r * Math.cos(view.pol), center.z + view.r * Math.sin(view.pol) * Math.cos(view.az));
    camera.lookAt(center);
  }

  const v = new THREE.Vector3();
  function project(p, el) {
    v.copy(p).project(camera);
    const r = canvas.getBoundingClientRect();
    el.style.transform = `translate(${(v.x + 1) / 2 * r.width}px, ${(1 - v.y) / 2 * r.height}px)`;
  }
  function render(now = performance.now()) {
    frame = 0;
    let busy = false;
    for (let i = tweens.length - 1; i >= 0; i--) {
      const t = tweens[i];
      const k = t.ms ? Math.min(1, Math.max(0, (now - t.t0) / t.ms)) : 1;
      t.step(t.curve(k));
      if (k >= 1) { tweens.splice(i, 1); t.done?.(); } else busy = true;
    }
    for (const k of ['az', 'pol', 'r']) {
      const d = goal[k] - view[k];
      view[k] += reduced ? d : d * 0.14;
      if (Math.abs(d) > 0.0005) busy = true;
    }
    placeCamera();
    renderer.render(scene, camera);
    stages.forEach((s, i) => {
      project(new THREE.Vector3(laneX(i), 0, DEPTH / 2 + 0.55), laneLabels[i]);
      const top = blocks.get(s.deals[0]?.id);
      nameLabels[i].hidden = !top;
      if (top) project(new THREE.Vector3(top.mesh.position.x, top.mesh.position.y + top.h / 2 + 0.12, top.mesh.position.z), nameLabels[i]);
    });
    marks.forEach(m => project(m.anchor, m.label));
    // A company name gives way to any suggestion label or earlier name it would overlap.
    const taken = [...marks.values()].map(m => m.label.getBoundingClientRect());
    nameLabels.forEach(el => {
      if (el.hidden) return;
      el.style.visibility = '';
      const r = el.getBoundingClientRect();
      const box = labelsEl.getBoundingClientRect();
      const clash = r.left < box.left || r.right > box.right || taken.some(t => r.left < t.right && r.right > t.left && r.top < t.bottom && r.bottom > t.top);
      el.style.visibility = clash ? 'hidden' : '';
      if (!clash) taken.push(r);
    });
    if (busy || dragging) request();
  }

  function resize() {
    const { clientWidth: w, clientHeight: h } = canvas.parentElement;
    if (!w || !h) return;
    renderer.setSize(w, h, false);
    camera.aspect = w / h;
    camera.updateProjectionMatrix();
    // Distance that fits the board's width, plus a margin, at this aspect ratio.
    goal.r = view.r = Math.max(15, (width + 3) / (camera.aspect * 2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2))));
    request();
  }
  const ro = new ResizeObserver(resize);
  ro.observe(canvas.parentElement);
  resize();

  // ---- Pointer: drag empty space to orbit, drag a block to move it, click a block to open it ----
  const ray = new THREE.Raycaster(), ndc = new THREE.Vector2(), board = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0), hitPoint = new THREE.Vector3();
  let orbit = null, grab = null, hovered = null, dragging = false;
  const pick = e => {
    const r = canvas.getBoundingClientRect();
    ndc.set((e.clientX - r.left) / r.width * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
    ray.setFromCamera(ndc, camera);
    return ray.intersectObjects([...blocks.values()].map(b => b.mesh))[0]?.object ?? null;
  };
  const laneUnder = x => stages.findIndex((s, i) => Math.abs(x - laneX(i)) <= (LANE_W + GAP) / 2);
  const setDropLane = li => lanes.forEach((l, i) => l.material.emissive.setHex(i === li ? COLORS.drop : 0x000000));
  const setHover = mesh => {
    if (hovered === mesh) return;
    if (hovered && !hovered.userData.lit) hovered.material.emissive.setHex(0x000000);
    hovered = mesh;
    if (hovered && !hovered.userData.lit) hovered.material.emissive.setHex(0x16332f);
    canvas.parentElement.style.cursor = mesh ? 'pointer' : '';
    request();
  };
  function showTip(mesh) {
    const b = blocks.get(mesh.userData.id);
    const s = stages[b.lane];
    tipEl.innerHTML = describe(s.deals.find(x => x.id === mesh.userData.id), s);
    tipEl.hidden = false;
    project(new THREE.Vector3(mesh.position.x, mesh.position.y + b.h / 2 + 0.05, mesh.position.z), tipEl);
  }
  const root = canvas.parentElement;
  root.addEventListener('pointerdown', e => {
    const hit = pick(e);
    if (e.pointerType === 'touch') { if (hit) onSelect(hit.userData.id); return; } // touch scrolls; a tap opens the deal
    root.setPointerCapture(e.pointerId);
    if (hit) grab = { mesh: hit, x: e.clientX, y: e.clientY, moved: false };
    else orbit = { x: e.clientX, y: e.clientY, az: goal.az, pol: goal.pol };
  });
  root.addEventListener('pointermove', e => {
    if (orbit) {
      goal.az = THREE.MathUtils.clamp(orbit.az - (e.clientX - orbit.x) * 0.006, -0.85, 0.85);
      goal.pol = THREE.MathUtils.clamp(orbit.pol - (e.clientY - orbit.y) * 0.004, 0.62, 1.22);
      tipEl.hidden = true;
      return request();
    }
    if (grab) {
      if (!grab.moved && Math.hypot(e.clientX - grab.x, e.clientY - grab.y) < 6) return;
      grab.moved = dragging = true;
      tipEl.hidden = true;
      const r = canvas.getBoundingClientRect();
      ndc.set((e.clientX - r.left) / r.width * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
      ray.setFromCamera(ndc, camera);
      if (ray.ray.intersectPlane(board, hitPoint)) {
        const b = blocks.get(grab.mesh.userData.id);
        grab.mesh.position.set(THREE.MathUtils.clamp(hitPoint.x, -width / 2, width / 2), b.h / 2 + 0.6, THREE.MathUtils.clamp(hitPoint.z, -DEPTH / 2, DEPTH / 2));
        grab.lane = laneUnder(grab.mesh.position.x);
        setDropLane(grab.lane);
        root.dataset.drop = stages[grab.lane]?.name ?? '';
      }
      return request();
    }
    const hit = pick(e);
    setHover(hit);
    if (hit) showTip(hit); else tipEl.hidden = true;
  });
  root.addEventListener('pointerup', () => {
    if (grab) {
      const id = grab.mesh.userData.id, b = blocks.get(id);
      if (!grab.moved) onSelect(id);
      else if (grab.lane >= 0 && grab.lane !== b.lane) onDrop(id, stages[grab.lane].key);
      else layout([]); // dropped where it was: settle back into its slot
      setDropLane(-1);
      delete root.dataset.drop;
    }
    grab = orbit = null;
    dragging = false;
    request();
  });
  root.addEventListener('pointerleave', () => { if (!grab) { setHover(null); tipEl.hidden = true; } });

  // ---- Data-driven layout: every block goes where the data says, moved ones arc there ----
  function layout(moved = []) {
    moved.forEach(id => recent.add(id));
    const present = new Set();
    stages.forEach((s, li) => shown(s).forEach((deal, n) => {
      present.add(deal.id);
      const b = blocks.get(deal.id) ?? addBlock(deal, li, n, 300);
      const target = slot(li, n);
      target.y = b.h / 2 + 0.03;
      const start = b.mesh.position.clone();
      const arc = moved.includes(deal.id);
      const changedLane = b.lane !== li;
      b.lane = li;
      if (changedLane) b.mesh.material.color.setHex(dealColor(s.key));
      if (start.distanceTo(target) < 0.005) return;
      const i = moved.indexOf(deal.id);
      tween(arc ? 900 : 480, arc ? i * 110 : 260, k => {
        b.mesh.position.lerpVectors(start, target, k);
        if (arc) b.mesh.position.y += Math.sin(k * Math.PI) * 1.4;
      });
    }));
    blocks.forEach((b, id) => {
      if (present.has(id)) return;
      blocks.delete(id);
      b.mesh.material.transparent = true;
      tween(560, 0, k => { b.mesh.scale.y = Math.max(0.001, b.h * (1 - k)); b.mesh.position.y = b.mesh.scale.y / 2 + 0.03; b.mesh.material.opacity = 1 - k; }, () => scene.remove(b.mesh));
    });
    refreshLabels();
    request();
  }

  function clearMark(id) {
    const m = marks.get(id);
    if (!m) return;
    marks.delete(id);
    m.label.classList.add('gone');
    tween(320, 0, k => m.group.traverse(o => { if (o.material) o.material.opacity = (o.material.userData.o0 ??= o.material.opacity) * (1 - k); }), () => { scene.remove(m.group); m.label.remove(); });
    request();
  }

  request();
  return {
    layout,
    clearMark,
    // Light up one suggestion's blocks and ghosts while its card is hovered or focused.
    highlight(pid) {
      blocks.forEach(b => { b.mesh.userData.lit = false; if (b.mesh !== hovered) b.mesh.material.emissive.setHex(0x000000); });
      marks.forEach((m, id) => {
        const on = id === pid;
        m.label.classList.toggle('lit', on);
        m.ghosts.forEach(g => { g.material.opacity = on ? 0.24 : 0.08; });
        if (on) m.ids.forEach(i => { const b = blocks.get(i); if (b) { b.mesh.userData.lit = true; b.mesh.material.emissive.setHex(0x1d3f63); } });
      });
      request();
    },
    showSuggestions(on) { marks.forEach(m => { m.group.visible = on; m.label.hidden = !on; }); request(); },
    resetView() { Object.assign(goal, HOME); request(); },
    destroy() { ro.disconnect(); renderer.dispose(); pmrem.dispose(); labelsEl.replaceChildren(); },
  };
}
