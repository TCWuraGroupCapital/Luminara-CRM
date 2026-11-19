<x-admin::layouts>
    <x-slot:title>
        @lang('tma::app.pairing.title')
    </x-slot>

    @include('agent::partials.styles')

    @pushOnce('styles')
        <style>
            .lum-agent .tma-code-box {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 16px;
                padding: 18px 20px;
                border-radius: var(--r-2);
                background: var(--sheet);
                border: 1.5px dashed var(--pencil-line);
            }
            .lum-agent .tma-code-token {
                font-family: var(--mono);
                font-size: 28px;
                font-weight: 600;
                letter-spacing: 0.14em;
                color: var(--ink);
                font-variant-numeric: tabular-nums;
            }
            .lum-agent .tma-card {
                display: grid;
                gap: 14px;
                padding: 18px 20px;
                border-radius: var(--r-2);
                background: var(--sheet);
                box-shadow: inset 0 0 0 1px var(--rule);
            }
            .lum-agent .tma-row {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
            }
            .lum-agent .tma-input {
                min-height: 36px;
                padding: 0 12px;
                border-radius: var(--r-1);
                border: 1px solid var(--rule-2);
                background: var(--sheet);
                color: var(--ink);
                font-size: 14px;
            }
            .lum-agent .tma-badge-ok {
                display: inline-flex;
                align-items: center;
                gap: 6px;
                font-size: 13px;
                font-weight: 500;
                color: var(--ok);
            }
            .lum-agent .tma-badge-ok::before {
                content: "";
                width: 7px;
                height: 7px;
                border-radius: 50%;
                background: var(--ok);
            }
        </style>
    @endPushOnce

    <x-admin::breadcrumbs name="tma.pairing" />

    <div class="lum-agent" v-pre>
        <div class="head">
            <div>
                <h1>@lang('tma::app.pairing.title')</h1>
                <p>@lang('tma::app.pairing.subtitle')</p>
            </div>

            <a href="{{ route('tma.app') }}" target="_blank" rel="noopener" class="btn">
                @lang('tma::app.pairing.preview-tma')
            </a>
        </div>

        @if (is_array($pairing) && ! empty($pairing['code']))
            <section aria-labelledby="tma-issued-code">
                <div class="tma-code-box">
                    <div>
                        <p id="tma-issued-code" class="meta">@lang('tma::app.pairing.code-label')</p>
                        <div class="tma-code-token">{{ $pairing['code'] }}</div>
                        <p class="who">@lang('tma::app.pairing.code-instructions', ['code' => $pairing['code']])</p>
                    </div>

                    @if (! empty($pairing['deep_link']))
                        <a href="{{ $pairing['deep_link'] }}" target="_blank" rel="noopener" class="btn sign">
                            @lang('tma::app.pairing.open-bot')
                        </a>
                    @endif
                </div>
            </section>
        @endif

        <section aria-labelledby="tma-connection-heading">
            <h2 id="tma-connection-heading">@lang('tma::app.pairing.telegram-heading')</h2>

            <div class="tma-card">
                @if ($identity)
                    <div class="tma-row">
                        <div>
                            <span class="tma-badge-ok">@lang('tma::app.pairing.status-linked')</span>
                            <p class="who">
                                @lang('tma::app.pairing.linked-as', [
                                    'name' => $identity->telegram_username ? '@'.$identity->telegram_username : ($identity->first_name ?? '#'.$identity->telegram_user_id),
                                    'id' => (string) $identity->telegram_user_id,
                                ])
                                · <span class="num">@lang('tma::app.pairing.linked-since', ['time' => core()->formatDate($identity->linked_at, 'd M Y H:i')])</span>
                            </p>
                        </div>

                        <div class="actions">
                            <form method="POST" action="{{ route('admin.tma.pairing.code') }}">
                                @csrf
                                <button type="submit" class="btn">@lang('tma::app.pairing.reissue-code')</button>
                            </form>

                            <form method="POST" action="{{ route('admin.tma.pairing.destroy') }}">
                                @csrf
                                @method('DELETE')
                                <button
                                    type="submit"
                                    class="btn danger"
                                    aria-pressed="false"
                                    data-tma-confirm="@lang('tma::app.pairing.confirm-unlink')"
                                >
                                    @lang('tma::app.pairing.unlink')
                                </button>
                            </form>

                            <span class="note" aria-live="polite"></span>
                        </div>
                    </div>
                @else
                    <div class="tma-row">
                        <div>
                            <p class="state pending_approval">@lang('tma::app.pairing.status-unlinked')</p>
                            <p class="who">@lang('tma::app.menu.tma-info')</p>
                        </div>

                        <form method="POST" action="{{ route('admin.tma.pairing.code') }}">
                            @csrf
                            <button type="submit" class="btn sign">@lang('tma::app.pairing.issue-code')</button>
                        </form>
                    </div>
                @endif
            </div>
        </section>

        <section aria-labelledby="tma-passkeys-heading">
            <h2 id="tma-passkeys-heading">
                @lang('tma::app.passkeys.heading')
                <small>@lang('tma::app.passkeys.subheading')</small>
            </h2>

            <div class="tma-card">
                <div class="tma-row">
                    <input
                        type="text"
                        id="tma-passkey-label"
                        class="tma-input"
                        maxlength="80"
                        placeholder="@lang('tma::app.passkeys.label-placeholder')"
                    />

                    <button
                        type="button"
                        id="tma-register-passkey"
                        class="btn sign"
                        data-challenge="{{ $webauthnChallenge }}"
                        data-rp-id="{{ $rpId }}"
                        data-user-id="{{ \Webkul\Tma\Support\WebAuthnVerifier::base64UrlEncode((string) $user->id) }}"
                        data-user-name="{{ $user->getAttribute('email') }}"
                        data-user-display="{{ $user->getAttribute('name') }}"
                        data-challenge-url="{{ route('admin.tma.passkeys.challenge') }}"
                        data-post-url="{{ route('admin.tma.passkeys.store') }}"
                        data-csrf="{{ csrf_token() }}"
                    >
                        @lang('tma::app.passkeys.register-device')
                    </button>
                </div>

                <p id="tma-passkey-status" class="note" aria-live="polite"></p>
            </div>

            <div class="ledger-wrap" style="margin-top: 12px;">
                <table class="ledger">
                    <thead>
                        <tr>
                            <th scope="col">@lang('tma::app.passkeys.col-label')</th>
                            <th scope="col">@lang('tma::app.passkeys.col-alg')</th>
                            <th scope="col" class="hide-sm">@lang('tma::app.passkeys.col-credential')</th>
                            <th scope="col">@lang('tma::app.passkeys.col-uses')</th>
                            <th scope="col" class="hide-sm">@lang('tma::app.passkeys.col-last-used')</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($passkeys as $passkey)
                            <tr>
                                <td>{{ $passkey->label }}</td>
                                <td class="mono">{{ strtoupper($passkey->alg) }}</td>
                                <td class="mono hide-sm">{{ \Illuminate\Support\Str::limit($passkey->credential_id, 24) }}</td>
                                <td class="num">{{ $passkey->sign_count }}</td>
                                <td class="num hide-sm nowrap">
                                    {{ $passkey->last_used_at ? core()->formatDate($passkey->last_used_at, 'd M H:i') : '—' }}
                                </td>
                                <td>
                                    <form method="POST" action="{{ route('admin.tma.passkeys.destroy', $passkey->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn danger">@lang('tma::app.passkeys.remove')</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="empty">@lang('tma::app.passkeys.empty')</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @pushOnce('scripts')
        <script type="module">
            const toBytes = (b64url) => {
                const pad = '='.repeat((4 - (b64url.length % 4)) % 4);
                const bin = atob((b64url + pad).replace(/-/g, '+').replace(/_/g, '/'));
                return Uint8Array.from(bin, (c) => c.charCodeAt(0));
            };

            const toBase64Url = (buf) => {
                const bytes = new Uint8Array(buf);
                let str = '';
                for (let i = 0; i < bytes.length; i++) str += String.fromCharCode(bytes[i]);
                return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
            };

            document.addEventListener('click', (event) => {
                const button = event.target.closest('[data-tma-confirm]');
                if (! button || button.getAttribute('aria-pressed') === 'true') return;

                event.preventDefault();
                const original = button.textContent;
                const note = button.closest('.actions')?.querySelector('.note');

                const disarm = () => {
                    button.setAttribute('aria-pressed', 'false');
                    button.textContent = original;
                    if (note) note.textContent = '';
                };

                button.setAttribute('aria-pressed', 'true');
                button.textContent = button.dataset.tmaConfirm;
                if (note) note.textContent = @json(trans('tma::app.pairing.confirm-hint'));

                button.addEventListener('keydown', (e) => e.key === 'Escape' && disarm(), { once: true });
                button.addEventListener('blur', disarm, { once: true });
            });

            const regBtn = document.getElementById('tma-register-passkey');
            const statusEl = document.getElementById('tma-passkey-status');

            regBtn?.addEventListener('click', async () => {
                if (! window.PublicKeyCredential || ! navigator.credentials?.create) {
                    if (statusEl) statusEl.textContent = @json(trans('tma::app.passkeys.unsupported'));
                    return;
                }

                regBtn.disabled = true;
                if (statusEl) statusEl.textContent = '';

                try {
                    if (regBtn.dataset.challengeUrl) {
                        const chRes = await fetch(regBtn.dataset.challengeUrl, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': regBtn.dataset.csrf,
                            },
                        }).catch(() => null);
                        const chData = chRes && chRes.ok ? await chRes.json().catch(() => null) : null;
                        if (chData?.challenge) regBtn.dataset.challenge = chData.challenge;
                        if (chData?.rp_id) regBtn.dataset.rpId = chData.rp_id;
                    }

                    const cred = await navigator.credentials.create({
                        publicKey: {
                            challenge: toBytes(regBtn.dataset.challenge),
                            rp: { name: 'Luminara CRM', id: regBtn.dataset.rpId },
                            user: {
                                id: toBytes(regBtn.dataset.userId),
                                name: regBtn.dataset.userName,
                                displayName: regBtn.dataset.userDisplay,
                            },
                            pubKeyCredParams: [
                                { type: 'public-key', alg: -7 },
                                { type: 'public-key', alg: -8 },
                            ],
                            authenticatorSelection: { userVerification: 'preferred' },
                            timeout: 60000,
                        },
                    });

                    const spki = cred.response.getPublicKey ? cred.response.getPublicKey() : null;
                    const attestation = cred.response.attestationObject ? toBase64Url(cred.response.attestationObject) : '';
                    const coseAlg = cred.response.getPublicKeyAlgorithm ? cred.response.getPublicKeyAlgorithm() : -7;

                    if (! attestation && ! spki) {
                        throw new Error(@json(trans('tma::app.passkeys.unsupported')));
                    }

                    const labelInput = document.getElementById('tma-passkey-label');
                    const res = await fetch(regBtn.dataset.postUrl, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': regBtn.dataset.csrf,
                        },
                        body: JSON.stringify({
                            credential_id: toBase64Url(cred.rawId),
                            alg: coseAlg === -8 ? 'ed25519' : 'es256',
                            public_key: spki ? toBase64Url(spki) : '',
                            attestation_object: attestation,
                            client_data_json: toBase64Url(cred.response.clientDataJSON),
                            label: labelInput?.value?.trim() || 'Desktop Passkey',
                        }),
                    });

                    if (res.ok) {
                        window.location.reload();
                        return;
                    }

                    const err = await res.json().catch(() => ({}));
                    if (err?.challenge) regBtn.dataset.challenge = err.challenge;
                    if (statusEl) statusEl.textContent = err?.error?.message || @json(trans('tma::app.passkeys.invalid'));
                } catch (error) {
                    if (statusEl) statusEl.textContent = error?.message || '';
                } finally {
                    regBtn.disabled = false;
                }
            });
        </script>
    @endPushOnce
</x-admin::layouts>
