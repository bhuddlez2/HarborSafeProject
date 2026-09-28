<?php

namespace App\Providers\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class StaffPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('staff')
            ->path('staff')
            ->viteTheme('resources/css/filament/staff/theme.css')
            ->login(Login::class)
            // Account page. Filament's EditProfile only edits the signed-in
            // user's own name, email and password - never role or is_active.
            ->profile(isSimple: false)
            // Look and feel carried over from the former Next.js officer
            // portal (PortalSidebar/PortalHeader): #5C0F8B primary, Nunito
            // body text, Montserrat headings, a dark full-height sidebar. The
            // rest of it lives in resources/css/filament/staff/theme.css.
            ->brandName('HarborSafe')
            ->colors([
                'primary' => Color::hex('#5C0F8B'),
                // Tailwind's own gray, which the original pages used; Filament
                // otherwise defaults to Zinc.
                'gray' => Color::Gray,
            ])
            ->font(
                'Nunito',
                url: 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&family=Nunito:wght@400;500;600;700&display=swap',
                provider: GoogleFontProvider::class,
            )
            ->darkMode(false)
            // No topbar: the sidebar runs full height like the original rail,
            // and the user menu (profile, sign out) sits in its footer.
            ->topbar(false)
            ->sidebarWidth('248px')
            ->collapsibleNavigationGroups(false)
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn (): string => view('filament.partials.officer-identity')->render(),
            )
            ->navigationItems([
                // The original sidebar's last link. Filament also keeps the
                // profile page in the user menu, for every role.
                NavigationItem::make('Account')
                    ->url(fn (): string => filament()->getProfileUrl())
                    ->icon(Heroicon::OutlinedUser)
                    ->group('Officer Portal')
                    ->sort(6)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.staff.auth.profile'))
                    ->visible(fn (): bool => in_array(auth()->user()?->role, [
                        UserRole::LawEnforcement,
                        UserRole::PoliceAdmin,
                        UserRole::Admin,
                    ], true)),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // No Dashboard: with nothing on the panel root, Filament's built-in
            // RedirectToHomeController sends /staff (and the post-login
            // redirect) to the first navigation item the user can see, so each
            // role lands on its own page. Order is set by $navigationSort.
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->plugins([
                FilamentShieldPlugin::make(),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
