<?php

namespace App\Providers;

use App\Filament\Forms\RequirementMarkers;
use App\Models\LawEnforcementAssessment;
use App\Policies\LawEnforcementAssessmentPolicy;
use BladeUI\Icons\Factory as IconFactory;
use Filament\Support\View\Components\ModalComponent;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // The project's own icons (resources/svg), for what Heroicons lacks -
        // e.g. 'hs-police-badge'. Usable anywhere Filament takes an icon name.
        $this->callAfterResolving(IconFactory::class, function (IconFactory $factory): void {
            $factory->add('harborsafe', [
                'path' => resource_path('svg'),
                'prefix' => 'hs',
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registered explicitly rather than left to discovery, so nothing
        // depends on the policy's file name or folder.
        Gate::policy(LawEnforcementAssessment::class, LawEnforcementAssessmentPolicy::class);

        // "Optional" ghost text on every optional text input, text area and
        // dropdown in the staff panel - see that class for the full rule.
        RequirementMarkers::register();

        // Pop-ups close only from their X, Cancel or the Esc key, never from a
        // stray click outside them, which used to throw away a half-typed
        // event or edit. Every action modal and <x-filament::modal> falls back
        // to this unless it says otherwise.
        ModalComponent::closedByClickingAway(false);
    }
}
