<?php

namespace App\Services\Analytics;

use App\Models\ServiceFeedback;
use App\Models\ResourceRequestForm;
use App\Support\Analytics\Chart;
use App\Support\Analytics\DateRange;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;

/*
The numbers behind Analytics' "Feedback and resource requests" tab (team plan
task 28), read on the full-access Feedback connection like Submissions - the
restricted FeedbackPublic user has no SELECT on submissions by design. Totals
only: no comments (free text), no requester names, emails or phone numbers.
Both forms count staff-entered rows too, and say how many there were.
*/
final class FeedbackStats
{
    public function serviceFeedback(DateRange $range): array
    {
        $feedback = DB::connection('Feedback');
        $base = $feedback->table('service_feedback as f')->whereBetween('f.SubmissionDate', $range->between());

        $totals = (clone $base)
            ->selectRaw('COUNT(*) AS total, AVG(f.Rating) AS average, SUM(CASE WHEN f.Source = ? THEN 1 ELSE 0 END) AS staff', [ServiceFeedback::SOURCE_STAFF])
            ->first();

        // Every service on the form's list, zeros included.
        $services = $feedback->table('services as s')
            ->leftJoin('service_feedback as f', fn (JoinClause $join) => $join
                ->on('f.ServiceID', '=', 's.id')
                ->whereBetween('f.SubmissionDate', $range->between()))
            ->selectRaw('s.Name AS name, COUNT(f.FormID) AS n, AVG(f.Rating) AS average')
            ->groupBy('s.id', 's.Name')
            ->orderBy('s.Name')
            ->get();

        $ratings = (clone $base)->selectRaw('f.Rating AS rating, COUNT(*) AS n')->groupBy('f.Rating')->pluck('n', 'rating');
        $total = (int) $totals->total;

        return [
            'total' => $total,
            'averageRating' => $total > 0 ? round((float) $totals->average, 1) : null,
            'staff' => (int) $totals->staff,
            'perService' => $services->mapWithKeys(fn ($row): array => [$row->name => (int) $row->n])->all(),
            'averagePerService' => $services->mapWithKeys(fn ($row): array => [
                $row->name => $row->average === null ? null : round((float) $row->average, 1),
            ])->all(),
            'ratings' => collect(range(1, 5))->mapWithKeys(fn (int $r): array => [$r => (int) ($ratings[$r] ?? 0)])->all(),
            'periods' => Chart::perPeriod($base, 'f.SubmissionDate', $range),
        ];
    }

    public function resourceRequests(DateRange $range): array
    {
        $feedback = DB::connection('Feedback');
        $base = $feedback->table('resource_request_form as r')->whereBetween('r.SubmissionDate', $range->between());

        $totals = (clone $base)
            ->selectRaw('COUNT(*) AS total, SUM(CASE WHEN r.Source = ? THEN 1 ELSE 0 END) AS staff', [ResourceRequestForm::SOURCE_STAFF])
            ->first();

        // Every county on the form's list, zeros included, then any request
        // that named none.
        $counties = $feedback->table('counties as c')
            ->leftJoin('resource_request_form as r', fn (JoinClause $join) => $join
                ->on('r.CountyID', '=', 'c.id')
                ->whereBetween('r.SubmissionDate', $range->between()))
            ->selectRaw('c.Name AS name, COUNT(r.FormID) AS n')
            ->groupBy('c.id', 'c.Name')
            ->orderBy('c.Name')
            ->pluck('n', 'name')
            ->map(fn ($n): int => (int) $n)
            ->all();

        $noCounty = (clone $base)->whereNull('r.CountyID')->count();

        if ($noCounty > 0) {
            $counties['Not given'] = $noCounty;
        }

        // A request may name several types, so these add up to more than the
        // total. Every type on the list, zeros included.
        $types = $feedback->table('resources as t')
            ->leftJoin('resource_request_resource_types as j', 'j.ResourceTypeID', '=', 't.id')
            ->leftJoin('resource_request_form as r', fn (JoinClause $join) => $join
                ->on('r.FormID', '=', 'j.FormID')
                ->whereBetween('r.SubmissionDate', $range->between()))
            ->selectRaw('t.Name AS name, COUNT(r.FormID) AS n')
            ->groupBy('t.id', 't.Name')
            ->orderBy('t.Name')
            ->pluck('n', 'name')
            ->map(fn ($n): int => (int) $n)
            ->all();

        return [
            'total' => (int) $totals->total,
            'staff' => (int) $totals->staff,
            'perCounty' => $counties,
            'countiesReached' => count(array_filter($counties, fn (int $n, string $name): bool => $n > 0 && $name !== 'Not given', ARRAY_FILTER_USE_BOTH)),
            'perType' => $types,
            'periods' => Chart::perPeriod($base, 'r.SubmissionDate', $range),
        ];
    }
}
