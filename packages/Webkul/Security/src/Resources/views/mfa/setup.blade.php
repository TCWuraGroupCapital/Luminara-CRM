<x-admin::layouts>
    <x-slot:title>
        @lang('security::app.mfa.title')
    </x-slot>

    <div class="mx-auto flex max-w-4xl flex-col gap-5 py-2">
        <x-admin::breadcrumbs name="security.mfa" />

        <div class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 pb-4 dark:border-gray-800">
                <div>
                    <h1 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">
                        @lang('security::app.mfa.title')
                    </h1>
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        @lang('security::app.mfa.subtitle')
                    </p>
                </div>

                @if ($isEnabled)
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                        @lang('security::app.mfa.status-enabled')
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                        @lang('security::app.mfa.status-disabled')
                    </span>
                @endif
            </div>

            @if ($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            @if (! empty($freshRecoveryCodes) && is_array($freshRecoveryCodes))
                <div class="mt-5 rounded-lg border border-amber-300 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
                    <h2 class="text-sm font-bold text-amber-900 dark:text-amber-200">
                        @lang('security::app.mfa.recovery-title')
                    </h2>
                    <p class="mt-1 text-xs text-amber-800 dark:text-amber-300">
                        @lang('security::app.mfa.recovery-warning')
                    </p>
                    <div class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4">
                        @foreach ($freshRecoveryCodes as $code)
                            <code class="rounded border border-amber-300 bg-white px-2.5 py-1.5 text-center font-mono text-xs font-semibold tabular-nums text-gray-900 dark:border-amber-800 dark:bg-gray-900 dark:text-white">
                                {{ $code }}
                            </code>
                        @endforeach
                    </div>
                </div>
            @endif

            @if (! $isEnabled)
                <div class="mt-5 grid gap-5 md:grid-cols-12">
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-800 dark:bg-gray-950 md:col-span-5">
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                            @lang('security::app.mfa.secret-label')
                        </div>
                        <code class="mt-2 block break-all rounded border border-gray-200 bg-white p-3 font-mono text-sm font-semibold tracking-wider text-gray-900 dark:border-gray-800 dark:bg-gray-900 dark:text-teal-300">
                            {{ trim(chunk_split((string) $pendingSecret, 4, ' ')) }}
                        </code>
                        <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                            <span class="font-semibold">@lang('security::app.mfa.uri-label'):</span>
                            <div class="mt-1 break-all font-mono text-[11px]">{{ $provisioningUri }}</div>
                        </div>
                    </div>

                    <form
                        method="POST"
                        action="{{ route('admin.security.mfa.enable') }}"
                        class="grid gap-4 md:col-span-7"
                    >
                        @csrf

                        <div class="grid gap-1">
                            <label for="mfa-current-password" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                @lang('security::app.mfa.current-password') *
                            </label>
                            <input
                                id="mfa-current-password"
                                name="current_password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            />
                        </div>

                        <div class="grid gap-1">
                            <label for="mfa-totp-code" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                @lang('security::app.mfa.totp-code') *
                            </label>
                            <input
                                id="mfa-totp-code"
                                name="code"
                                type="text"
                                inputmode="numeric"
                                pattern="[0-9]{6}"
                                maxlength="6"
                                required
                                autocomplete="one-time-code"
                                placeholder="123456"
                                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm tabular-nums text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                            />
                        </div>

                        <div>
                            <button type="submit" class="primary-button">
                                @lang('security::app.mfa.enable-btn')
                            </button>
                        </div>
                    </form>
                </div>
            @else
                <div class="mt-5 grid gap-4">
                    <div class="text-sm text-gray-700 dark:text-gray-300">
                        @lang('security::app.mfa.remaining-codes', ['count' => $remainingRecoveryCodes])
                    </div>

                    @if (! empty($requireMfaPolicy))
                        <div class="rounded-md border border-gray-200 bg-gray-50 px-4 py-3 text-xs text-gray-600 dark:border-gray-800 dark:bg-gray-950 dark:text-gray-400">
                            @lang('security::app.mfa.enrollment-required')
                        </div>
                    @else
                        <form
                            method="POST"
                            action="{{ route('admin.security.mfa.disable') }}"
                            class="grid max-w-md gap-3 rounded-lg border border-gray-200 p-4 dark:border-gray-800"
                        >
                            @csrf

                            <div class="grid gap-1">
                                <label for="disable-password" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    @lang('security::app.mfa.current-password') *
                                </label>
                                <input
                                    id="disable-password"
                                    name="current_password"
                                    type="password"
                                    required
                                    autocomplete="current-password"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                            </div>

                            <div class="grid gap-1">
                                <label for="disable-code" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                                    @lang('security::app.mfa.code-or-recovery') *
                                </label>
                                <input
                                    id="disable-code"
                                    name="code"
                                    type="text"
                                    required
                                    autocomplete="one-time-code"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 font-mono text-sm text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                                />
                            </div>

                            <div>
                                <button type="submit" class="secondary-button text-red-600 dark:text-red-400">
                                    @lang('security::app.mfa.disable-btn')
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @endif
        </div>
    </div>
</x-admin::layouts>
