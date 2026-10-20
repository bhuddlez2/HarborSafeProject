<?php

namespace App\Models;

use App\Enums\FollowUpKind;
use App\Enums\FollowUpStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

// Harbor Safe's follow-up on one form, as it stands now (follow_ups). The
// history is in entries(). Opened and updated only through
// App\Services\FollowUps, which enforces the rules.
class FollowUp extends BaseModel
{
    use HasUuids;

    protected $connection = 'Portal';

    protected $table = 'follow_ups';

    protected $primaryKey = 'FollowUpID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'Kind',
        'LawEnforcementDocumentID',
        'PrivateDocumentID',
        'ResourceRequestFormID',
        'Status',
        'SubmittedAt',
        'LastUpdatedAt',
        'LastUpdatedBy',
        'RemindersSent',
    ];

    protected function casts(): array
    {
        return [
            'Kind' => FollowUpKind::class,
            'Status' => FollowUpStatus::class,
            'SubmittedAt' => 'datetime',
            'LastUpdatedAt' => 'datetime',
            'RemindersSent' => 'integer',
        ];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function lawEnforcementAssessment()
    {
        return $this->belongsTo(LawEnforcementAssessment::class, 'LawEnforcementDocumentID');
    }

    public function privateAssessment()
    {
        return $this->belongsTo(PrivateAssessment::class, 'PrivateDocumentID');
    }

    // In the Feedback database; Eloquent loads it there in a query of its own.
    public function resourceRequest()
    {
        return $this->belongsTo(ResourceRequestForm::class, 'ResourceRequestFormID', 'FormID');
    }

    // The form this follow-up is about, whichever kind it is.
    public function form(): LawEnforcementAssessment | PrivateAssessment | ResourceRequestForm | null
    {
        return match ($this->Kind) {
            FollowUpKind::LawEnforcementAssessment => $this->lawEnforcementAssessment,
            FollowUpKind::CivilianAssessment => $this->privateAssessment,
            FollowUpKind::ResourceRequest => $this->resourceRequest,
        };
    }

    // Who the form is about, as the queue shows it: the victim for an
    // assessment, the requester for a resource request.
    public function formName(): ?string
    {
        $form = $this->form();

        if ($form === null) {
            return null;
        }

        $name = $form instanceof ResourceRequestForm
            ? "{$form->FirstName} {$form->LastName}"
            : "{$form->VictimFirstName} {$form->VictimLastName}";

        return trim($name) ?: null;
    }

    public function entries()
    {
        return $this->hasMany(FollowUpEntry::class, 'FollowUpID')->orderBy('EnteredAt')->orderBy('EntryID');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'LastUpdatedBy');
    }

    // The follow-ups a user may see and work: the kinds whose workers include
    // the user's role (FollowUpKind::workedBy()) - admins all three, secretaries
    // resource requests only. Officers and police admins: none, ever.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        $kinds = self::kindsFor($user);

        return $kinds === []
            ? $query->whereRaw('1 = 0')
            : $query->whereIn('Kind', array_map(fn (FollowUpKind $kind): string => $kind->value, $kinds));
    }

    /** @return list<FollowUpKind> */
    public static function kindsFor(?User $user): array
    {
        return array_values(array_filter(
            FollowUpKind::cases(),
            fn (FollowUpKind $kind): bool => (bool) $user?->hasActiveRole(...$kind->workedBy()),
        ));
    }

    public function isWorkableBy(?User $user): bool
    {
        return in_array($this->Kind, self::kindsFor($user), true);
    }
}
