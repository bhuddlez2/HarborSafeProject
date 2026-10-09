<?php

use App\Enums\UserRole;
use App\Filament\Clusters\Content\Resources\EventCategories\Pages\ManageEventCategories;
use App\Filament\Clusters\Content\Resources\Events\Pages\ManageEvents;
use App\Filament\Clusters\Content\Resources\Newsletters\Pages\ManageNewsletters;
use App\Filament\Clusters\FormOptions\Resources\Counties\Pages\ManageCounties;
use App\Filament\Clusters\FormOptions\Resources\Resources\Pages\ManageResourceTypes;
use App\Filament\Clusters\FormOptions\Resources\Services\Pages\ManageServices;
use App\Filament\Clusters\Submissions\Resources\ResourceRequests\Pages\ListResourceRequests;
use App\Filament\Clusters\Submissions\Resources\ServiceFeedback\Pages\ListServiceFeedback;
use App\Filament\Resources\CivilianAssessments\Pages\ListCivilianAssessments;
use App\Filament\Resources\StaffAccounts\Pages\ListStaffAccounts;
use App\Filament\Tables\TableAlignment;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
Roadmap Phase K for the content-management tables, Civilian assessments and
Accounts (App\Filament\Tables\TableAlignment): text columns left-aligned with
their heading, symbol columns - yes/no icons and badges - centred under it.
Checked column by column, so a column added later the wrong way fails here.

The police-side tables (Assessment review, My Assessments, Officers, Change
log) are being aligned separately and are deliberately not listed.
*/

afterEach(fn () => cleanupStaffData());

function isSymbolColumn(Column $column): bool
{
    return $column instanceof IconColumn
        || ($column instanceof TextColumn && $column->isBadge());
}

test('text columns sit left and symbol columns sit centred', function (string $page) {
    $this->actingAs(staffUser(UserRole::Admin));

    $columns = Livewire::test($page)->instance()->getTable()->getColumns();

    expect($columns)->not->toBeEmpty();

    foreach ($columns as $column) {
        $headerClass = (string) ($column->getExtraHeaderAttributes()['class'] ?? '');

        if (isSymbolColumn($column)) {
            expect($column->getAlignment())->toBe(Alignment::Center, "{$page}: {$column->getName()} should be centred")
                ->and($headerClass)->toContain(TableAlignment::SYMBOL_HEADER_CLASS);
        } else {
            expect($column->getAlignment())->toBeIn([null, Alignment::Start], "{$page}: {$column->getName()} should be left-aligned")
                ->and($headerClass)->not->toContain(TableAlignment::SYMBOL_HEADER_CLASS);
        }
    }
})->with([
    'Events' => ManageEvents::class,
    'Newsletters' => ManageNewsletters::class,
    'Categories' => ManageEventCategories::class,
    'Service feedback' => ListServiceFeedback::class,
    'Resource requests' => ListResourceRequests::class,
    'Services' => ManageServices::class,
    'Resource types' => ManageResourceTypes::class,
    'Counties' => ManageCounties::class,
    'Civilian assessments' => ListCivilianAssessments::class,
    'Accounts' => ListStaffAccounts::class,
]);

test('Civilian assessments has a Relationship column after Offender', function () {
    $this->actingAs(staffUser(UserRole::Admin));

    $labels = collect(Livewire::test(ListCivilianAssessments::class)->instance()->getTable()->getColumns())
        ->map(fn (Column $column): string => (string) $column->getLabel())
        ->values()
        ->all();

    expect($labels)->toBe(['Submitted', 'Victim', 'Offender', 'Relationship', 'Yes']);
});

test('an account\'s Created date is the Eastern day it happened on', function () {
    // 03:00 UTC on 10 March 2026 is 11 pm on 9 March in Eastern.
    $secretary = staffUser(UserRole::Secretary);
    DB::connection('Portal')->table('users')->where('id', $secretary->getKey())
        ->update(['created_at' => '2026-03-10 03:00:00']);

    $this->actingAs(staffUser(UserRole::Admin));

    Livewire::test(ListStaffAccounts::class)
        ->assertTableColumnFormattedStateSet('created_at', 'Mar 9, 2026', $secretary->fresh());
});
