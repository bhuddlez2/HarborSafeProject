<?php

namespace App\Providers;

use App\Filament\Forms\RequirementMarkers;
use App\Models\LawEnforcementAssessment;
use App\Policies\LawEnforcementAssessmentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registered explicitly rather than left to discovery, so Shield
        // always finds a policy already in place for this model.
        Gate::policy(LawEnforcementAssessment::class, LawEnforcementAssessmentPolicy::class);

        // "Optional" ghost text on every optional text input, text area and
        // dropdown in the staff panel - see that class for the full rule.
        RequirementMarkers::register();
    }
}
