<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\EditedOnlyThroughAssessmentEditor;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class LawEnforcementAssessment extends BaseModel
{
    // Changes only through App\Services\AssessmentEditor, so every edit is logged.
    use EditedOnlyThroughAssessmentEditor, HasUuids;

    protected $connection = 'Portal';

    protected $table = 'law_enforcement_assessment';

    protected $primaryKey = 'DocumentID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'submitted_by',
        'OffenderFirstName',
        'OffenderLastName',
        'OffenderSex',
        'OffenderDOB',
        'OffenderVictimRelationship',
        'OffenderVictimRelationshipOther',
        'VictimFirstName',
        'VictimLastName',
        'VictimSex',
        'VictimDOB',
        'VictimSafePhoneNumber',
        'AssessmentDocID',
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    public function submitter()
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function assessmentAnswers()
    {
        return $this->belongsTo(AssessmentAnswers::class, 'AssessmentDocID');
    }

    // Every save since submission, newest first. See App\Services\AssessmentEditor.
    public function edits()
    {
        return $this->hasMany(AssessmentEdit::class, 'DocumentID')->latest('EditedAt');
    }

    // The one rule for who may see which law-enforcement assessments
    // (Filament_CMS_Design.md section 5, rule 5). Both staff screens scope
    // their queries with it and LawEnforcementAssessmentPolicy::view() asks
    // it, so a list and a single-record check can never disagree.
    //
    // Admins see every submission; police admins only those whose submitting
    // officer is currently in their own agency; officers only their own.
    // Everyone else - no user, inactive, secretary, unknown role, or a police
    // admin with no agency - matches nothing. Never widen the police admin
    // branch to every agency: it was narrowed on purpose.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        if ($user?->hasActiveRole(UserRole::Admin)) {
            return $query;
        }

        if ($user?->hasActiveRole(UserRole::PoliceAdmin)) {
            $agencyId = $user->agencyId();

            if ($agencyId === null) {
                return $query->whereRaw('1 = 0');
            }

            return $query->whereHas(
                'submitter.lawEnforcementAgent',
                fn (Builder $agent): Builder => $agent->where('agency_id', $agencyId),
            );
        }

        if ($user?->hasActiveRole(UserRole::LawEnforcement)) {
            return $query->where('submitted_by', $user->getKey());
        }

        return $query->whereRaw('1 = 0');
    }
}
