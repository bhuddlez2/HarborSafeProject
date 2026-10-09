<?php

namespace App\Enums;

use Illuminate\Validation\Rule;

/*
The offender's relationship to the victim - the one list behind every
assessment's "Relationship to victim" dropdown (roadmap Phase P, decision 5:
a fixed list in code rather than an admin-editable table).

  - Records store the CODE (the case value), never the label. Labels can be
    reworded here without touching a single saved record, and the categories
    stay countable for reporting.
  - OTHER comes with a typed detail in OffenderVictimRelationshipOther, which
    is required when Other is picked and refused otherwise.
  - The civilian form gets this list from GET /api/public/relationships, so
    there is no second copy in JavaScript. The staff panel reads it directly.

Changing the list means a deploy, on purpose: these are safety records, and
categories that shift under them would change what past assessments appear
to say. Agree any change with Harbor Safe first. Labels capitalise every word.
*/
enum OffenderRelationship: string
{
    case Spouse = 'spouse';
    case FormerSpouse = 'former_spouse';
    case DatingPartner = 'dating_partner';
    case FormerDatingPartner = 'former_dating_partner';
    case LiveInPartner = 'live_in_partner';
    case CoParent = 'co_parent';
    case FamilyMember = 'family_member';
    case HouseholdMember = 'household_member';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Spouse => 'Spouse',
            self::FormerSpouse => 'Former Spouse',
            self::DatingPartner => 'Dating Partner',
            self::FormerDatingPartner => 'Former Dating Partner',
            self::LiveInPartner => 'Live-In Partner (Not Married)',
            self::CoParent => 'Co-Parent',
            self::FamilyMember => 'Family Member',
            self::HouseholdMember => 'Other Household Member',
            self::Other => 'Other (Please Specify)',
        };
    }

    /** @return array<string, string> code => label, in list order */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }

    /*
    The validation for the pair of fields, shared by both assessment APIs so
    they can't disagree: a code from this list (required), and the Other
    detail - required when Other is picked, refused otherwise, 50 characters.
    $partial is for an update, where the field may be left out but not
    cleared.
    */
    public static function validationRules(bool $partial = false): array
    {
        return [
            'OffenderVictimRelationship' => array_values(array_filter([
                $partial ? 'sometimes' : null,
                'required',
                Rule::enum(self::class),
            ])),
            'OffenderVictimRelationshipOther' => [
                'nullable',
                'string',
                'max:50',
                'required_if:OffenderVictimRelationship,'.self::Other->value,
                'prohibited_unless:OffenderVictimRelationship,'.self::Other->value,
            ],
        ];
    }

    /*
    What a person reads for a stored value: the label, or "Other: <detail>"
    for Other. A value that isn't a code (free text saved before this list
    existed and not mapped) is shown as it was typed, so no record ever
    displays blank.
    */
    public static function display(?string $code, ?string $other = null): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        $case = self::tryFrom($code);

        if ($case === null) {
            return $code;
        }

        if ($case === self::Other) {
            return filled($other) ? 'Other: '.$other : 'Other';
        }

        return $case->label();
    }
}
