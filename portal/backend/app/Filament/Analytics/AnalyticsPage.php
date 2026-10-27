<?php

namespace App\Filament\Analytics;

use App\Filament\Clusters\Analytics\AnalyticsCluster;
use App\Services\Analytics\AssessmentStats;
use App\Support\Analytics\DateRange;
use App\Support\Timezones;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;

/*
One tab of Analytics (App\Filament\Clusters\Analytics, team plan task 28). Each
tab says who may open it and what it shows (sections()); this class holds what
they share: the date range above the charts and, on the assessment tabs, the
filter by victim and offender sex behind the funnel button. Both are kept in
the session, so they stay put when someone moves between tabs.

The filter: the victim's sex is required to apply it, the offender's is
optional ("Any"); Reset removes it. Stored codes only (AssessmentStats::SEXES).

Everything a tab shows is a total. No names, phone numbers, comments or dates
of birth ever reach the page - the stats services don't select them.
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

    // The applied sex filter (stored codes), and the filter panel's choices
    // before Apply.
    #[Locked]
    public ?string $victimSex = null;

    #[Locked]
    public ?string $offenderSex = null;

    public ?string $draftVictimSex = null;

    public ?string $draftOffenderSex = null;

    // [heading?, tiles, cards] for the view; see the stats services.
    abstract protected function sections(DateRange $range): array;

    // The sentence under the heading.
    abstract public function description(): string;

    // Only the assessment tabs record sex, so only they offer the filter.
    public function offersSexFilter(): bool
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
        $this->victimSex = $this->validSex($saved['victimSex'] ?? null);
        $this->offenderSex = $this->victimSex === null ? null : $this->validSex($saved['offenderSex'] ?? null);
        $this->draftVictimSex = $this->victimSex;
        $this->draftOffenderSex = $this->offenderSex;
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

    public function applySexFilter(): void
    {
        $codes = implode(',', array_keys(AssessmentStats::SEXES));

        $this->validate([
            'draftVictimSex' => ['required', "in:{$codes}"],
            'draftOffenderSex' => ['nullable', "in:{$codes}"],
        ], [
            'draftVictimSex.required' => 'Choose the victim’s sex.',
        ], [
            'draftVictimSex' => 'victim sex',
            'draftOffenderSex' => 'offender sex',
        ]);

        $this->victimSex = $this->draftVictimSex;
        $this->offenderSex = filled($this->draftOffenderSex) ? $this->draftOffenderSex : null;
        $this->remember();
        $this->dispatch('analytics-filter-closed');
    }

    public function resetSexFilter(): void
    {
        $this->victimSex = $this->offenderSex = null;
        $this->draftVictimSex = $this->draftOffenderSex = null;
        $this->resetValidation(['draftVictimSex', 'draftOffenderSex']);
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

    // "Victim: Female · Offender: Male", or null when no filter is applied.
    public function sexFilterLabel(): ?string
    {
        if (! $this->offersSexFilter() || $this->victimSex === null) {
            return null;
        }

        return 'Victim: '.AssessmentStats::SEXES[$this->victimSex]
            .' · Offender: '.($this->offenderSex === null ? 'Any' : AssessmentStats::SEXES[$this->offenderSex]);
    }

    protected function getViewData(): array
    {
        $range = $this->dateRange();

        return [
            'rangeLabel' => $range->label(),
            'sections' => $this->sections($range),
        ];
    }

    // The sex filter as the stats services take it: nothing unless this tab
    // offers it.
    protected function sexFilter(): array
    {
        return $this->offersSexFilter() ? [$this->victimSex, $this->offenderSex] : [null, null];
    }

    private function remember(): void
    {
        session([self::SESSION_KEY => [
            'range' => $this->range,
            'from' => $this->from,
            'to' => $this->to,
            'victimSex' => $this->victimSex,
            'offenderSex' => $this->offenderSex,
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

    private function validSex(mixed $value): ?string
    {
        return is_string($value) && isset(AssessmentStats::SEXES[$value]) ? $value : null;
    }
}
