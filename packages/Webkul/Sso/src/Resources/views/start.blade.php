<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('sso::app.start.title')
    </x-slot>

    <div class="flex min-h-[100vh] flex-col items-center justify-center gap-8 px-4 py-10">
        <div class="flex w-full max-w-[380px] flex-col items-center gap-5">
            <x-admin::logo class="h-10" />

            <div class="box-shadow w-full rounded-md bg-white dark:bg-gray-900">
                <form
                    method="POST"
                    action="{{ route('admin.sso.start') }}"
                >
                    @csrf

                    <div class="p-4">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                            @lang('sso::app.start.title')
                        </h1>

                        <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                            @lang('sso::app.start.intro')
                        </p>

                        @foreach (['error' => 'text-red-600', 'warning' => 'text-amber-700 dark:text-amber-400'] as $kind => $colour)
                            @if (session($kind))
                                <p
                                    class="mt-3 text-sm {{ $colour }}"
                                    role="alert"
                                >
                                    {{ session($kind) }}
                                </p>
                            @endif
                        @endforeach
                    </div>

                    <div class="border-y p-4 dark:border-gray-800">
                        <label
                            class="required mb-1.5 block text-xs font-medium text-gray-800 dark:text-white"
                            for="email"
                        >
                            @lang('sso::app.start.email')
                        </label>

                        <input
                            class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            id="email"
                            name="email"
                            type="email"
                            value="{{ old('email') }}"
                            autocomplete="email"
                            required
                            autofocus
                            @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                        >

                        @error('email')
                            <p
                                class="mt-1 text-xs text-red-600"
                                id="email-error"
                            >
                                {{ $message }}
                            </p>
                        @enderror
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <a
                            class="text-xs font-semibold leading-6 text-brandColor dark:text-teal-400"
                            href="{{ route('admin.session.create') }}"
                        >
                            @lang('sso::app.start.password')
                        </a>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('sso::app.start.submit')
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin::layouts.anonymous>
