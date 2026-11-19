@php
    $locale = app()->getLocale();
    $isRtl = in_array($locale, ['ar', 'fa'], true);
    $i18n = [
        'pairInvalid' => trans('tma::app.bot.paired-invalid'),
        'browserHint' => trans('tma::app.app.pair-browser-hint'),
        'questsEmpty' => trans('tma::app.app.quests-empty'),
        'slipReason' => trans('tma::app.app.slip-reason'),
        'slipRecheck' => trans('tma::app.app.slip-recheck'),
        'editToggle' => trans('tma::app.app.edit-toggle'),
        'editCancel' => trans('tma::app.app.edit-cancel'),
        'feedbackPlaceholder' => trans('tma::app.app.feedback-placeholder'),
        'distillRule' => trans('tma::app.app.distill-rule'),
        'signInk' => trans('tma::app.app.sign-ink'),
        'signPasskey' => trans('tma::app.app.sign-passkey'),
        'reject' => trans('tma::app.app.reject'),
        'receiptBadge' => trans('tma::app.app.receipt-badge'),
        'rejectedBadge' => trans('tma::app.app.rejected-badge'),
        'captureSaved' => trans('tma::app.app.capture-saved'),
        'chainEmpty' => trans('tma::app.app.chain-empty'),
        'chainHeight' => trans('tma::app.app.chain-height'),
        'chainHash' => trans('tma::app.app.chain-hash'),
        'chainEpoch' => trans('tma::app.app.chain-epoch'),
        'passkeyAdded' => trans('tma::app.passkeys.added'),
        'passkeyUnsupported' => trans('tma::app.passkeys.unsupported'),
        'retry' => trans('tma::app.app.retry'),
    ];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', $locale) }}" dir="{{ $isRtl ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>@lang('tma::app.app.title')</title>
    @include('tma::partials.styles')
</head>
<body>
<div class="lum-tma" id="tma-root">
    <header class="topbar">
        <div>
            <div class="brandmark"><span class="hex">L</span> @lang('tma::app.app.title')</div>
            <div class="kicker">@lang('tma::app.app.disclosure')</div>
        </div>
        <div class="user-pill" id="tma-user-pill"></div>
    </header>

    <div id="tma-error" class="alert" hidden>
        <span id="tma-error-text"></span>
        <button type="button" class="btn" id="tma-retry-btn">@lang('tma::app.app.retry')</button>
    </div>

    <section id="view-loading" aria-busy="true">
        <div class="skeleton"></div>
        <div class="skeleton" style="margin-top: 10px;"></div>
    </section>

    <section id="view-pair" class="sheet" hidden>
        <h1>@lang('tma::app.app.pair-title')</h1>
        <p class="sub">@lang('tma::app.app.pair-subtitle')</p>
        <p class="sub" id="tma-pair-hint" hidden></p>
        <form id="tma-pair-form" style="display: grid; gap: 10px;">
            <input
                type="text"
                id="tma-pair-code"
                class="code-input"
                maxlength="8"
                autocomplete="one-time-code"
                placeholder="@lang('tma::app.app.pair-placeholder')"
                required
            />
            <button type="submit" class="btn primary" id="tma-pair-submit">@lang('tma::app.app.pair-submit')</button>
        </form>
    </section>

    <main id="view-quests" hidden>
        <h2 style="margin-bottom: 10px;">@lang('tma::app.app.quests-heading')</h2>
        <div id="quests-list" style="display: grid; gap: 12px;"></div>
    </main>

    <main id="view-capture" hidden>
        <div class="sheet">
            <h2>@lang('tma::app.app.capture-heading')</h2>
            <p class="sub">@lang('tma::app.app.capture-subtitle')</p>
            <form id="tma-capture-form" style="display: grid; gap: 10px;">
                <textarea id="tma-capture-text" maxlength="4000" placeholder="@lang('tma::app.app.capture-placeholder')" required></textarea>
                <div class="btn-row">
                    <button type="button" class="btn" id="tma-dictate-btn" hidden>@lang('tma::app.app.capture-dictate')</button>
                    <button type="submit" class="btn primary" id="tma-capture-submit">@lang('tma::app.app.capture-submit')</button>
                </div>
            </form>
            <div id="tma-capture-receipt" hidden></div>
        </div>
    </main>

    <main id="view-vault" hidden>
        <h2 style="margin-bottom: 10px;">@lang('tma::app.app.vault-heading')</h2>
        <div class="stats-grid">
            <div class="stat-cell">
                <span class="stat-val num" id="stat-streak">0</span>
                <span class="stat-lbl">@lang('tma::app.app.streak-days')</span>
            </div>
            <div class="stat-cell">
                <span class="stat-val num" id="stat-signed">0</span>
                <span class="stat-lbl">@lang('tma::app.app.signed-today')</span>
            </div>
            <div class="stat-cell">
                <span class="stat-val num" id="stat-captured">0</span>
                <span class="stat-lbl">@lang('tma::app.app.captured-today')</span>
            </div>
        </div>

        <div class="sheet" style="margin-top: 12px;">
            <div style="display: flex; justify-content: space-between; align-items: center; gap: 8px; flex-wrap: wrap;">
                <h2>@lang('tma::app.app.chain-heading')</h2>
                <button type="button" class="btn" id="tma-enroll-passkey">@lang('tma::app.app.passkey-enroll')</button>
            </div>
            <div id="vault-chain" class="params-box"></div>
        </div>

        <div class="sheet" style="margin-top: 12px;">
            <h2>@lang('tma::app.app.receipts-heading')</h2>
            <div id="vault-receipts" style="display: grid; gap: 8px;"></div>
        </div>
    </main>

    <nav class="tabbar" id="tma-tabbar" role="tablist" hidden>
        <button type="button" role="tab" class="tab-btn" data-tab="quests" aria-selected="true">
            @lang('tma::app.app.tab-quests') <span class="badge" id="quests-count">0</span>
        </button>
        <button type="button" role="tab" class="tab-btn" data-tab="capture" aria-selected="false">
            @lang('tma::app.app.tab-capture')
        </button>
        <button type="button" role="tab" class="tab-btn" data-tab="vault" aria-selected="false">
            @lang('tma::app.app.tab-vault')
        </button>
    </nav>
