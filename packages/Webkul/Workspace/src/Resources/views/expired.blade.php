<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('workspace::app.status.expired-title')
    </x-slot>

    <div class="flex min-h-[100vh] flex-col items-center justify-center gap-8 px-4 py-10">
        <div class="flex w-full max-w-[380px] flex-col items-center gap-5">
            <x-admin::logo class="h-10" />

            <div class="box-shadow w-full rounded-md bg-white p-4 dark:bg-gray-900">
                <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                    @lang('workspace::app.status.expired-title')
                </h1>

                <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                    @lang('workspace::app.status.expired')
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a
                        class="primary-button max-w-max"
                        href="{{ route('admin.session.create') }}"
                    >
                        @lang('workspace::app.status.sign-in')
                    </a>

                    <a
                        class="secondary-button max-w-max"
                        href="{{ route('workspace.signup.create') }}"
                    >
                        @lang('workspace::app.status.try-again')
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-admin::layouts.anonymous>
