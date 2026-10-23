<?php

namespace App\Services\Analytics;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use App\Support\Analytics\DateRange;
use Illuminate\Support\Facades\DB;

/*
The numbers behind Analytics' Follow-ups tab (team plan task 28): how Harbor
Safe's follow-up (App\Services\FollowUps) is going for the forms submitted in
the range - officer assessments, civilian assessments and resource requests,
never service feedback.

"First contact" is a form's first status change (the first follow_up_entries
row with a FromStatus): a note on its own doesn't count. Forms not yet moved
from Needs follow-up have no first contact and are left out of those numbers.
*/
final class FollowUpStats
{
    // [label, upper bound in minutes (exclusive)], for the time-to-first-contact chart.
    public const CONTACT_BANDS = [
        ['Under 4 h', 4 * 60],
        ['4–24 h', 24 * 60],
        ['24–48 h', 48 * 60],
        ['Over 48 h', null],
    ];

    public function summary(DateRange $range): array
    {
        $portal = DB::connection('Portal');
        $base = $portal->table('follow_ups as fu')->whereBetween('fu.SubmittedAt', $range->between());

        $counts = (clone $base)
            ->selectRaw('fu.Kind AS kind, fu.Status AS status, COUNT(*) AS n')
            ->groupBy('fu.Kind', 'fu.Status')
            ->get();

        // [kind value => [status value => n]], every kind and status present.
        $byKind = [];

        foreach (FollowUpKind::cases() as $kind) {
            foreach (FollowUpStatus::cases() as $status) {
                $byKind[$kind->value][$status->value] = 0;
            }
        }

        foreach ($counts as $row) {
            if (isset($byKind[$row->kind][$row->status])) {
                $byKind[$row->kind][$row->status] = (int) $row->n;
            }
        }

        $byStatus = [];

        foreach (FollowUpStatus::cases() as $status) {
            $byStatus[$status->value] = array_sum(array_column($byKind, $status->value));
        }

        $reminders = (clone $base)
            ->selectRaw('SUM(CASE WHEN fu.RemindersSent >= 1 THEN 1 ELSE 0 END) AS first, SUM(CASE WHEN fu.RemindersSent >= 2 THEN 1 ELSE 0 END) AS second')
            ->first();

        $firstContact = $portal->table('follow_up_entries')
            ->whereNotNull('FromStatus')
            ->selectRaw('FollowUpID, MIN(EnteredAt) AS first_at')
            ->groupBy('FollowUpID');

        // Minutes from submission to first contact, one number per form.
        $minutes = (clone $base)
            ->joinSub($firstContact, 'fc', 'fc.FollowUpID', '=', 'fu.FollowUpID')
            ->selectRaw('TIMESTAMPDIFF(MINUTE, fu.SubmittedAt, fc.first_at) AS minutes')
            ->pluck('minutes')
            ->map(fn ($m): int => max(0, (int) $m))
            ->sort()
            ->values()
            ->all();

        return [
            'total' => array_sum($byStatus),
            'byStatus' => $byStatus,
            'byKind' => $byKind,
            'open' => $byStatus[FollowUpStatus::NeedsFollowUp->value] + $byStatus[FollowUpStatus::AttemptedContact->value],
            'reached' => $byStatus[FollowUpStatus::Reached->value],
            'remindedOnce' => (int) $reminders->first,
            'remindedTwice' => (int) $reminders->second,
            'medianMinutes' => $this->median($minutes),
            'contactBands' => $this->bands($minutes),
        ];
    }

    // "45 minutes", "18 hours", "3.5 days"; null when nothing to measure.
    public static function formatMinutes(?int $minutes): ?string
    {
        return match (true) {
            $minutes === null => null,
            $minutes < 60 => $minutes.' '.str('minute')->plural($minutes),
            $minutes < 72 * 60 => ($hours = (int) round($minutes / 60)).' '.str('hour')->plural($hours),
            default => rtrim(rtrim(number_format($minutes / 1440, 1), '0'), '.').' days',
        };
    }

    private function median(array $sorted): ?int
    {
        $count = count($sorted);

        if ($count === 0) {
            return null;
        }

        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? $sorted[$middle]
            : (int) round(($sorted[$middle - 1] + $sorted[$middle]) / 2);
    }

    private function bands(array $minutes): array
    {
        $result = array_fill_keys(array_column(self::CONTACT_BANDS, 0), 0);

        foreach ($minutes as $m) {
            foreach (self::CONTACT_BANDS as [$label, $below]) {
                if ($below === null || $m < $below) {
                    $result[$label]++;
                    break;
                }
            }
        }

        return $result;
    }
}
