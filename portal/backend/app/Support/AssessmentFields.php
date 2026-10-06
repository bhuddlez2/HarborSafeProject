<?php

namespace App\Support;

use App\Filament\Pages\NewAssessment;
use Carbon\CarbonImmutable;

/*
Human-readable labels and values for law-enforcement assessment fields, for
the change history. The log stores raw values - column names, sex codes,
Y-m-d dates, booleans - so it stays exact; this is where they become
"Victim last name: Doe -> Smith" for a person to read.
*/
final class AssessmentFields
{
    // In the order the edit form lays them out.
    public const DETAIL_LABELS = [
        'VictimFirstName' => 'Victim first name',
        'VictimLastName' => 'Victim last name',
        'VictimSex' => 'Victim sex',
        'VictimDOB' => 'Victim date of birth',
        'VictimSafePhoneNumber' => 'Victim safe contact number',
        'OffenderFirstName' => 'Offender first name',
        'OffenderLastName' => 'Offender last name',
        'OffenderSex' => 'Offender sex',
        'OffenderDOB' => 'Offender date of birth',
        'OffenderVictimRelationship' => 'Relationship to victim',
    ];

    public const SEX_OPTIONS = [
        'M' => 'Male',
        'F' => 'Female',
        'O' => 'Other',
    ];

    public static function label(string $field): string
    {
        if (preg_match('/^RiskIndicator(\d+)$/', $field, $match)) {
            $number = (int) $match[1];

            return "Q{$number}: ".(NewAssessment::QUESTIONS[$number] ?? $field);
        }

        return self::DETAIL_LABELS[$field] ?? $field;
    }

    public static function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        if (str_starts_with($field, 'RiskIndicator')) {
            return $value ? 'Yes' : 'No';
        }

        if (str_ends_with($field, 'Sex')) {
            return self::SEX_OPTIONS[$value] ?? (string) $value;
        }

        if (str_ends_with($field, 'DOB')) {
            try {
                return CarbonImmutable::parse((string) $value)->format('M j, Y');
            } catch (\Throwable) {
                return (string) $value;
            }
        }

        return (string) $value;
    }

    /** @return array{label: string, from: string, to: string} */
    public static function describe(string $field, mixed $from, mixed $to): array
    {
        return [
            'label' => self::label($field),
            'from' => self::value($field, $from),
            'to' => self::value($field, $to),
        ];
    }
}
