<?php

namespace App\Models;

use App\Http\Requests\Public\ServiceFeedbackStoreRequest;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
Service feedback: online from the website's form, or typed into the panel from
a paper form by an admin or secretary (Source, below).

Only the three fields the public form sends are fillable. Source, EnteredBy,
SubmissionDate and ScanFileID are set explicitly by recordPaperForm() and never
mass-assigned, so the public endpoint - which creates from its validated input
- cannot mark a submission as paper or claim a staff author.
*/
class ServiceFeedback extends BaseModel
{
    use HasUuids;

    public const SOURCE_ONLINE = 'online';

    public const SOURCE_PAPER = 'paper';

    protected $connection = 'Feedback';

    protected $table = 'service_feedback';

    protected $primaryKey = 'FormID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'ServiceID',
        'Rating',
        'Comment',
    ];

    protected function casts(): array
    {
        return [
            'SubmissionDate' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // BaseModel::booted() enforces the $connection declaration.
        parent::booted();

        // A scan goes with its entry: deleting a mistyped paper entry (the
        // way to correct one - there is no edit) must not leave a handwritten
        // form behind. Query builder, so the blob is never loaded. Fires for
        // bulk deletes too, because the resource fetches the selected records
        // and deletes them one by one.
        static::deleted(function (self $feedback): void {
            if ($feedback->ScanFileID) {
                DB::connection('Feedback')->table('feedback_scans')
                    ->where('FileID', $feedback->ScanFileID)
                    ->delete();
            }
        });
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function service()
    {
        return $this->belongsTo(Service::class, 'ServiceID');
    }

    public function isPaper(): bool
    {
        return $this->Source === self::SOURCE_PAPER;
    }

    // The staff member who typed a paper form in. users is on Portal, so this
    // is a lookup rather than a relation that could be joined.
    public function enteredByName(): ?string
    {
        return $this->EnteredBy ? User::find($this->EnteredBy)?->name : null;
    }

    /*
    Saves a paper form typed in by $enteredBy. $data is the panel form's
    validated state, which used ServiceFeedbackStoreRequest's own rules, so a
    paper entry passes exactly the checks an online one does.

    SubmissionDate is the date written on the paper, at midnight - not when it
    was typed in.
    */
    public static function recordPaperForm(array $data, User $enteredBy): self
    {
        $feedback = new self;

        $feedback->forceFill([
            'ServiceID' => (int) $data['ServiceID'],
            'Rating' => (int) $data['Rating'],
            'Comment' => ServiceFeedbackStoreRequest::normalizeText($data['Comment'] ?? null),
            'SubmissionDate' => Carbon::parse($data['SubmissionDate'])->startOfDay(),
            'Source' => self::SOURCE_PAPER,
            'EnteredBy' => $enteredBy->getKey(),
            'ScanFileID' => $data['ScanFileID'] ?? null,
        ])->save();

        return $feedback;
    }
}
