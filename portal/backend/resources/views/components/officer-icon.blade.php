{{--
    Inline stroke icons carried over from the former Next.js officer portal
    (portal/frontend/components/portal/icons.js). Same paths, same props:
    every icon inherits colour from currentColor.
--}}
@props([
    'name',
    'size' => 20,
])

<svg
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="{{ $name === 'plus' ? '2.2' : '2' }}"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    {{ $attributes }}
>
    @switch($name)
        @case('plus')
            <path d="M12 5v14" />
            <path d="M5 12h14" />
            @break
        @case('lock')
            <rect x="4" y="11" width="16" height="10" rx="2" />
            <path d="M8 11V7a4 4 0 0 1 8 0v4" />
            @break
    @endswitch
</svg>
