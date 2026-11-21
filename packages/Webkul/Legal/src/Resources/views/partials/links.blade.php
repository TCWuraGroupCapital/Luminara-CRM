@php
    $legalLinks = collect(\Webkul\Legal\Support\LegalDocuments::FILES)
        ->keys()
        ->filter(fn (string $document) => \Webkul\Legal\Support\LegalDocuments::exists($document));
@endphp

@if ($legalLinks->isNotEmpty())
    <nav
        class="flex flex-wrap justify-center gap-x-4 gap-y-1 p-4 text-xs text-gray-600 dark:text-gray-400"
        aria-label="@lang('legal::app.nav-label')"
    >
        @foreach ($legalLinks as $document)
            <a
                class="hover:underline @if (($current ?? null) === $document) font-semibold text-gray-800 dark:text-gray-200 @endif"
                href="{{ route('legal.'.$document) }}"
                @if (($current ?? null) === $document) aria-current="page" @endif
            >
                @lang('legal::app.titles.'.$document)
            </a>
        @endforeach
    </nav>
@endif
