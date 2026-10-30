<?php

namespace App\Filament\Analytics;

use App\Filament\Clusters\Analytics\AnalyticsCluster;
use App\Models\Agency;
use App\Services\Analytics\AnalyticsExport;
use App\Services\Analytics\AssessmentStats;
use App\Support\Analytics\DateRange;
use App\Support\Timezones;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
One tab of Analytics (App\Filament\Clusters\Analytics, team plan task 28). Each
tab says who may open it and what it shows (sections()); this class holds what
they share: the date range above the charts and, on the assessment tabs, the
filters behind the Filters button. Both are kept in the session, so they stay
put when someone moves between tabs.

The filters (Finishing Touches 32): victim sex, offender sex, relationship and,
on the Law-Enforcement tab only, agency. Each is optional ("Any") and works
alone or with the others; Reset removes them all. Stored codes and agency ids
only, each checked against its list.

Everything a tab shows is a total. No names, phone numbers, comments or dates
of birth ever reach the page - the stats services don't select them.

Export (Burchfield B68) downloads the tab as Excel or PDF, with the applied
range and filter, through App\Services\Analytics\AnalyticsExport. Not logged.
*/
abstract class AnalyticsPage extends Page
{
    protected static ?string $cluster = AnalyticsCluster::class;

    protected string $view = 'filament.analytics.page';

    private const SESSION_KEY = 'analytics.filters';

    // The applied values are #[Locked]: only this class's methods change
    // them, never the browser. The draft boxes are free, and validated.

    // The applied range: a DateRange preset key or DateRange::CUSTOM.
    #[Locked]
    public string $range = DateRange::DEFAULT;

    // The applied custom dates (Y-m-d, Eastern), set by applyCustomRange().
    #[Locked]
    public ?string $from = null;

    #[Locked]
    public ?string $to = null;

    // What the custom date boxes hold before Apply.
    public ?string $customFrom = null;

    public ?string $customTo = null;

    // The applied filters (stored codes, an agency id), and the filter
    // panel's choices before Apply.
    #[Locked]
    public ?string $victimSex = null;

    #[Locked]
    public ?string $offenderSex = null;

    #[Locked]
    public ?string $relationship = null;

    #[Locked]
    public ?int $agency = null;

    public ?string $draftVictimSex = null;

    public ?string $draftOffenderSex = null;

    public ?string $draftRelationship = null;

    public ?string $draftAgency = null;

    // [heading?, tiles, cards] for the view; see the stats services.
    abstract protected function sections(DateRange $range): array;

    // The sentence under the heading; only Follow-Ups has one (FT33).
    public function description(): ?string
    {
        return null;
    }

    // Only the assessment tabs offer the filters.
    public function offersFilters(): bool
    {
        return false;
    }

    // Only officer assessments have an agency.
    public function offersAgencyFilter(): bool
    {
        return false;
    }

