<?php

namespace App\Models;

use App\Enums\LogAction;
use App\Models\Concerns\AppendOnly;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use LogicException;

/*
One row of the Logs page: a view or PDF download (assessment_access_log) or an
edit (assessment_edits, one row per save), read together as one list.

Assessments only - officer and civilian (Finishing Touches item 28). The
access log also records resource request PDF downloads and follow-up page
views, but those rows stay out of this list; they are left out here, in the
access half, before anything else applies.

There is no log_entries table and no database view. The rows come from
visibleTo(), which UNIONs the two tables in a subquery and names it
log_entries. Each half is scoped BEFORE the union by its own scope -
AssessmentAccessLog::visibleTo() and AssessmentEdit::visibleTo(), both built on
LawEnforcementAssessment::visibleTo() (Filament_CMS_Design.md section 5,
rule 5) - so nothing applied to the union afterwards (search, filters, the Show
dropdown) can bring back a row either scope left out. A plain
LogEntry::query() reads the missing table and fails, so the only way in is the
scoped one.

Common columns: EntryID (the AccessID or EditID), OccurredAt, UserID (who
viewed, downloaded or edited), Action (LogAction), the two assessment
pointers, and EditID on edit rows. An edit row's assessment sits in LawEnforcementDocumentID,
like an access row about an officer assessment, so the record column and every
officer-assessment filter treat both the same way.

Read-only: rows are written to the two real tables by AssessmentAccessLog::
record() and App\Services\AssessmentEditor, never here.
*/
class LogEntry extends BaseModel
{
    use AppendOnly;

    protected $connection = 'Portal';

    protected $table = 'log_entries';

    protected $primaryKey = 'EntryID';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'OccurredAt' => 'datetime',
            'Action' => LogAction::class,
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn () => throw new LogicException(static::class.' is read-only.'));
    }

    // The rows $user may see: each half scoped by its own visibleTo(), then
    // combined. Never build the union from unscoped halves.
    public static function visibleTo(?User $user): Builder
    {
        $access = AssessmentAccessLog::query()
            ->visibleTo($user)
            ->whereNull('ResourceRequestFormID')
            ->select([
                'AccessID as EntryID',
                'AccessedAt as OccurredAt',
                'AccessedBy as UserID',
                'Action',
                'LawEnforcementDocumentID',
                'PrivateDocumentID',
                DB::raw('NULL as EditID'),
            ]);

        $edits = AssessmentEdit::query()
            ->visibleTo($user)
            ->select([
                'EditID as EntryID',
                'EditedAt as OccurredAt',
                'ChangedBy as UserID',
                DB::raw("'".LogAction::Edit->value."' as Action"),
                'DocumentID as LawEnforcementDocumentID',
                DB::raw('NULL as PrivateDocumentID'),
                'EditID',
            ]);

        $table = (new static)->getTable();

        // The explicit select: withCount() and friends otherwise build one
        // from the FROM, which is the subquery, not a table name.
        return static::query()
            ->fromSub($access->toBase()->unionAll($edits->toBase()), $table)
            ->select("{$table}.*");
    }

    public function isEdit(): bool
    {
        return $this->EditID !== null;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'UserID');
    }

    public function lawEnforcementAssessment()
    {
        return $this->belongsTo(LawEnforcementAssessment::class, 'LawEnforcementDocumentID');
    }

    public function privateAssessment()
    {
        return $this->belongsTo(PrivateAssessment::class, 'PrivateDocumentID');
    }

    public function edit()
    {
        return $this->belongsTo(AssessmentEdit::class, 'EditID');
    }

    public function detailChanges()
    {
        return $this->hasMany(AssessmentChangeLog::class, 'EditID', 'EditID');
    }

    public function answerChanges()
    {
        return $this->hasMany(AssessmentAnswerChangeLog::class, 'EditID', 'EditID');
    }
}
