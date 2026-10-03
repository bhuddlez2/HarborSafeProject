<?php

namespace Database\Seeders;

use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAssessment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
Two law-enforcement assessments per local officer, so My Assessments has
something to list and the "own submissions only" rule has a second officer to
be checked against. Local development only. Deliberately not registered in
DatabaseSeeder. Run LocalStaffUserSeeder first - it creates the two officers.

  php artisan db:seed --class=LocalAssessmentSeeder --database=Portal

Idempotent: a record is skipped when that officer already has one with the
same victim name. Never deletes anything.
*/
class LocalAssessmentSeeder extends Seeder
{
    // Officer email => [victim/offender suffix => the 11 risk-indicator answers].
    private const RECORDS = [
        'officer@harborsafe.test' => [
            'A' => [true, false, true, false, false, false, true, false, false, false, true],
            'B' => [false, false, false, true, true, false, false, false, false, false, false],
        ],
        'officer2@harborsafe.test' => [
            'C' => [true, true, true, true, false, false, true, true, false, false, true],
            'D' => [false, true, false, false, false, true, false, false, true, true, false],
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalAssessmentSeeder only runs when APP_ENV=local.');
        }

        $officers = User::whereIn('email', array_keys(self::RECORDS))->get()->keyBy('email');
        $missing = array_diff(array_keys(self::RECORDS), $officers->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException(
                'LocalAssessmentSeeder needs '.implode(' and ', $missing)
                .'. Run LocalStaffUserSeeder first: php artisan db:seed --class=LocalStaffUserSeeder --database=Portal'
            );
        }

        foreach (self::RECORDS as $email => $records) {
            $officerId = $officers[$email]->getKey();

            foreach ($records as $suffix => $answers) {
                $exists = LawEnforcementAssessment::where('submitted_by', $officerId)
                    ->where('VictimFirstName', 'Test')
                    ->where('VictimLastName', "Victim {$suffix}")
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::connection('Portal')->transaction(function () use ($officerId, $suffix, $answers): void {
                    $indicators = [];

                    foreach ($answers as $index => $answer) {
                        $indicators['RiskIndicator'.($index + 1)] = $answer;
                    }

                    $answerRow = AssessmentAnswers::create($indicators);

                    LawEnforcementAssessment::create([
                        'submitted_by' => $officerId,
                        'VictimFirstName' => 'Test',
                        'VictimLastName' => "Victim {$suffix}",
                        'VictimSex' => 'F',
                        'VictimDOB' => '1990-01-15',
                        'VictimSafePhoneNumber' => '555-0100',
                        'OffenderFirstName' => 'Test',
                        'OffenderLastName' => "Offender {$suffix}",
                        'OffenderSex' => 'M',
                        'OffenderDOB' => '1988-06-30',
                        'OffenderVictimRelationship' => 'Spouse',
                        'AssessmentDocID' => $answerRow->getKey(),
                    ]);
                });
            }
        }
    }
}
