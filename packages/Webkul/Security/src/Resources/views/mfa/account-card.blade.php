@php
    $currentAccountUser = $user ?? auth()->guard('user')->user();
    $mfaSetting = $currentAccountUser
        ? \Webkul\Security\Models\UserMfaSetting::query()->where('user_id', $currentAccountUser->id)->first()
        : null;
    $mfaActive = (bool) $mfaSetting?->mfa_enabled;
@endphp

<div class="box-shadow mt-4 rounded bg-white p-4 dark:bg-gray-900">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-base font-semibold text-gray-800 dark:text-white">
                @lang('security::app.mfa.title')
            </p>
            <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">
                @lang('security::app.mfa.subtitle')
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($mfaActive)
                <span class="inline-flex shrink-0 items-center rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300">
                    @lang('security::app.mfa.status-enabled')
                </span>
            @else
                <span class="inline-flex shrink-0 items-center rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                    @lang('security::app.mfa.status-disabled')
                </span>
            @endif

            <a
                href="{{ route('admin.security.mfa.setup') }}"
                class="secondary-button inline-flex"
            >
                @lang('security::app.mfa.title')
            </a>
        </div>
    </div>
</div>
