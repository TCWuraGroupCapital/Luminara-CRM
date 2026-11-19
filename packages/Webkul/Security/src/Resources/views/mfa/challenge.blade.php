<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('security::app.mfa.challenge-title')
    </x-slot>

    <div class="flex min-h-[100vh] flex-col items-center justify-center gap-6 px-4 py-8">
        <div class="flex items-center justify-center">
            <x-admin::logo class="h-10" />
        </div>

        <div class="w-full max-w-md rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-800 dark:bg-gray-900">
            <h1 class="text-lg font-bold tracking-tight text-gray-900 dark:text-white">
                @lang('security::app.mfa.challenge-title')
            </h1>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                @lang('security::app.mfa.challenge-subtitle')
            </p>

            @if ($errors->any())
                <div class="mt-4 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700 dark:border-red-900 dark:bg-red-950/50 dark:text-red-300">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('admin.security.mfa.verify') }}"
                class="mt-5 grid gap-4"
            >
                @csrf

                <div class="grid gap-1">
                    <label for="challenge-code" class="text-xs font-semibold text-gray-700 dark:text-gray-300">
                        @lang('security::app.mfa.code-or-recovery')
                    </label>
                    <input
                        id="challenge-code"
                        name="code"
                        type="text"
                        required
                        autofocus
                        autocomplete="one-time-code"
                        placeholder="123456"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2.5 font-mono text-sm tabular-nums text-gray-900 dark:border-gray-700 dark:bg-gray-950 dark:text-white"
                    />
                </div>

                <button type="submit" class="primary-button justify-center">
                    @lang('security::app.mfa.verify-btn')
                </button>
            </form>

            <form
                method="POST"
                action="{{ route('admin.session.destroy') }}"
                class="mt-4 border-t border-gray-100 pt-3 text-center dark:border-gray-800"
            >
                @csrf
                @method('DELETE')

                <button
                    type="submit"
                    class="text-xs font-medium text-gray-500 underline-offset-2 hover:text-gray-800 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
                >
                    @lang('admin::app.layouts.sign-out')
                </button>
            </form>
        </div>
    </div>
</x-admin::layouts.anonymous>
