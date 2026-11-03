<?php

use App\Enums\OffenderRelationship;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
"Relationship to victim" becomes a fixed list (App\Enums\OffenderRelationship,
roadmap Phase P) on both assessment tables.

  - OffenderVictimRelationship keeps its column (string 50) and now holds the
    option's CODE ('spouse', 'dating_partner', ...).
  - New OffenderVictimRelationshipOther (string 50, nullable): the typed
    detail when the option is Other.
  - Existing free text is mapped once. Obvious matches become their option
    (case and surrounding spaces ignored); anything else - including the
    ambiguous "Partner" - becomes Other with the original text kept as the
    detail, so nothing is lost. NULL stays NULL.

Query builder throughout: LawEnforcementAssessment refuses any update outside
App\Services\AssessmentEditor, and a one-off data mapping is not an officer's
edit, so it is not change-logged.
*/
return new class extends Migration
{
    protected $connection = 'Portal';

    private const TABLES = ['law_enforcement_assessment', '_private_assessment'];

    // Lower-cased free text => option code.
    private const MAP = [
        'spouse' => ['spouse', 'husband', 'wife', 'married', 'married partner'],
        'former_spouse' => [
            'former spouse', 'ex-spouse', 'ex spouse', 'ex-husband', 'ex husband', 'exhusband',
            'ex-wife', 'ex wife', 'exwife', 'former husband', 'former wife', 'divorced',
        ],
        'dating_partner' => ['dating partner', 'boyfriend', 'girlfriend', 'dating', 'bf', 'gf'],
        'former_dating_partner' => [
            'former dating partner', 'ex-boyfriend', 'ex boyfriend', 'ex-girlfriend', 'ex girlfriend',
            'former boyfriend', 'former girlfriend', 'ex',
        ],
        'live_in_partner' => ['live-in partner', 'live in partner', 'live-in', 'cohabiting', 'cohabiting partner'],
        'co_parent' => ['co-parent', 'co parent', 'coparent', "child's father", "child's mother"],
        'family_member' => [
            'family member', 'family', 'father', 'mother', 'dad', 'mom', 'parent', 'son', 'daughter',
            'child', 'brother', 'sister', 'sibling',
        ],
        'household_member' => ['household member', 'roommate', 'room mate', 'housemate'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::connection('Portal')->table($table, function (Blueprint $blueprint) {
                $blueprint->string('OffenderVictimRelationshipOther', 50)->nullable()->after('OffenderVictimRelationship');
            });

            $db = DB::connection('Portal');

            $rows = $db->table($table)
                ->whereNotNull('OffenderVictimRelationship')
                ->get(['DocumentID', 'OffenderVictimRelationship']);

            foreach ($rows as $row) {
                $text = trim((string) $row->OffenderVictimRelationship);

                // Already a code (e.g. a re-run): leave it.
                if (OffenderRelationship::tryFrom($text) !== null) {
                    continue;
                }

                $code = $this->codeFor($text);

                $db->table($table)->where('DocumentID', $row->DocumentID)->update([
                    'OffenderVictimRelationship' => $code ?? OffenderRelationship::Other->value,
                    // Keep the original wording whenever it didn't map cleanly.
                    'OffenderVictimRelationshipOther' => $code === null ? ($text === '' ? null : $text) : null,
                ]);
            }
        }
    }

    // Back to readable free text: the label, or Other's typed detail.
    public function down(): void
    {
        foreach (self::TABLES as $table) {
            $db = DB::connection('Portal');

            foreach ($db->table($table)->whereNotNull('OffenderVictimRelationship')
                ->get(['DocumentID', 'OffenderVictimRelationship', 'OffenderVictimRelationshipOther']) as $row) {
                $case = OffenderRelationship::tryFrom($row->OffenderVictimRelationship);

                if ($case === null) {
                    continue;
                }

                $db->table($table)->where('DocumentID', $row->DocumentID)->update([
                    'OffenderVictimRelationship' => mb_substr($case === OffenderRelationship::Other
                        ? ($row->OffenderVictimRelationshipOther ?: 'Other')
                        : $case->label(), 0, 50),
                ]);
            }

            Schema::connection('Portal')->table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn('OffenderVictimRelationshipOther');
            });
        }
    }

    private function codeFor(string $text): ?string
    {
        $needle = mb_strtolower(preg_replace('/\s+/', ' ', trim($text)));

        foreach (self::MAP as $code => $words) {
            if (in_array($needle, $words, true)) {
                return $code;
            }
        }

        return null;
    }
};