    public function mount(): void
    {
        $saved = session(self::SESSION_KEY, []);

        $range = $saved['range'] ?? DateRange::DEFAULT;
        $this->range = $range === DateRange::CUSTOM || array_key_exists($range, DateRange::PRESETS) ? $range : DateRange::DEFAULT;
        $this->from = $this->validDate($saved['from'] ?? null);
        $this->to = $this->validDate($saved['to'] ?? null);

        if ($this->range === DateRange::CUSTOM && ($this->from === null || $this->to === null || $this->from > $this->to)) {
            $this->range = DateRange::DEFAULT;
            $this->from = $this->to = null;
        }

        $this->customFrom = $this->from;
        $this->customTo = $this->to;
        // Every filter is read back on every tab, so remember() never drops
        // one that this tab doesn't use.
        $this->victimSex = $this->validOption($saved['victimSex'] ?? null, $this->sexOptions());
        $this->offenderSex = $this->validOption($saved['offenderSex'] ?? null, $this->sexOptions());
        $this->relationship = $this->validOption($saved['relationship'] ?? null, $this->relationshipOptions());
        $agency = $this->validOption($saved['agency'] ?? null, $this->agencyOptions());
        $this->agency = $agency === null ? null : (int) $agency;
        $this->draftVictimSex = $this->victimSex;
        $this->draftOffenderSex = $this->offenderSex;
        $this->draftRelationship = $this->relationship;
        $this->draftAgency = $agency;
    }

    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make([
                Action::make('exportExcel')
                    ->label('Excel (.xlsx)')
                    ->icon('heroicon-o-table-cells')
                    ->authorize(fn (): bool => static::canAccess())
                    ->action(fn (AnalyticsExport $export): StreamedResponse => $this->download(
                        'xlsx',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        $export->xlsx(...),
                    )),
                Action::make('exportPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-text')
                    ->authorize(fn (): bool => static::canAccess())
                    ->action(fn (AnalyticsExport $export): StreamedResponse => $this->download(
                        'pdf',
                        'application/pdf',
                        $export->pdf(...),
                    )),
            ])
                ->label('Export')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->button(),
        ];
    }

    // What the export prints under the tab's name: the range, the filter on
    // the assessment tabs ("None" when off) and the day it was exported.
    public function exportLines(): array
    {
        $range = $this->dateRange();
        $preset = $this->range === DateRange::CUSTOM ? null : (DateRange::PRESETS[$this->range] ?? DateRange::PRESETS[DateRange::DEFAULT]);

        $lines = ['Date range: '.($preset === null ? $range->label() : "{$preset} ({$range->label()})").', Eastern time'];

        if ($this->offersFilters()) {
            $lines[] = 'Filter: '.($this->filterLabel() ?? 'None');
        }

        $lines[] = 'Exported '.now(Timezones::DISPLAY)->format('M j, Y');

        return $lines;
    }

    // The tab as a file, from the same sections() the page draws.
    private function download(string $extension, string $contentType, \Closure $render): StreamedResponse
    {
        abort_unless(static::canAccess(), 403);

        $title = (string) $this->getTitle();
        $bytes = $render($title, $this->exportLines(), $this->sections($this->dateRange()));
        $name = 'analytics-'.Str::slug($title).'-'.now(Timezones::DISPLAY)->format('Y-m-d').'.'.$extension;

        return response()->streamDownload(
            function () use ($bytes): void {
                echo $bytes;
            },
            $name,
            ['Content-Type' => $contentType],
        );
    }

    public function getHeading(): string | Htmlable
    {
        return 'Analytics';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return $this->description();
    }

    // A preset applies at once. Custom opens the date boxes, filled with the
    // range on screen, and changes nothing until Apply.
    public function setRange(string $key): void
    {
        if ($key === DateRange::CUSTOM) {
            $current = $this->dateRange();
            $this->customFrom = $current->fromDate;
            $this->customTo = $current->toDate;
            $this->from = $current->fromDate;
            $this->to = $current->toDate;
            $this->range = DateRange::CUSTOM;
        } elseif (array_key_exists($key, DateRange::PRESETS)) {
            $this->range = $key;
        }

        $this->resetValidation();
        $this->remember();
    }

    public function applyCustomRange(): void
    {
        // Neither end in the future (the Eastern day, as the inputs' max).
        $today = Timezones::today();

        $this->validate([
            'customFrom' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.$today],
            'customTo' => ['required', 'date_format:Y-m-d', 'after_or_equal:customFrom', 'before_or_equal:'.$today],
        ], [
            'customTo.after_or_equal' => 'The end date must be on or after the start date.',
            'customFrom.before_or_equal' => "The start date can't be in the future.",
            'customTo.before_or_equal' => "The end date can't be in the future.",
        ], [
            'customFrom' => 'start date',
            'customTo' => 'end date',
        ]);

        $this->range = DateRange::CUSTOM;
        $this->from = $this->customFrom;
        $this->to = $this->customTo;
        $this->remember();
    }

    public function applyFilters(): void
    {
        $this->validate([
            'draftVictimSex' => ['nullable', Rule::in(array_keys($this->sexOptions()))],
            'draftOffenderSex' => ['nullable', Rule::in(array_keys($this->sexOptions()))],
            'draftRelationship' => ['nullable', Rule::in(array_keys($this->relationshipOptions()))],
            'draftAgency' => ['nullable', Rule::in(array_keys($this->agencyOptions()))],
        ], [], [
            'draftVictimSex' => 'victim sex',
            'draftOffenderSex' => 'offender sex',
            'draftRelationship' => 'relationship',
            'draftAgency' => 'agency',
        ]);

        $this->victimSex = filled($this->draftVictimSex) ? $this->draftVictimSex : null;
        $this->offenderSex = filled($this->draftOffenderSex) ? $this->draftOffenderSex : null;
        $this->relationship = filled($this->draftRelationship) ? $this->draftRelationship : null;

        if ($this->offersAgencyFilter()) {
            $this->agency = filled($this->draftAgency) ? (int) $this->draftAgency : null;
        }

        $this->remember();
        $this->dispatch('analytics-filter-closed');
    }

    public function resetFilters(): void
    {
        $this->victimSex = $this->offenderSex = $this->relationship = $this->agency = null;
        $this->draftVictimSex = $this->draftOffenderSex = $this->draftRelationship = $this->draftAgency = null;
        $this->resetValidation(['draftVictimSex', 'draftOffenderSex', 'draftRelationship', 'draftAgency']);
        $this->remember();
        $this->dispatch('analytics-filter-closed');
    }

    public function dateRange(): DateRange
    {
        if ($this->range === DateRange::CUSTOM && $this->from !== null && $this->to !== null && $this->from <= $this->to) {
            return DateRange::custom($this->from, $this->to);
        }

        return DateRange::preset(array_key_exists($this->range, DateRange::PRESETS) ? $this->range : DateRange::DEFAULT);
    }

    public function rangeOptions(): array
    {
        return [...DateRange::PRESETS, DateRange::CUSTOM => 'Custom'];
    }

    public function sexOptions(): array
    {
        return AssessmentStats::SEXES;
    }

    public function relationshipOptions(): array
    {
        return AssessmentStats::relationshipOptions();
    }

    /** @return array<int, string> id => name */
    public function agencyOptions(): array
    {
        return once(fn (): array => Agency::query()->orderBy('name')->pluck('name', 'id')->all());
    }

    // The filters that are set, e.g. "Victim Sex: Female · Agency: Example PD",
    // or null when none is.
    public function filterLabel(): ?string
    {
        if (! $this->offersFilters()) {
            return null;
        }

        $parts = array_filter([
            'Victim Sex' => $this->victimSex === null ? null : $this->sexOptions()[$this->victimSex],
            'Offender Sex' => $this->offenderSex === null ? null : $this->sexOptions()[$this->offenderSex],
            'Relationship' => $this->relationship === null ? null : $this->relationshipOptions()[$this->relationship],
            'Agency' => $this->offersAgencyFilter() && $this->agency !== null ? ($this->agencyOptions()[$this->agency] ?? null) : null,
        ]);

        return $parts === [] ? null : collect($parts)->map(fn (string $value, string $name): string => "{$name}: {$value}")->implode(' · ');
    }

    protected function getViewData(): array
    {
        $range = $this->dateRange();

        return [
            'rangeLabel' => $range->label(),
            'sections' => $this->sections($range),
        ];
    }

    // The applied filters as named arguments for AssessmentStats and
    // AssessmentSections: nothing unless this tab offers them, and agency
    // only where it applies.
    protected function filters(): array
    {
        if (! $this->offersFilters()) {
            return [];
        }

        return [
            'victimSex' => $this->victimSex,
            'offenderSex' => $this->offenderSex,
            'relationship' => $this->relationship,
            ...($this->offersAgencyFilter() ? ['agency' => $this->agency] : []),
        ];
    }

    private function remember(): void
    {
        session([self::SESSION_KEY => [
            'range' => $this->range,
            'from' => $this->from,
            'to' => $this->to,
            'victimSex' => $this->victimSex,
            'offenderSex' => $this->offenderSex,
            'relationship' => $this->relationship,
            'agency' => $this->agency,
        ]]);
    }

    private function validDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('!Y-m-d', $value)->toDateString() === $value ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    // $value as a string when it is one of $options' keys, else null.
    private function validOption(mixed $value, array $options): ?string
    {
        return (is_string($value) || is_int($value)) && array_key_exists($value, $options) ? (string) $value : null;
    }
}
