{{--
    Filament login styled to match the website and civilian assessment:
    Montserrat font, #F4F2F7 background, #5C0F8B accents.
--}}

{{-- HarborSafe Staff Portal heading above the login form. --}}
<div class="fi-simple-page hs-login">
    <p class="text-xs font-bold tracking-widest uppercase mb-4" style="color: #5C0F8B;">HarborSafe Staff Portal</p>
    <h1 class="text-3xl font-semibold text-gray-900 mb-10">{{ $this->getHeading() }}</h1>

    {{ $this->content }}

    <x-filament-actions::modals />
</div>
