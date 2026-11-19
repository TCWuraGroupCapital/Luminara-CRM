@props([
    'variant' => 'full',
    'imgClass' => 'h-full w-auto object-contain',
])

@php
    $customLogo = core()->getConfigData('general.general.admin_logo.logo_image');

    $lightAsset = $variant === 'mark'
        ? vite()->asset('images/luminara-mobile-light-logo.svg')
        : vite()->asset('images/luminara-logo.svg');

    $darkAsset = $variant === 'mark'
        ? vite()->asset('images/luminara-mobile-dark-logo.svg')
        : vite()->asset('images/luminara-dark-logo.svg');
@endphp

<span {{ $attributes->class(['inline-flex items-center']) }}>
    @if ($customLogo)
        <img
            class="{{ $imgClass }}"
            src="{{ Storage::url($customLogo) }}"
            alt="{{ config('app.name') }}"
        />
    @else
        <img
            class="{{ $imgClass }} dark:hidden"
            src="{{ $lightAsset }}"
            alt="{{ config('app.name') }}"
        />

        <img
            class="{{ $imgClass }} hidden dark:block"
            src="{{ $darkAsset }}"
            alt="{{ config('app.name') }}"
        />
    @endif
</span>
