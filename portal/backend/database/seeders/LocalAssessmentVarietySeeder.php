<?php

namespace Database\Seeders;

use App\Models\AssessmentAnswers;
use App\Models\LawEnforcementAssessment;
use App\Models\PrivateAssessment;
use App\Models\SubmitterInfo;
use App\Models\User;
use App\Services\AssessmentEditor;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/*
Varied assessments for exercising the View, Edit, Change log and filter
screens: every relationship option including Other, all three sexes, 0 and 11
"yes" answers, a missing offender date of birth, names at the 50-character
limit, submission dates spread over three months, edit history, and civilian
submissions both anonymous and with submitter details. Local development only,
not registered in DatabaseSeeder. Run LocalStaffUserSeeder first.

  php artisan db:seed --class=LocalAssessmentVarietySeeder --database=Portal

Edits go through AssessmentEditor as the submitting officer, exactly as the
Edit pop-up does, and are backdated so the history reads in order.

Idempotent: a record is skipped, edits included, when that submitter already
has one with the same victim first name and offender name. Never deletes
anything.
*/
class LocalAssessmentVarietySeeder extends Seeder
{
    private const LAW_ENFORCEMENT = [
        [
            'officer' => 'officer@harborsafe.test',
            'days_ago' => 2,
            'victim' => ['Maria', 'Delgado', 'F', '1991-03-12', '555-0141'],
            'offender' => ['Victor', 'Delgado', 'M', '1987-11-02'],
            'relationship' => ['spouse', null],
            'yes' => [1, 2, 3, 5, 7, 8, 11],
            'edits' => [
                [
                    'hours_later' => 3,
                    'reason' => 'Offender date of birth was typed with the wrong year.',
                    'details' => ['OffenderDOB' => '1986-11-02'],
                    'answers' => [],
                ],
                [
                    'hours_later' => 30,
                    'reason' => 'Victim called back with a new safe number and clarified two answers.',
                    'details' => ['VictimSafePhoneNumber' => '555-0199'],
                    'answers' => ['RiskIndicator4' => true, 'RiskIndicator8' => false],
                ],
            ],
        ],
        [
            'officer' => 'officer@harborsafe.test',
            'days_ago' => 10,
            'victim' => ['Jordan', 'Lee', 'O', '1998-07-21', '555-0142'],
            'offender' => ['Casey', 'Morgan', 'O', null],
            'relationship' => ['other', "Former roommate's partner"],
            'yes' => [],
            'edits' => [],
        ],
        [
            'officer' => 'officer@harborsafe.test',
            'days_ago' => 25,
            'victim' => ["Aaliyah", "O'Connor-Whitfield", 'F', '1995-01-30', '555-0143'],
            'offender' => ['Derek', 'Banks', 'M', '1993-09-14'],
            'relationship' => ['dating_partner', null],
            'yes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'edits' => [
                [
                    'hours_later' => 48,
                    'reason' => 'Relationship had ended before the incident.',
                    'details' => ['OffenderVictimRelationship' => 'former_dating_partner'],
                    'answers' => [],
                ],
            ],
        ],
        [
            'officer' => 'officer@harborsafe.test',
            'days_ago' => 45,
            'victim' => ['Alexandria-Josephine', 'Featherstonehaugh-Montgomery-Worthington-Hale', 'F', '1989-05-05', '555-0144'],
            'offender' => ['Maximilian-Theodore', 'Vanderbilt-Ashworth-Pemberton', 'M', '1985-12-25'],
            'relationship' => ['co_parent', null],
            'yes' => [2, 6, 9, 10],
            'edits' => [],
        ],
        [
            'officer' => 'officer2@harborsafe.test',
            'days_ago' => 3,
            'victim' => ['Priya', 'Raman', 'F', '1992-08-08', '555-0151'],
            'offender' => ['Arjun', 'Raman', 'M', '1990-02-17'],
            'relationship' => ['former_spouse', null],
            'yes' => [1, 3, 5, 7, 9],
            'edits' => [
                [
                    'hours_later' => 2,
                    'reason' => 'Corrected spelling of the victim\'s last name.',
                    'details' => ['VictimLastName' => 'Ramanathan'],
                    'answers' => [],
                ],
                [
                    'hours_later' => 20,
                    'reason' => 'Follow-up interview: offender has threatened to kill her.',
                    'details' => [],
                    'answers' => ['RiskIndicator2' => true],
                ],
                [
                    'hours_later' => 50,
                    'reason' => 'Offender is not employed; answer was recorded backwards.',
                    'details' => [],
                    'answers' => ['RiskIndicator9' => false, 'RiskIndicator10' => true],
                ],
            ],
        ],
        [
            'officer' => 'officer2@harborsafe.test',
            'days_ago' => 60,
            'victim' => ['Tomás', 'Ruiz', 'M', '1984-04-04', '555-0152'],
            'offender' => ['Daniel', 'Ortiz', 'M', '1982-10-10'],
            'relationship' => ['live_in_partner', null],
            'yes' => [4, 8],
            'edits' => [],
        ],
        [
            'officer' => 'officer3@harborsafe.test',
            'days_ago' => 1,
            'victim' => ['Grace', 'Kim', 'F', '2000-06-19', '555-0161'],
            'offender' => ['Brandon', 'Hale', 'M', '1999-03-03'],
            'relationship' => ['family_member', null],
            'yes' => [1, 2, 4, 6, 7, 11],
            'edits' => [
                [
                    'hours_later' => 5,
                    'reason' => 'Offender is her stepbrother, not a sibling; recorded as household member.',
                    'details' => ['OffenderVictimRelationship' => 'household_member'],
                    'answers' => [],
                ],
            ],
        ],
        [
            'officer' => 'officer3@harborsafe.test',
            'days_ago' => 90,
            'victim' => ['Hannah', 'Walsh', 'F', '1979-12-01', '555-0162'],
            'offender' => ['Unknown', 'Walsh', 'M', null],
            'relationship' => ['other', 'Ex-partner of her adult daughter'],
            'yes' => [5],
            'edits' => [],
        ],
    ];

    // Most civilian submissions are anonymous; submitter details appear only
    // when someone files for another person and declines anonymity.
    private const CIVILIAN = [
        [
            'days_ago' => 1,
            'victim' => ['Renee', 'Carter', 'F', '1990-09-09', '555-0171'],
            'offender' => ['Mark', 'Carter', 'M', '1988-01-20'],
            'relationship' => ['spouse', null],
            'yes' => [1, 2, 3, 4, 5, 7, 8, 11],
            'submitter' => null,
        ],
        [
            'days_ago' => 6,
            'victim' => ['Sofia', 'Mendes', 'F', '1997-02-14', '555-0172'],
            'offender' => ['Rafael', 'Costa', 'M', null],
            'relationship' => ['other', "Sister's ex-husband"],
            'yes' => [2, 6, 9],
            'submitter' => ['Lisa', 'Park', 'lisa.park@example.test', '555-0181'],
        ],
        [
            'days_ago' => 15,
            'victim' => ['Avery', 'Brooks', 'O', '2001-11-30', '555-0173'],
            'offender' => ['Taylor', 'Quinn', 'O', '2000-05-06'],
            'relationship' => ['former_dating_partner', null],
            'yes' => [],
            'submitter' => null,
        ],
        [
            'days_ago' => 33,
            'victim' => ['Denise', 'Holloway', 'F', '1975-07-07', '555-0174'],
            'offender' => ['Curtis', 'Holloway', 'M', '1973-03-15'],
            'relationship' => ['co_parent', null],
            'yes' => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11],
            'submitter' => ['James', 'Holloway', null, '555-0182'],
        ],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            throw new RuntimeException('LocalAssessmentVarietySeeder only runs when APP_ENV=local.');
        }

        $emails = array_unique(array_column(self::LAW_ENFORCEMENT, 'officer'));
        $officers = User::whereIn('email', $emails)->get()->keyBy('email');
        $missing = array_diff($emails, $officers->keys()->all());

        if ($missing !== []) {
            throw new RuntimeException(
                'LocalAssessmentVarietySeeder needs '.implode(' and ', $missing)
                .'. Run LocalStaffUserSeeder first: php artisan db:seed --class=LocalStaffUserSeeder --database=Portal'
            );
        }

        try {
            foreach (self::LAW_ENFORCEMENT as $spec) {
                $this->seedLawEnforcement($spec, $officers[$spec['officer']]);
            }

            foreach (self::CIVILIAN as $spec) {
                $this->seedCivilian($spec);
            }
        } finally {
            Carbon::setTestNow();
        }
    }

    private function seedLawEnforcement(array $spec, User $officer): void
    {
        [$victimFirst] = $spec['victim'];
        [$offenderFirst, $offenderLast] = $spec['offender'];

        // Matched on names the seeded edits never change (one renames the
        // victim's last name), so a re-run still recognises the record.
        $exists = LawEnforcementAssessment::where('submitted_by', $officer->getKey())
            ->where('VictimFirstName', $victimFirst)
            ->where('OffenderFirstName', $offenderFirst)
            ->where('OffenderLastName', $offenderLast)
            ->exists();

        if ($exists) {
            return;
        }

        $created = now()->subDays($spec['days_ago'])->setTime(14, 30);

        $record = DB::connection('Portal')->transaction(function () use ($spec, $officer, $created): LawEnforcementAssessment {
            $record = LawEnforcementAssessment::create([
                'submitted_by' => $officer->getKey(),
                ...$this->personColumns($spec),
                'AssessmentDocID' => AssessmentAnswers::create($this->answers($spec['yes']))->getKey(),
            ]);

            // DateCreated is filled by the database; the query builder sidesteps
            // the model's editor-only guard for this one backdate.
            DB::connection('Portal')->table('law_enforcement_assessment')
                ->where('DocumentID', $record->getKey())
                ->update(['DateCreated' => $created]);

            return $record;
        });

        foreach ($spec['edits'] as $edit) {
            Carbon::setTestNow($created->copy()->addHours($edit['hours_later']));

            $logged = AssessmentEditor::apply($record, $edit['details'], $edit['answers'], $edit['reason'], $officer);

            // EditedAt follows the test clock; the field rows' TimeStamp is the
            // database's own clock, so bring it in line.
            if ($logged !== null) {
                foreach (['assessment_change_log', 'assessment_answer_change_log'] as $table) {
                    DB::connection('Portal')->table($table)
                        ->where('EditID', $logged->getKey())
                        ->update(['TimeStamp' => now()]);
                }
            }

            Carbon::setTestNow();
        }
    }

    private function seedCivilian(array $spec): void
    {
        [$victimFirst, $victimLast] = $spec['victim'];

        $exists = PrivateAssessment::where('VictimFirstName', $victimFirst)
            ->where('VictimLastName', $victimLast)
            ->exists();

        if ($exists) {
            return;
        }

        DB::connection('Portal')->transaction(function () use ($spec): void {
            $submissionId = null;

            if ($spec['submitter'] !== null) {
                [$first, $last, $email, $phone] = $spec['submitter'];

                $submissionId = SubmitterInfo::create([
                    'SubmitterFirstName' => $first,
                    'SubmitterLastName' => $last,
                    'SubmitterEmail' => $email,
                    'SubmitterPhoneNumber' => $phone,
                ])->getKey();
            }

            $record = PrivateAssessment::create([
                ...$this->personColumns($spec),
                'SubmissionID' => $submissionId,
                'AssessmentDocID' => AssessmentAnswers::create($this->answers($spec['yes']))->getKey(),
            ]);

            DB::connection('Portal')->table('_private_assessment')
                ->where('DocumentID', $record->getKey())
                ->update(['DateCreated' => now()->subDays($spec['days_ago'])->setTime(9, 15)]);
        });
    }

    private function personColumns(array $spec): array
    {
        [$victimFirst, $victimLast, $victimSex, $victimDob, $phone] = $spec['victim'];
        [$offenderFirst, $offenderLast, $offenderSex, $offenderDob] = $spec['offender'];
        [$relationship, $other] = $spec['relationship'];

        return [
            'VictimFirstName' => $victimFirst,
            'VictimLastName' => $victimLast,
            'VictimSex' => $victimSex,
            'VictimDOB' => $victimDob,
            'VictimSafePhoneNumber' => $phone,
            'OffenderFirstName' => $offenderFirst,
            'OffenderLastName' => $offenderLast,
            'OffenderSex' => $offenderSex,
            'OffenderDOB' => $offenderDob,
            'OffenderVictimRelationship' => $relationship,
            'OffenderVictimRelationshipOther' => $other,
        ];
    }

    /** @param  list<int>  $yes  the risk indicators answered "yes" */
    private function answers(array $yes): array
    {
        $answers = [];

        foreach (range(1, 11) as $n) {
            $answers["RiskIndicator{$n}"] = in_array($n, $yes, true);
        }

        return $answers;
    }
}
