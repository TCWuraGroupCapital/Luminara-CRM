@php
    $workspace = app(\Webkul\Workspace\WorkspaceManager::class)->current();

    $user = auth()->guard('user')->user();

    /**
     * Shown to administrators of a workspace during its first two weeks, until they hide it.
     */
    $show = $workspace !== null
        && $user?->role?->permission_type === 'all'
        && $workspace->created_at?->gt(now()->subDays(14))
        && ! request()->cookie('lum_started_' . $workspace->id);

    $steps = [
        ['lead', 'admin.leads.create'],
        ['team', 'admin.settings.users.index'],
        ['mfa', 'admin.security.mfa.setup'],
        ['form', 'admin.settings.web_forms.index'],
        ['agent', 'admin.agent.briefing.index'],
    ];
@endphp

@if ($show)
    <section
        id="lum-getting-started"
        class="mt-3.5 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        aria-labelledby="lum-getting-started-title"
    >
        <div class="flex items-start justify-between gap-4">
            <div>
                <h2
                    id="lum-getting-started-title"
                    class="text-base font-semibold text-gray-900 dark:text-white"
                >
                    @lang('workspace::app.onboarding.title', ['name' => $workspace->name])
                </h2>

                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    @lang('workspace::app.onboarding.intro')
                </p>
            </div>

            <button
                type="button"
                class="secondary-button flex-none"
                onclick="document.cookie = 'lum_started_{{ $workspace->id }}=1; path=/; max-age=31536000; SameSite=Lax'; document.getElementById('lum-getting-started').remove();"
            >
                @lang('workspace::app.onboarding.dismiss')
            </button>
        </div>

        <ol class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-5">
            @foreach ($steps as $index => [$key, $routeName])
                @continue(! \Illuminate\Support\Facades\Route::has($routeName))

                <li>
                    <a
                        href="{{ route($routeName) }}"
                        class="flex h-full min-h-[44px] items-center gap-2.5 rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-800 transition-colors hover:border-gray-400 hover:bg-gray-100 dark:border-gray-800 dark:text-gray-200 dark:hover:border-gray-600 dark:hover:bg-gray-800"
                    >
                        <span class="flex h-6 w-6 flex-none items-center justify-center rounded-full bg-gray-200 font-mono text-xs text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            {{ $index + 1 }}
                        </span>

                        @lang('workspace::app.onboarding.steps.' . $key)
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
@endif