</div>

<script>
(() => {
    const I18N = @json($i18n);
    const API_BASE = @json(url('/api/tma'));
    const root = document.getElementById('tma-root');
    const tg = window.Telegram?.WebApp;

    const state = {
        token: null,
        linkTicket: null,
        initData: '',
        passkeys: [],
        slips: [],
        tab: localStorage.getItem('tma_ui:active_tab') || 'quests',
    };

    const syncTheme = () => {
        if (tg?.colorScheme === 'dark' || tg?.colorScheme === 'light') {
            root.dataset.theme = tg.colorScheme;
        }
    };

    const haptic = (kind) => {
        try {
            tg?.HapticFeedback?.notificationOccurred?.(kind);
        } catch (_) {}
    };

    const readInitData = () => {
        if (tg?.initData) return tg.initData;
        if (window.location.hash.length > 1) {
            const hashParams = new URLSearchParams(window.location.hash.slice(1));
            const fromHash = hashParams.get('tgWebAppData');
            if (fromHash) return fromHash;
        }
        return '';
    };

    const toBytes = (b64url) => {
        const pad = '='.repeat((4 - (b64url.length % 4)) % 4);
        const bin = atob((b64url + pad).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from(bin, (c) => c.charCodeAt(0));
    };

    const toBase64Url = (buf) => {
        const bytes = new Uint8Array(buf);
        let s = '';
        for (let i = 0; i < bytes.length; i++) s += String.fromCharCode(bytes[i]);
        return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
    };

    const showError = (msg) => {
        const box = document.getElementById('tma-error');
        const txt = document.getElementById('tma-error-text');
        txt.textContent = msg;
        box.hidden = ! msg;
    };

    const api = async (path, method = 'GET', body = null) => {
        const headers = { 'Accept': 'application/json' };
        if (state.token) headers['Authorization'] = 'Bearer ' + state.token;
        if (body !== null) headers['Content-Type'] = 'application/json';
        try {
            const res = await fetch(API_BASE + path, {
                method,
                headers,
                body: body !== null ? JSON.stringify(body) : null,
            });
            if (res.status === 401 && path !== '/session') {
                state.token = null;
            }
            const json = await res.json().catch(() => ({}));
            return { status: res.status, ok: res.ok, data: json };
        } catch (err) {
            return {
                status: 0,
                ok: false,
                data: { error: { message: err?.message || I18N.retry } },
            };
        }
    };

    const switchTab = (tab) => {
        state.tab = ['quests', 'capture', 'vault'].includes(tab) ? tab : 'quests';
        localStorage.setItem('tma_ui:active_tab', state.tab);
        ['quests', 'capture', 'vault'].forEach((name) => {
            document.getElementById('view-' + name).hidden = name !== state.tab;
        });
        document.querySelectorAll('.tab-btn').forEach((btn) => {
            btn.setAttribute('aria-selected', String(btn.dataset.tab === state.tab));
        });
    };

    const el = (tag, cls, text) => {
        const node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = text;
        return node;
    };

    const renderSlips = () => {
        const list = document.getElementById('quests-list');
        list.replaceChildren();
        document.getElementById('quests-count').textContent = String(state.slips.length);

        if (! state.slips.length) {
            const empty = el('div', 'sheet sub', I18N.questsEmpty);
            list.appendChild(empty);
            return;
        }

        state.slips.forEach((slip) => {
            const card = el('article', 'slip');
            const meta = el('div', 'meta');
            meta.appendChild(el('span', 'mono num', '#' + slip.id + ' · ' + slip.action));
            card.appendChild(meta);

            const paramsView = el('div', 'params-box', JSON.stringify(slip.params || {}, null, 2));
            card.appendChild(paramsView);

            if (slip.reason) {
                const reason = el('div', 'reason');
                reason.appendChild(el('small', '', I18N.slipReason));
                reason.appendChild(document.createTextNode('“' + slip.reason + '”'));
                card.appendChild(reason);
            }

            card.appendChild(el('div', 'checkline', I18N.slipRecheck));

            const drawer = el('div', 'edit-drawer');
            drawer.hidden = true;
            const editArea = el('textarea', '', JSON.stringify(slip.params || {}, null, 2));
            const noteInput = el('input');
            noteInput.type = 'text';
            noteInput.placeholder = I18N.feedbackPlaceholder;
            const distillWrap = el('label', 'chk');
            const distillBox = el('input');
            distillBox.type = 'checkbox';
            distillWrap.append(distillBox, document.createTextNode(I18N.distillRule));
            drawer.append(editArea, noteInput, distillWrap);
            card.appendChild(drawer);

            const actions = el('div', 'btn-row');
            const signLabel = state.passkeys.length && window.PublicKeyCredential ? I18N.signPasskey : I18N.signInk;
            const signBtn = el('button', 'btn primary', signLabel);
            signBtn.type = 'button';
            const editBtn = el('button', 'btn', I18N.editToggle);
            editBtn.type = 'button';
            const rejectBtn = el('button', 'btn danger', I18N.reject);
            rejectBtn.type = 'button';

            editBtn.addEventListener('click', () => {
                drawer.hidden = ! drawer.hidden;
                editBtn.textContent = drawer.hidden ? I18N.editToggle : I18N.editCancel;
            });

            const submitDecision = async (approve) => {
                signBtn.disabled = true;
                rejectBtn.disabled = true;
                showError('');
                let editedParams = null;

                if (! drawer.hidden) {
                    try {
                        editedParams = JSON.parse(editArea.value);
                    } catch (_) {
                        showError('Invalid JSON in edited parameters.');
                        signBtn.disabled = false;
                        rejectBtn.disabled = false;
                        return;
                    }
                }

                let assertion = null;
                if (approve && state.passkeys.length && window.PublicKeyCredential && navigator.credentials?.get) {
                    const ch = await api('/passkeys/challenge', 'POST', { call_id: slip.id });
                    if (! ch.ok) {
                        showError(ch.data?.error?.message || 'Unable to request Passkey challenge.');
                        signBtn.disabled = false;
                        rejectBtn.disabled = false;
                        return;
                    }
                    try {
                        const cred = await navigator.credentials.get({
                            publicKey: {
                                challenge: toBytes(ch.data.challenge),
                                rpId: ch.data.rp_id,
                                allowCredentials: (ch.data.allow_credentials || []).map((c) => ({
                                    type: 'public-key',
                                    id: toBytes(c.id),
                                })),
                                userVerification: 'preferred',
                                timeout: 60000,
                            },
                        });
                        assertion = {
                            credential_id: toBase64Url(cred.rawId),
                            client_data_json: toBase64Url(cred.response.clientDataJSON),
                            authenticator_data: toBase64Url(cred.response.authenticatorData),
                            signature: toBase64Url(cred.response.signature),
                        };
                    } catch (err) {
                        showError(err?.message || 'Passkey signature cancelled.');
                        signBtn.disabled = false;
                        rejectBtn.disabled = false;
                        return;
                    }
                }

                const res = await api('/calls/' + slip.id + '/decide', 'POST', {
                    approve,
                    edited_params: editedParams,
                    feedback_note: noteInput.value.trim() || null,
                    distill_rule: distillBox.checked,
                    webauthn_assertion: assertion,
                });

                if (! res.ok || res.data?.ok === false) {
                    haptic('error');
                    showError(res.data?.error?.message || I18N.retry);
                    signBtn.disabled = false;
                    rejectBtn.disabled = false;
                    return;
                }

                haptic('success');
                card.classList.add(approve ? 'inked' : 'rejected');
                const badge = el('div', 'receipt-pill num', (approve ? I18N.receiptBadge : I18N.rejectedBadge) + slip.id);
                actions.replaceWith(badge);
                drawer.hidden = true;
                state.slips = state.slips.filter((s) => s.id !== slip.id);
                document.getElementById('quests-count').textContent = String(state.slips.length);
            };

            signBtn.addEventListener('click', () => submitDecision(true));
            rejectBtn.addEventListener('click', () => submitDecision(false));

            actions.append(signBtn, editBtn, rejectBtn);
            card.appendChild(actions);
            list.appendChild(card);
        });
    };

    const renderVault = (data) => {
        document.getElementById('stat-streak').textContent = String(data.streak?.streak_days ?? 0);
        document.getElementById('stat-signed').textContent = String(data.streak?.signed_today ?? 0);
        document.getElementById('stat-captured').textContent = String(data.streak?.captured_today ?? 0);

        const chainEl = document.getElementById('vault-chain');
        const head = data.vault?.head;
        const epoch = data.vault?.latest_epoch;
        if (! head) {
            chainEl.textContent = I18N.chainEmpty;
        } else {
            const lines = [
                I18N.chainHeight + ': #' + head.height + ' (' + (head.alg || 'ed25519') + ')',
                I18N.chainHash + ': ' + String(head.block_hash || '').slice(0, 24) + '…',
            ];
            if (epoch) {
                lines.push(I18N.chainEpoch + ': #' + epoch.id + ' · ' + (epoch.anchor_proof || epoch.merkle_root?.slice(0, 18)));
            }
            chainEl.textContent = lines.join('\n');
            chainEl.style.whiteSpace = 'pre-line';
        }

        const recBox = document.getElementById('vault-receipts');
        recBox.replaceChildren();
        const receipts = data.recent_receipts || [];
        if (! receipts.length) {
            recBox.appendChild(el('p', 'meta', I18N.chainEmpty));
        } else {
            receipts.forEach((r) => {
                recBox.appendChild(el('div', 'meta mono num', '#' + r.id + ' · ' + r.action + ' · ' + r.status));
            });
        }
    };

    const loadBriefing = async () => {
        showError('');
        const res = await api('/briefing');
        document.getElementById('view-loading').hidden = true;
        if (! res.ok) {
            showError(res.data?.error?.message || 'Failed to load desk briefing.');
            return;
        }
        state.slips = res.data.pending_slips || [];
        state.passkeys = res.data.passkeys || state.passkeys;
        renderSlips();
        renderVault(res.data);
        document.getElementById('view-pair').hidden = true;
        document.getElementById('tma-tabbar').hidden = false;
        switchTab(state.tab);
    };

    const bootSession = async (pairCode = '') => {
        showError('');
        const payload = {};
        if (pairCode && state.linkTicket) {
            payload.link_ticket = state.linkTicket;
            payload.pair_code = pairCode;
        } else {
            payload.init_data = state.initData;
            if (pairCode) payload.pair_code = pairCode;
        }

        if (! payload.init_data && ! payload.link_ticket) {
            document.getElementById('view-loading').hidden = true;
            document.getElementById('view-pair').hidden = false;
            const hint = document.getElementById('tma-pair-hint');
            hint.textContent = I18N.browserHint;
            hint.hidden = false;
            return;
        }

        const res = await api('/session', 'POST', payload);
        document.getElementById('view-loading').hidden = true;

        if (res.data?.link_ticket) state.linkTicket = res.data.link_ticket;

        if (! res.ok) {
            document.getElementById('view-pair').hidden = false;
            showError(res.data?.error?.message || I18N.pairInvalid);
            return;
        }

        if (! res.data.linked) {
            document.getElementById('view-pair').hidden = false;
            return;
        }

        state.token = res.data.token;
        state.passkeys = res.data.passkeys || [];
        document.getElementById('tma-user-pill').textContent = res.data.user?.name || '';
        await loadBriefing();
    };

    document.getElementById('tma-pair-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = document.getElementById('tma-pair-code').value.trim().toUpperCase();
        if (! code) return;
        await bootSession(code);
    });

    const captureArea = document.getElementById('tma-capture-text');
    captureArea.value = localStorage.getItem('tma_draft:intake_text') || '';
    captureArea.addEventListener('input', () => localStorage.setItem('tma_draft:intake_text', captureArea.value));

    document.getElementById('tma-capture-form').addEventListener('submit', async (e) => {
        e.preventDefault();
        const text = captureArea.value.trim();
        if (! text) return;
        const btn = document.getElementById('tma-capture-submit');
        btn.disabled = true;
        showError('');
        const res = await api('/intake', 'POST', { text });
        btn.disabled = false;
        if (! res.ok) {
            haptic('error');
            showError(res.data?.error?.message || 'Could not capture note.');
            return;
        }
        haptic('success');
        captureArea.value = '';
        localStorage.removeItem('tma_draft:intake_text');
        const box = document.getElementById('tma-capture-receipt');
        box.className = 'receipt-pill num';
        box.textContent = I18N.captureSaved + (res.data.call_id || '—');
        box.hidden = false;
        await loadBriefing();
        switchTab('capture');
    });

    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (SpeechRec) {
        const dicBtn = document.getElementById('tma-dictate-btn');
        dicBtn.hidden = false;
        dicBtn.addEventListener('click', () => {
            const rec = new SpeechRec();
            rec.onresult = (ev) => {
                const transcript = ev.results?.[0]?.[0]?.transcript || '';
                if (transcript) {
                    captureArea.value = (captureArea.value ? captureArea.value + ' ' : '') + transcript;
                    localStorage.setItem('tma_draft:intake_text', captureArea.value);
                }
            };
            rec.start();
        });
    }

    const enrollBtn = document.getElementById('tma-enroll-passkey');
    enrollBtn.addEventListener('click', async () => {
        if (! window.PublicKeyCredential || ! navigator.credentials?.create) {
            showError(I18N.passkeyUnsupported);
            return;
        }
        enrollBtn.disabled = true;
        showError('');
        try {
            const ch = await api('/passkeys/challenge', 'POST', {});
            if (! ch.ok) {
                haptic('error');
                showError(ch.data?.error?.message || 'Unable to request Passkey challenge.');
                return;
            }
            const cred = await navigator.credentials.create({
                publicKey: {
                    challenge: toBytes(ch.data.challenge),
                    rp: { name: 'Luminara CRM', id: ch.data.rp_id },
                    user: {
                        id: toBytes(ch.data.user.id),
                        name: ch.data.user.name,
                        displayName: ch.data.user.displayName,
                    },
                    pubKeyCredParams: [{ type: 'public-key', alg: -7 }, { type: 'public-key', alg: -8 }],
                    authenticatorSelection: { userVerification: 'preferred' },
                    timeout: 60000,
                },
            });
            const spki = cred.response.getPublicKey ? cred.response.getPublicKey() : null;
            const attestation = cred.response.attestationObject ? toBase64Url(cred.response.attestationObject) : '';
            const coseAlg = cred.response.getPublicKeyAlgorithm ? cred.response.getPublicKeyAlgorithm() : -7;
            if (! attestation && ! spki) {
                showError(I18N.passkeyUnsupported);
                return;
            }
            const saved = await api('/passkeys', 'POST', {
                credential_id: toBase64Url(cred.rawId),
                alg: coseAlg === -8 ? 'ed25519' : 'es256',
                public_key: spki ? toBase64Url(spki) : '',
                attestation_object: attestation,
                client_data_json: toBase64Url(cred.response.clientDataJSON),
                label: 'Telegram Passkey',
            });
            if (! saved.ok) {
                haptic('error');
                showError(saved.data?.error?.message || 'Passkey enrollment failed.');
                return;
            }
            haptic('success');
            await loadBriefing();
            switchTab('vault');
        } catch (err) {
            if (err?.message) showError(err.message);
        } finally {
            enrollBtn.disabled = false;
        }
    });

    document.querySelectorAll('.tab-btn').forEach((btn) => {
        btn.addEventListener('click', () => switchTab(btn.dataset.tab));
    });

    document.getElementById('tma-retry-btn').addEventListener('click', () => {
        state.token ? loadBriefing() : bootSession();
    });

    syncTheme();
    tg?.onEvent?.('themeChanged', syncTheme);
    tg?.ready?.();
    tg?.expand?.();

    state.initData = readInitData();
    bootSession();
})();
</script>
</body>
</html>
