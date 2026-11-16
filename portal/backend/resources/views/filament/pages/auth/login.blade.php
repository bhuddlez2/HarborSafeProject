{{--
    Filament login styled to match the website and civilian assessment:
    Montserrat font, #F4F2F7 background, #5C0F8B accents. Also the look of
    the password reset and set-up pages (App\Filament\Pages\Auth), whose
    subheading is the link back to sign-in.
--}}

{{-- "Harbor Safe Portal" above the login form: one sign-in for staff and law enforcement alike. --}}
<div class="fi-simple-page hs-login">
    <p class="text-xs font-bold tracking-widest uppercase mb-4" style="color: #5C0F8B;">Harbor Safe Portal</p>
    <h1 class="text-3xl font-semibold text-gray-900 {{ filled($this->getSubheading()) ? 'mb-3' : 'mb-10' }}">{{ $this->getHeading() }}</h1>

    @if (filled($subheading = $this->getSubheading()))
        <div class="mb-8">{{ $subheading }}</div>
    @endif

    {{ $this->content }}

    {{-- After the button, so the keyboard goes Email, Password, Sign in, then this. --}}
    @if ($this instanceof \App\Filament\Pages\Auth\Login && filament()->hasPasswordReset())
        <p class="mt-6 text-center">
            <a href="{{ filament()->getRequestPasswordResetUrl() }}" class="hs-forgot-password">Forgot password?</a>
        </p>
    @endif

    <x-filament-actions::modals />
</div>
