<?php

namespace App\Providers\Filament;

use App\Enums\NewSubmission;
use App\Enums\UserRole;
use App\Livewire\StaffNotifications;
use Filament\Enums\DatabaseNotificationsPosition;
use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Login;
use App\Filament\PoliceAdminNavigation;
use App\Http\Middleware\RedirectStaffHome;
use App\Http\Responses\StaffLoginResponse;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Facades\Filament;
use Filament\FontProviders\GoogleFontProvider;
use Filament\Http\Middleware\Authenticate;
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
            // Served from the site root, so sign-in is a neutral /login shared
            // by every role rather than living under one side's address; / sends
            // a signed-in user to their own area (RedirectStaffHome). The id
            // stays 'staff', so route names remain filament.staff.*. /api is
            // the JSON API and is not part of the panel.
            ->path('')
            ->viteTheme('resources/css/filament/staff/theme.css')
            ->login(Login::class)
            // Account page. Filament's EditProfile only edits the signed-in
            // user's own name, email and password - never role or is_active.
            // Ours swaps its Name field for first and last name.
            ->profile(EditProfile::class, isSimple: false)
            // A screen with no rule for an action is an error, not a yes:
            // without this, Filament allows any action on a model with no
            // policy. Every screen lists what it allows
            // (App\Filament\Concerns\AllowsOnlyListedActions, or a policy);
            // ActionAccessTest pins each one.
            ->strictAuthorization()
            // Look and feel carried over from the former Next.js officer
            // portal (PortalSidebar/PortalHeader): #5C0F8B primary, Nunito
            // body text, Montserrat headings, a dark full-height sidebar. The
            // rest of it lives in resources/css/filament/staff/theme.css.
            ->brandName('Harbor Safe')
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
            // The bell: alerts about new submissions (App\Enums\NewSubmission),
            // in the sidebar footer just above the user menu, for secretaries
            // and admins only. Never pop-ups. No polling: a poll is a request,
            // and would keep an unattended session alive past its 15 idle
            // minutes, so the count refreshes on each page and action instead.
            ->databaseNotifications(
                condition: fn (): bool => NewSubmission::reaches(Filament::auth()->user()),
                livewireComponent: StaffNotifications::class,
                isLazy: false,
                position: DatabaseNotificationsPosition::Sidebar,
            )
            ->databaseNotificationsPolling(null)
            // Marks the bell's alerts read after its panel has been open ~5 s.
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => NewSubmission::reaches(Filament::auth()->user())
                    ? view('filament.partials.staff-notifications')->render()
                    : '',
            )
            // Reloads an idle screen once the session has expired, so it
            // shows the sign-in page instead of whatever was left open.
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Filament::auth()->check()
                    ? view('filament.partials.idle-logout', ['minutes' => config('session.lifetime')])->render()
                    : '',
            )
            // An unfinished assessment kept in the tab (AssessmentDraft):
            // wiped on Sign out, dropped if it is someone else's or too old.
            ->renderHook(
                PanelsRenderHook::SCRIPTS_AFTER,
                fn (): string => Filament::auth()->check()
                    ? view('filament.partials.assessment-draft-guard')->render()
                    : '',
            )
            ->navigationItems([
                // The original officer sidebar's last link. Scoped to the two
                // officer-portal roles so the group does not appear for an
                // admin containing nothing but this. Every role still reaches
                // the profile page through the user menu in the sidebar
                // footer, which Filament renders regardless. Officers find it
                // last in Officer Portal, police admins last in their single
                // Police Admin group (App\Filament\PoliceAdminNavigation).
                NavigationItem::make('Account')
                    ->url(fn (): string => filament()->getProfileUrl())
                    ->icon(Heroicon::OutlinedUser)
                    ->group(fn (): string => PoliceAdminNavigation::applies() ? PoliceAdminNavigation::GROUP : 'Officer Portal')
                    ->sort(fn (): int => PoliceAdminNavigation::applies() ? PoliceAdminNavigation::ACCOUNT : 6)
                    ->isActiveWhen(fn (): bool => request()->routeIs('filament.staff.auth.profile'))
                    ->visible(fn (): bool => (bool) auth()->user()?->hasActiveRole(
                        UserRole::LawEnforcement,
                        UserRole::PoliceAdmin,
                    )),
            ])
            // Sidebar group order. Without this Filament orders groups by
            // first appearance, which depends on discovery order and so moves
            // when a file is added.
            ->navigationGroups([
                'Content Management',
                'Assessments',
                // Analytics (App\Filament\Clusters\Analytics), for admins,
                // super admins and secretaries.
                'Reports',
                'Officer Portal',
                PoliceAdminNavigation::GROUP,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            // Clusters must be discovered explicitly; a cluster that is not
            // registered here still resolves as a class but registers no
            // routes, so every resource inside it 404s.
            ->discoverClusters(in: app_path('Filament/Clusters'), for: 'App\Filament\Clusters')
            // No Dashboard: with nothing on the panel root, Filament's built-in
            // RedirectToHomeController sends / (and the post-login
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
            ->authMiddleware([
                Authenticate::class,
                // Sends / to the signed-in user's landing page from
                // App\Filament\StaffLanding rather than to whichever
                // navigation item happens to sort first.
                RedirectStaffHome::class,
            ]);
    }

    public function register(): void
    {
        parent::register();

        // Where a successful sign-in goes. Filament's default lands on
        // whatever the navigation starts with; StaffLanding declares it per
        // role instead.
        $this->app->singleton(LoginResponse::class, StaffLoginResponse::class);
    }
}
