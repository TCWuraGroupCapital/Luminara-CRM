<x-admin::layouts.anonymous>
    <x-slot:title>
        @lang('workspace::app.signup.title')
    </x-slot>

    @php
        $input = 'block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300';

        $label = 'mb-1.5 block text-xs font-medium text-gray-800 dark:text-white';

        $fields = [
            ['business_name', 'text', 'organization', 'business-name'],
            ['name', 'text', 'name', 'name'],
            ['email', 'email', 'email', 'email'],
        ];
    @endphp

    <div class="flex min-h-[100vh] flex-col items-center justify-center gap-8 px-4 py-10">
        <div class="flex w-full max-w-[380px] flex-col items-center gap-5">
            <x-admin::logo class="h-10" />

            <div class="box-shadow w-full rounded-md bg-white dark:bg-gray-900">
                <form
                    method="POST"
                    action="{{ route('workspace.signup.store') }}"
                >
                    @csrf

                    <div class="p-4">
                        <h1 class="text-xl font-bold text-gray-800 dark:text-white">
                            @lang('workspace::app.signup.title')
                        </h1>

                        <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-300">
                            @lang('workspace::app.signup.intro')
                        </p>
                    </div>

                    <div class="grid gap-4 border-y p-4 dark:border-gray-800">
                        @foreach ($fields as [$field, $type, $autocomplete, $key])
                            <div>
                                <label
                                    class="{{ $label }} required"
                                    for="{{ $field }}"
                                >
                                    @lang('workspace::app.signup.' . $key)
                                </label>

                                <input
                                    class="{{ $input }}"
                                    id="{{ $field }}"
                                    name="{{ $field }}"
                                    type="{{ $type }}"
                                    value="{{ old($field) }}"
                                    autocomplete="{{ $autocomplete }}"
                                    required
                                    @error($field) aria-invalid="true" aria-describedby="{{ $field }}-error" @enderror
                                >

                                @error($field)
                                    <p
                                        class="mt-1 text-xs text-red-600"
                                        id="{{ $field }}-error"
                                    >
                                        {{ $message }}
                                    </p>
                                @enderror
                            </div>
                        @endforeach

                        <div>
                            <label
                                class="{{ $label }} required"
                                for="password"
                            >
                                @lang('workspace::app.signup.password')
                            </label>

                            <input
                                class="{{ $input }}"
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="new-password"
                                minlength="12"
                                required
                                aria-describedby="password-hint"
                                @error('password') aria-invalid="true" @enderror
                            >

                            <p
                                class="mt-1 text-xs text-gray-600 dark:text-gray-400"
                                id="password-hint"
                            >
                                @lang('workspace::app.signup.password-hint')
                            </p>

                            @error('password')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label
                                class="{{ $label }} required"
                                for="password_confirmation"
                            >
                                @lang('workspace::app.signup.password-confirm')
                            </label>

                            <input
                                class="{{ $input }}"
                                id="password_confirmation"
                                name="password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                minlength="12"
                                required
                            >
                        </div>

                        @if ($needsInvite)
                            <div>
                                <label
                                    class="{{ $label }} required"
                                    for="invite_code"
                                >
                                    @lang('workspace::app.signup.invite-code')
                                </label>

                                <input
                                    class="{{ $input }}"
                                    id="invite_code"
                                    name="invite_code"
                                    type="text"
                                    value="{{ old('invite_code') }}"
                                    autocomplete="off"
                                    required
                                    @error('invite_code') aria-invalid="true" @enderror
                                >

                                @error('invite_code')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <a
                            class="text-xs font-semibold leading-6 text-brandColor dark:text-teal-400"
                            href="{{ route('admin.session.create') }}"
                        >
                            @lang('workspace::app.signup.have-account')
                        </a>

                        <button
                            type="submit"
                            class="primary-button"
                        >
                            @lang('workspace::app.signup.submit')
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-admin::layouts.anonymous>
