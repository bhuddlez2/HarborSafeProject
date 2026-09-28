{{--
    Filament's login in the layout of the former Next.js sign-in page
    (portal/frontend/app/login/page.js): heading and subheading left-aligned
    above the form. Card and field styling live in the theme under .hs-login.
--}}
<div class="fi-simple-page hs-login">
    <h1 class="text-3xl font-semibold text-gray-900 mb-2">{{ $this->getHeading() }}</h1>
    <p class="text-sm text-gray-500 mb-10">{{ $this->getSubheading() }}</p>

    {{ $this->content }}

    <x-filament-actions::modals />
</div>
