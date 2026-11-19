@verbatim
/*
 * The pipeline as a physical model: one lane per stage, one block per deal, height by value.
 * Waiting agent calls are dashed wireframes ("pencil"); approved changes move the real blocks ("ink").
 * The model draws what the data says: change the data, call layout(), and it animates there.
 * It renders on demand only, so an idle page costs nothing. three.js loads by exact URL (no import map),
 * so the page works inside the admin layout; if it can't load, the Desk falls back to bars.
 */
const THREE_URL = 'https://cdn.jsdelivr.net/npm/three@0.160.0/build/three.module.min.js';

async function createScene({ canvas, labelsEl, tipEl, stages, proposals, reduced, describe, onSelect, onDrop, dropText, shownText }) {
    const THREE = await import(THREE_URL);
    let renderer;
    try {
        renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true });
    } catch {
        return null;
    }

    const LANE_W = 2.1, GAP = 0.32, DEPTH = 4.2, BLOCK = { w: 0.78, d: 0.62 }, PER_ROW = 2, MAX = 10;
    const COLORS = { base: 0x1a2629, lane: 0x223236, won: 0x1f3c37, lost: 0x283033, deal: 0x5baea3, wonDeal: 0x84d4c6, lostDeal: 0x6e7a7c, pencil: 0x93bdf4, stamp: 0xf2907a, drop: 0x2c5a52 };
    const ease = t => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
    const out = t => 1 - Math.pow(1 - t, 3);

    renderer.setPixelRatio(Math.min(devicePixelRatio, 2));
    renderer.shadowMap.enabled = true;
    renderer.shadowMap.type = THREE.PCFSoftShadowMap;
    renderer.toneMapping = THREE.ACESFilmicToneMapping;
    renderer.toneMappingExposure = 0.95;
    renderer.outputColorSpace = THREE.SRGBColorSpace;

    // A rounded block whose base sits at y = 0.
    function roundedBlock(w, h, d, r = 0.06) {
        const s = new THREE.Shape(), x = -w / 2, y = -d / 2;
        s.moveTo(x + r, y); s.lineTo(x + w - r, y); s.quadraticCurveTo(x + w, y, x + w, y + r);
        s.lineTo(x + w, y + d - r); s.quadraticCurveTo(x + w, y + d, x + w - r, y + d);
        s.lineTo(x + r, y + d); s.quadraticCurveTo(x, y + d, x, y + d - r);
        s.lineTo(x, y + r); s.quadraticCurveTo(x, y, x + r, y);
        const b = Math.min(0.02, h / 4);
        const g = new THREE.ExtrudeGeometry(s, { depth: Math.max(0.001, h - 2 * b), bevelEnabled: true, bevelThickness: b, bevelSize: b, bevelSegments: 2, curveSegments: 4 });
        g.rotateX(-Math.PI / 2);
        g.translate(0, b, 0);
        return g;
    }
    // A soft studio for reflections: a dim room with three light panels.
    function studio() {
        const room = new THREE.Scene();
        room.add(new THREE.Mesh(new THREE.BoxGeometry(20, 12, 20), new THREE.MeshBasicMaterial({ color: 0x323a3e, side: THREE.BackSide })));
        [[0, 5.5, 0, 10, 0.2, 10, 1.6], [-9.6, 2, 0, 0.2, 6, 12, 1.1], [9.6, 3, -2, 0.2, 4, 8, 0.7]].forEach(([x, y, z, w, h, d, i]) => {
            const panel = new THREE.Mesh(new THREE.BoxGeometry(w, h, d), new THREE.MeshBasicMaterial({ color: new THREE.Color(i, i, i) }));
            panel.position.set(x, y, z);
            room.add(panel);
        });
        return room;
    }

    const scene = new THREE.Scene();
    const pmrem = new THREE.PMREMGenerator(renderer);
    scene.environment = pmrem.fromScene(studio(), 0.04).texture;
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
    const base = new THREE.Mesh(roundedBlock(width + 0.7, 0.24, DEPTH + 0.7, 0.12), new THREE.MeshStandardMaterial({ color: COLORS.base, roughness: 0.92 }));
    base.position.y = -0.26;
    base.receiveShadow = true;
    scene.add(base);
    const lanes = stages.map((s, i) => {
        const color = s.code === 'won' ? COLORS.won : s.code === 'lost' ? COLORS.lost : COLORS.lane;
        const lane = new THREE.Mesh(roundedBlock(LANE_W, 0.06, DEPTH, 0.03), new THREE.MeshStandardMaterial({ color, roughness: 0.85 }));
        lane.position.set(laneX(i), -0.03, 0);
        lane.receiveShadow = true;
        scene.add(lane);
        return lane;
    });

    const maxValue = () => Math.max(1, ...stages.flatMap(s => s.deals.map(d => d.value)));
    let tallest = maxValue();
    const heightOf = v => 0.18 + (v / tallest) * 3.2; // linear: twice the value stands twice as tall
    const slot = (li, n) => new THREE.Vector3(laneX(li) + ((n % PER_ROW) - 0.5) * (BLOCK.w + 0.18), 0.03, -DEPTH / 2 + 0.5 + Math.floor(n / PER_ROW) * (BLOCK.d + 0.14));
    const dealColor = code => (code === 'won' ? COLORS.wonDeal : code === 'lost' ? COLORS.lostDeal : COLORS.deal);
    const idsOf = p => p.deal_ids;
    const waiting = () => proposals.filter(p => p.state === 'wait');

    // A lane draws its largest deals, but always includes deals a waiting call touches or that just
    // moved, so nothing the person is deciding about, or just watched move, is left off the model.
    const recent = new Set();
    const pinned = () => new Set([...recent, ...waiting().flatMap(idsOf)]);
    const pick = (deals, pins) => {
        const keep = deals.filter(d => pins.has(d.id)).slice(0, MAX);
        return [...keep, ...deals.filter(d => !pins.has(d.id)).slice(0, MAX - keep.length)].sort((a, b) => b.value - a.value);
    };
    const shown = s => pick(s.deals, pinned());

    const tweens = [];
    const tween = (ms, delay, step, done, curve = ease) => tweens.push({ t0: performance.now() + (reduced ? 0 : delay), ms: reduced ? 0 : ms, step, done, curve });
    let frame = 0, dragging = false;
    const request = () => { if (!frame) frame = requestAnimationFrame(render); };

    // ---- Blocks ----
    const blocks = new Map(); // deal id -> { mesh, h, lane }
    function addBlock(deal, li, n, delay) {
        const h = heightOf(deal.value);
        const mesh = new THREE.Mesh(roundedBlock(BLOCK.w, h, BLOCK.d), new THREE.MeshStandardMaterial({ color: dealColor(stages[li].code), roughness: 0.38, metalness: 0.05, envMapIntensity: 0.55 }));
        mesh.position.copy(slot(li, n));
        mesh.scale.y = 0.001;
        mesh.castShadow = mesh.receiveShadow = true;
        mesh.userData.id = deal.id;
        scene.add(mesh);
        const b = { mesh, h, lane: li };
        blocks.set(deal.id, b);
        tween(650, delay, k => { mesh.scale.y = Math.max(0.001, k); }, null, out);
        return b;
    }
    stages.forEach((s, li) => shown(s).forEach((deal, n) => addBlock(deal, li, n, li * 70 + n * 25)));

    // ---- The agent's pencil ----
    const dashed = (w, h, d, color) => {
        const lines = new THREE.LineSegments(new THREE.EdgesGeometry(new THREE.BoxGeometry(w, h, d)), new THREE.LineDashedMaterial({ color, dashSize: 0.09, gapSize: 0.06, transparent: true }));
        lines.computeLineDistances();
        return lines;
    };
    const marks = new Map(); // call id -> { outlines, ghosts, fills, anchor, label, ids }
    function landing(p) {
        const to = stages.findIndex(s => s.id === p.to_stage_id);
        if (to < 0) return [];
        const moving = p.deal_ids.map(id => stages.flatMap(s => s.deals).find(x => x.id === id)).filter(Boolean);
        const lane = pick([...stages[to].deals, ...moving].sort((a, b) => b.value - a.value), new Set([...pinned(), ...p.deal_ids]));
        return moving.map(deal => ({ deal, at: slot(to, lane.indexOf(deal)) }));
    }
    function drawMark(p) {
        const outlines = [], ghosts = new THREE.Group(), fills = [];
        const anchor = new THREE.Vector3();
        let anchored = false;
        p.deal_ids.forEach(id => {
            const b = blocks.get(id);
            if (!b) return;
            // Attached to the block, so the outline follows it wherever it goes.
            const o = dashed(BLOCK.w + 0.06, b.h + 0.06, BLOCK.d + 0.06, p.type === 'move' ? COLORS.pencil : COLORS.stamp);
            o.position.y = b.h / 2;
            b.mesh.add(o);
            outlines.push(o);
            if (p.type === 'remove') { anchor.set(b.mesh.position.x, b.h + 0.45, b.mesh.position.z); anchored = true; }
        });
        if (p.type === 'move') {
            const spots = landing(p);
            spots.forEach(({ deal, at }) => {
                const h = blocks.get(deal.id)?.h ?? heightOf(deal.value);
                const ghost = new THREE.Group();
                ghost.add(dashed(BLOCK.w, h, BLOCK.d, COLORS.pencil));
                const fill = new THREE.Mesh(new THREE.BoxGeometry(BLOCK.w, h, BLOCK.d), new THREE.MeshBasicMaterial({ color: COLORS.pencil, transparent: true, opacity: 0.08, depthWrite: false }));
                ghost.add(fill);
                fills.push(fill);
                ghost.position.set(at.x, h / 2 + 0.03, at.z);
                ghosts.add(ghost);
                anchor.x += at.x / spots.length;
                anchor.z += at.z / spots.length;
                anchor.y = Math.max(anchor.y, h + 0.45);
                anchored = true;
            });
        }
        scene.add(ghosts);
        const label = document.createElement('span');
        label.className = `ghost-label ${p.type}`;
        label.textContent = p.short;
        label.hidden = !anchored;
        labelsEl.append(label);
        marks.set(p.id, { outlines, ghosts, fills, anchor, label, ids: p.deal_ids });
    }
    function dropMark(id, fade) {
        const m = marks.get(id);
        if (!m) return;
        marks.delete(id);
        const parts = [...m.outlines, m.ghosts];
        const gone = () => { m.outlines.forEach(o => o.parent?.remove(o)); scene.remove(m.ghosts); m.label.remove(); };
        if (!fade) return gone();
        m.label.classList.add('gone');
        tween(320, 0, k => parts.forEach(p => p.traverse(o => { if (o.material) o.material.opacity = (o.material.userData.o0 ??= o.material.opacity) * (1 - k); })), gone);
    }
    // Redraw every waiting call's marks from the current data (after a refresh or once moves settle).
    function syncMarks() {
        [...marks.keys()].forEach(id => dropMark(id, false));
        waiting().forEach(drawMark);
        request();
    }
    syncMarks();

    // ---- Labels ----
    const laneLabels = stages.map(() => labelsEl.appendChild(Object.assign(document.createElement('span'), { className: 'lane-label' })));
    const nameLabels = stages.map(() => labelsEl.appendChild(Object.assign(document.createElement('span'), { className: 'deal-name' })));
    function refreshLabels() {
        stages.forEach((s, i) => {
            laneLabels[i].innerHTML = '<b></b><span></span>';
            laneLabels[i].firstChild.textContent = s.name;
            laneLabels[i].lastChild.textContent = shownText(Math.min(s.deals.length, MAX), s.count);
            nameLabels[i].textContent = s.deals[0]?.company ?? '';
        });
    }
    refreshLabels();

    // ---- Camera ----
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
            if (top) project(new THREE.Vector3(top.mesh.position.x, top.mesh.position.y + top.h + 0.12, top.mesh.position.z), nameLabels[i]);
        });
        marks.forEach(m => project(m.anchor, m.label));
        // A company name gives way to a suggestion label, an earlier name, or the edge of the model.
        const box = labelsEl.getBoundingClientRect();
        const taken = [...marks.values()].filter(m => !m.label.hidden).map(m => m.label.getBoundingClientRect());
        nameLabels.forEach(el => {
            if (el.hidden) return;
            el.style.visibility = '';
            const r = el.getBoundingClientRect();
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
        goal.r = view.r = Math.max(15, (width + 3) / (camera.aspect * 2 * Math.tan(THREE.MathUtils.degToRad(camera.fov / 2))));
        request();
    }
    const ro = new ResizeObserver(resize);
    ro.observe(canvas.parentElement);
    resize();

    // ---- Pointer: drag the board to orbit, drag a block to move it, click a block to open it ----
    const ray = new THREE.Raycaster(), ndc = new THREE.Vector2(), board = new THREE.Plane(new THREE.Vector3(0, 1, 0), 0), hit = new THREE.Vector3();
    let orbit = null, grab = null, hovered = null;
    const root = canvas.parentElement;
    const aim = e => {
        const r = canvas.getBoundingClientRect();
        ndc.set((e.clientX - r.left) / r.width * 2 - 1, -((e.clientY - r.top) / r.height) * 2 + 1);
        ray.setFromCamera(ndc, camera);
    };
    const pickBlock = e => { aim(e); return ray.intersectObjects([...blocks.values()].map(b => b.mesh), false)[0]?.object ?? null; };
    const laneUnder = x => stages.findIndex((s, i) => Math.abs(x - laneX(i)) <= (LANE_W + GAP) / 2);
    const setDropLane = li => lanes.forEach((l, i) => l.material.emissive.setHex(i === li ? COLORS.drop : 0x000000));
    function setHover(mesh) {
        if (hovered === mesh) return;
        if (hovered && !hovered.userData.lit) hovered.material.emissive.setHex(0x000000);
        hovered = mesh;
        if (hovered && !hovered.userData.lit) hovered.material.emissive.setHex(0x16332f);
        root.style.cursor = mesh ? 'pointer' : '';
        request();
    }
    function showTip(mesh) {
        const b = blocks.get(mesh.userData.id), s = stages[b.lane];
        const deal = s.deals.find(x => x.id === mesh.userData.id);
        if (!deal) return;
        tipEl.innerHTML = describe(deal, s);
        tipEl.hidden = false;
        project(new THREE.Vector3(mesh.position.x, mesh.position.y + b.h + 0.05, mesh.position.z), tipEl);
    }
    root.addEventListener('pointerdown', e => {
        const target = pickBlock(e);
        if (e.pointerType === 'touch') { if (target) onSelect(target.userData.id); return; }
        root.setPointerCapture(e.pointerId);
        if (target) grab = { mesh: target, x: e.clientX, y: e.clientY, moved: false, lane: -1 };
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
            aim(e);
            if (ray.ray.intersectPlane(board, hit)) {
                grab.mesh.position.set(THREE.MathUtils.clamp(hit.x, -width / 2, width / 2), 0.6, THREE.MathUtils.clamp(hit.z, -DEPTH / 2, DEPTH / 2));
                grab.lane = laneUnder(grab.mesh.position.x);
                setDropLane(grab.lane);
                if (stages[grab.lane]) root.dataset.drop = dropText(stages[grab.lane].name);
            }
            return request();
        }
        const target = pickBlock(e);
        setHover(target);
        if (target) showTip(target); else tipEl.hidden = true;
    });
    root.addEventListener('pointerup', () => {
        if (grab) {
            const id = grab.mesh.userData.id, b = blocks.get(id);
            if (!grab.moved) onSelect(id);
            else if (grab.lane >= 0 && b && grab.lane !== b.lane) onDrop(id, stages[grab.lane].id);
            else layout([]);
            setDropLane(-1);
            delete root.dataset.drop;
        }
        grab = orbit = null;
        dragging = false;
        request();
    });
    root.addEventListener('pointerleave', () => { if (!grab) { setHover(null); tipEl.hidden = true; } });

    // ---- Layout: every block goes where the data says; moved ones arc there ----
    function layout(moved = []) {
        moved.forEach(id => recent.add(id));
        const present = new Set();
        let settle = 0;
        stages.forEach((s, li) => shown(s).forEach((deal, n) => {
            present.add(deal.id);
            const b = blocks.get(deal.id) ?? addBlock(deal, li, n, 300);
            const target = slot(li, n);
            const start = b.mesh.position.clone();
            const arc = moved.includes(deal.id);
            if (b.lane !== li) b.mesh.material.color.setHex(dealColor(s.code));
            b.lane = li;
            if (start.distanceTo(target) < 0.005) return;
            const i = Math.max(0, moved.indexOf(deal.id));
            const ms = arc ? 900 : 480, delay = arc ? i * 110 : 260;
            settle = Math.max(settle, ms + delay);
            tween(ms, delay, k => {
                b.mesh.position.lerpVectors(start, target, k);
                if (arc) b.mesh.position.y += Math.sin(k * Math.PI) * 1.4;
            });
        }));
        blocks.forEach((b, id) => {
            if (present.has(id)) return;
            blocks.delete(id);
            b.mesh.material.transparent = true;
            tween(560, 0, k => { b.mesh.scale.y = Math.max(0.001, 1 - k); b.mesh.material.opacity = 1 - k; }, () => scene.remove(b.mesh));
        });
        refreshLabels();
        // Once everything has landed, put the pencil marks back where the blocks now are.
        setTimeout(syncMarks, reduced ? 0 : settle + 40);
        request();
    }

    return {
        layout,
        syncMarks,
        clearMark(id) { dropMark(id, true); request(); },
        highlight(id) {
            blocks.forEach(b => { b.mesh.userData.lit = false; if (b.mesh !== hovered) b.mesh.material.emissive.setHex(0x000000); });
            marks.forEach((m, mid) => {
                const on = mid === id;
                m.label.classList.toggle('lit', on);
                m.fills.forEach(f => { f.material.opacity = on ? 0.24 : 0.08; });
                if (on) m.ids.forEach(i => { const b = blocks.get(i); if (b) { b.mesh.userData.lit = true; b.mesh.material.emissive.setHex(0x1d3f63); } });
            });
            request();
        },
        resetView() { Object.assign(goal, HOME); request(); },
        destroy() { ro.disconnect(); renderer.dispose(); pmrem.dispose(); labelsEl.replaceChildren(); },
    };
}
@endverbatim
