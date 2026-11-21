<x-admin::layouts.anonymous>
    <x-slot:title>
        {{ $title }}
    </x-slot>

    <style>
        .legal-doc { line-height: 1.65; font-size: 0.95rem; }
        .legal-doc h1 { font-size: 1.6rem; font-weight: 700; margin: 0 0 1rem; }
        .legal-doc h2 { font-size: 1.15rem; font-weight: 600; margin: 2rem 0 0.6rem; }
        .legal-doc h3 { font-size: 1rem; font-weight: 600; margin: 1.5rem 0 0.5rem; }
        .legal-doc p, .legal-doc ul, .legal-doc ol, .legal-doc table { margin: 0 0 0.9rem; }
        .legal-doc ul { list-style: disc; padding-inline-start: 1.4rem; }
        .legal-doc ol { list-style: decimal; padding-inline-start: 1.4rem; }
        .legal-doc a { text-decoration: underline; }
        .legal-doc table { width: 100%; border-collapse: collapse; font-size: 0.875rem; display: block; overflow-x: auto; }
        .legal-doc th, .legal-doc td { border: 1px solid rgb(229 231 235); padding: 0.45rem 0.6rem; text-align: start; vertical-align: top; }
        .dark .legal-doc th, .dark .legal-doc td { border-color: rgb(31 41 55); }
    </style>

    <div class="flex min-h-[100vh] flex-col items-center gap-6 px-4 py-10">
        <a href="{{ url('/') }}">
            <x-admin::logo class="h-10" />
        </a>

        <article class="box-shadow legal-doc w-full max-w-[760px] rounded-md bg-white p-6 text-gray-800 dark:bg-gray-900 dark:text-gray-200 sm:p-10">
            @if ($version !== '')
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    @lang('legal::app.version', ['version' => $version])
                </p>
            @endif

            {!! $html !!}
        </article>

        @include('legal::partials.links', ['current' => $document])
    </div>
</x-admin::layouts.anonymous>
