{{--
    Sidebar identity block, carried over from the footer of the former
    Next.js PortalSidebar. Officers and police admins, one row each for name,
    badge and agency. The badge is officers only: a police admin's
    law_enforcement_agents row exists just to carry their agency (team plan
    task 11). Other roles have no badge or agency, so no block.
--}}
@php
    $user = filament()->auth()->user();
    $isOfficer = $user?->role === \App\Enums\UserRole::LawEnforcement;
    $isPoliceAdmin = $user?->role === \App\Enums\UserRole::PoliceAdmin;
@endphp

@if ($isOfficer || $isPoliceAdmin)
    @php
        $identity = $user->officerIdentity();
    @endphp

    <div class="hs-officer-identity mt-auto border-t border-[#4A3E5E] px-3 pt-4 text-white">
        <p class="text-[15px] font-bold">{{ $identity['name'] }}</p>
        @if ($isOfficer)
            <p class="text-[13px] text-[#D6CFE2]">Badge {{ $identity['badge'] ?? '—' }}</p>
        @endif
        <p class="text-[13px] text-[#D6CFE2]">{{ $identity['agency'] ?? 'No agency' }}</p>
    </div>
@endif
