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
        {{-- clipboard and users: feathericons.com, for the police admin Home cards --}}
        @case('clipboard')
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" />
            <rect x="8" y="2" width="8" height="4" rx="1" />
            @break
        @case('users')
            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2" />
            <circle cx="9" cy="7" r="4" />
            <path d="M23 21v-2a4 4 0 0 0-3-3.87" />
            <path d="M16 3.13a4 4 0 0 1 0 7.75" />
            @break
    @endswitch
</svg>
