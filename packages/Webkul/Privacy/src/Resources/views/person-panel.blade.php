@if (bouncer()->hasPermission('contacts.persons.privacy'))
    {{-- v-pre: the person's name is printed below and must never be read as a Vue template. --}}
    <div
        class="rounded-lg border border-gray-300 bg-white p-4 dark:border-gray-800 dark:bg-gray-900"
        v-pre
    >
        <p class="text-base font-semibold text-gray-800 dark:text-white">
            @lang('privacy::app.panel.title')
        </p>

        @if ($person->erased_at)
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                {{ __('privacy::app.panel.erased-on', ['date' => \Illuminate\Support\Carbon::parse($person->erased_at)->toFormattedDateString()]) }}
            </p>
        @else
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                @lang('privacy::app.panel.intro')
            </p>

            <div class="mt-3 flex flex-wrap items-start gap-3">
                <a
                    class="secondary-button"
                    href="{{ route('admin.contacts.persons.privacy.export', $person->id) }}"
                >
                    @lang('privacy::app.panel.export')
                </a>
            </div>

            <details class="mt-4 rounded-md border border-red-200 p-3 dark:border-red-900">
                <summary class="cursor-pointer text-sm font-semibold text-red-600">
                    @lang('privacy::app.panel.erase-title')
                </summary>

                <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
                    @lang('privacy::app.panel.erase-warning')
                </p>

                <form
                    class="mt-3 flex flex-col gap-2"
                    method="POST"
                    action="{{ route('admin.contacts.persons.privacy.erase', $person->id) }}"
                >
                    @csrf

                    <label
                        class="text-xs font-medium text-gray-800 dark:text-white"
                        for="privacy_confirm_name"
                    >
                        {{ __('privacy::app.panel.confirm-label', ['name' => $person->name]) }}
                    </label>

                    <input
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                        id="privacy_confirm_name"
                        name="confirm_name"
                        type="text"
                        autocomplete="off"
                        required
                        @error('confirm_name') aria-invalid="true" aria-describedby="privacy_confirm_name-error" @enderror
                    >

                    @error('confirm_name')
                        <p
                            class="text-xs text-red-600"
                            id="privacy_confirm_name-error"
                        >
                            {{ $message }}
                        </p>
                    @enderror

                    <button
                        class="self-start rounded-md bg-red-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-red-700"
                        type="submit"
                    >
                        @lang('privacy::app.panel.erase-button')
                    </button>
                </form>
            </details>
        @endif
    </div>
@endif
