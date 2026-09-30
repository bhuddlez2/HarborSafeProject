{{--
    Sidebar identity block, carried over from the footer of the former
    Next.js PortalSidebar. Officers only: other roles have no badge or agency.
--}}
@php
    $user = filament()->auth()->user();
@endphp

@if ($user?->role === \App\Enums\UserRole::LawEnforcement)
    @php
        $officer = $user->officerIdentity();
    @endphp

    <div class="hs-officer-identity mt-auto border-t border-[#4A3E5E] px-3 pt-4 text-white">
        <p class="text-[15px] font-bold">{{ $officer['name'] }}</p>
        <p class="text-[13px] text-[#D6CFE2]">
            Badge {{ $officer['badge'] ?? '—' }} &middot; {{ $officer['agency'] ?? '—' }}
        </p>
    </div>
@endif
