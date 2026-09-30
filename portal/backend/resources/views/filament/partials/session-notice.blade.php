{{-- Carried over from SessionNotice in the former Next.js PortalHeader.js. --}}
<p class="flex items-center gap-2 text-[13px] text-[#5A5566]">
    <x-officer-icon name="lock" :size="16" />
    Session locks after {{ $minutes }} minutes of inactivity
</p>
