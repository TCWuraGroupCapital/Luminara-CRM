<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('workspace::app.status.title')
    </x-slot>

    @php
        $status = $workspace->status;
    @endphp

    <div class="flex min-h-[100vh] flex-col items-center justify-center gap-8 px-4 py-10">
        <div class="flex w-full max-w-[380px] flex-col items-center gap-5">
            <x-admin::logo class="h-10" />

            <div
                class="box-shadow w-full rounded-md bg-white p-4 dark:bg-gray-900"
                role="status"
                aria-live="polite"
            >
                @if ($status === 'active')
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                        @lang('workspace::app.status.ready')
                    </h1>

                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                        @lang('workspace::app.status.ready-next')
                    </p>

                    <a
                        class="primary-button mt-4 max-w-max"
                        href="{{ route('admin.session.create') }}"
                    >
                        @lang('workspace::app.status.sign-in')
                    </a>
                @elseif ($status === 'unverified')
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                        @lang('workspace::app.status.verify-title')
                    </h1>

                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                        @lang('workspace::app.status.verify-body', ['email' => $workspace->owner_email])
                    </p>
                @elseif ($status === 'provisioning')
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                        @lang('workspace::app.status.title')
                    </h1>

                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                        @lang('workspace::app.status.building', ['name' => $workspace->name])
                    </p>

                    <div class="shimmer mt-4 h-1.5 w-full rounded-full"></div>

                    <noscript>
                        <meta
                            http-equiv="refresh"
                            content="5"
                        >
                    </noscript>
                @else
                    <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                        @lang('workspace::app.status.title')
                    </h1>

                    <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                        @lang('workspace::app.status.failed')
                    </p>

                    <a
                        class="secondary-button mt-4 max-w-max"
                        href="{{ route('workspace.signup.create') }}"
                    >
                        @lang('workspace::app.status.try-again')
                    </a>
                @endif
            </div>
        </div>
    </div>

    @if ($status === 'provisioning')
        @push('scripts')
            <script>
                (function poll() {
                    setTimeout(function () {
                        fetch(window.location.href, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
                            .then(function (response) { return response.json(); })
                            .then(function (data) { data.status === 'provisioning' ? poll() : window.location.reload(); })
                            .catch(poll);
                    }, 2500);
                })();
            </script>
        @endpush
    @endif
</x-admin::layouts.anonymous>
