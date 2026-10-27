<?php

namespace App\Models;

use App\Enums\FollowUpKind;
use App\Http\Requests\Public\ResourceRequestStoreRequest;
use App\Models\Concerns\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ResourceRequestForm extends BaseModel
{
    use EncryptsSensitiveFields, HasUuids;

    protected $connection = 'Feedback';

    protected $table = 'resource_request_form';

    protected $primaryKey = 'FormID';

    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'FirstName',
        'LastName',
        'EmailAddress',
        'SafePhoneNumber',
        'CountyID',
        'Message',
    ];

    // Who is asking and what they wrote, encrypted under DATA_ENCRYPTION_KEY
    // (App\Support\SensitiveData). County, resource types and dates stay
    // plain. Searching these goes through App\Support\EncryptedSearch.
    public const ENCRYPTED = [
        'FirstName',
        'LastName',
        'EmailAddress',
        'SafePhoneNumber',
        'Message',
    ];

    // Stored as (xxx)xxx-xxxx whichever form it arrives in - see
    // App\Support\PhoneNumber, which also validates it.
    protected function phoneNumberFields(): array
    {
        return ['SafePhoneNumber'];
    }

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }

    // A request deleted from Submissions (spam) takes its follow-up and that
    // follow-up's history with it, so the Follow-ups queue never lists a form
    // that no longer exists. They live on Portal, without a foreign key that
    // could cascade; the history is append-only, so the query builder.
    protected static function booted(): void
    {
        // Keeps BaseModel's own check (a model without its connection throws).
        parent::booted();

        static::deleting(function (self $request): void {
            $portal = DB::connection('Portal');
            $followUpIds = $portal->table('follow_ups')->where('ResourceRequestFormID', $request->getKey())->pluck('FollowUpID');

            $portal->table('follow_up_entries')->whereIn('FollowUpID', $followUpIds)->delete();
            $portal->table('follow_ups')->whereIn('FollowUpID', $followUpIds)->delete();
        });
    }

    // Multi-select "resources of interest" - see resource_request_resource_types
    // (migration 2026_09_15_090100). Replaced the old singular ResourceTypeID FK.
    public function resourceTypes()
    {
        return $this->belongsToMany(
            Resource::class,
            'resource_request_resource_types',
            'FormID',
            'ResourceTypeID'
        );
    }

    public function county()
    {
        return $this->belongsTo(County::class, 'CountyID');
    }

    // Who may see resource requests: the people who work them (admins and
    // secretaries; FollowUpKind::workedBy()). The Submissions screen and the
    // PDF download route both go through this.
    public function scopeVisibleTo(Builder $query, ?User $user): Builder
    {
        return in_array(FollowUpKind::ResourceRequest, FollowUp::kindsFor($user), true)
            ? $query
            : $query->whereRaw('1 = 0');
    }

    // How a request arrived: from the website, or started by staff from an
    // earlier form (team plan task 16). Set only by recordStaffEntry(); none
    // of Source, EnteredBy or CreatedFromFollowUpID is fillable.
    public const SOURCE_ONLINE = 'online';

    public const SOURCE_STAFF = 'staff';

    public function isStaffEntry(): bool
    {
        return $this->Source === self::SOURCE_STAFF;
    }

    // users is on Portal, so a lookup rather than a relation that could join.
    public function enteredByName(): ?string
    {
        return $this->EnteredBy ? User::find($this->EnteredBy)?->name : null;
    }

    // The follow-up of the form this request was started from (on Portal).
    public function createdFrom(): ?FollowUp
    {
        return $this->CreatedFromFollowUpID ? FollowUp::find($this->CreatedFromFollowUpID) : null;
    }

    /*
    A request staff start from an earlier form: validated by the website's own
    rules (ResourceRequestStoreRequest::fieldRules(), plus its "email or phone"
    rule), saved with Source = staff, who entered it and which follow-up it
    came from, with its resources of interest, in one Feedback transaction.

    @throws ValidationException
    */
    public static function recordStaffEntry(array $data, User $enteredBy, FollowUp $from): self
    {
        $data = [
            'FirstName' => ResourceRequestStoreRequest::normalizeText($data['FirstName'] ?? null),
            'LastName' => ResourceRequestStoreRequest::normalizeText($data['LastName'] ?? null),
            'EmailAddress' => ResourceRequestStoreRequest::normalizeText($data['EmailAddress'] ?? null),
            'SafePhoneNumber' => ResourceRequestStoreRequest::normalizeText($data['SafePhoneNumber'] ?? null),
            'CountyID' => filled($data['CountyID'] ?? null) ? (int) $data['CountyID'] : null,
            'ResourceTypeIDs' => array_values(array_map('intval', $data['ResourceTypeIDs'] ?? [])),
            'Message' => ResourceRequestStoreRequest::normalizeText($data['Message'] ?? null),
        ];

        $validator = Validator::make($data, ResourceRequestStoreRequest::fieldRules());
        $validator->after(function ($validator) use ($data): void {
            if (blank($data['EmailAddress']) && blank($data['SafePhoneNumber'])) {
                $validator->errors()->add('EmailAddress', 'Give an email address or a safe phone number.');
            }
        });
        $validator->validate();

        return DB::connection('Feedback')->transaction(function () use ($data, $enteredBy, $from): self {
            $request = new self;
            $request->forceFill([
                'FirstName' => $data['FirstName'],
                'LastName' => $data['LastName'],
                'EmailAddress' => $data['EmailAddress'],
                'SafePhoneNumber' => $data['SafePhoneNumber'],
                'CountyID' => $data['CountyID'],
                'Message' => $data['Message'],
                'Source' => self::SOURCE_STAFF,
                'EnteredBy' => $enteredBy->getKey(),
                'CreatedFromFollowUpID' => $from->getKey(),
            ])->save();

            $request->resourceTypes()->sync($data['ResourceTypeIDs']);

            return $request;
        });
    }
}
