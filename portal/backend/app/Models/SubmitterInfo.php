<?php

namespace App\Models;

use App\Models\Concerns\EncryptsSensitiveFields;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubmitterInfo extends BaseModel
{
    //Sets up UUID input for new records
    use EncryptsSensitiveFields, HasUuids;

    // The civilian submitter's contact details, encrypted under
    // DATA_ENCRYPTION_KEY (App\Support\SensitiveData).
    public const ENCRYPTED = [
        'SubmitterEmail',
        'SubmitterPhoneNumber',
        'SubmitterFirstName',
        'SubmitterLastName',
    ];

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = '_submitter_info';

    //Primary Key
    protected $primaryKey = 'SubmissionID';

    //UUID specifications
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;


    //Fillable Columns
    protected $fillable = [
        'SubmitterEmail',
        'SubmitterPhoneNumber',
        'SubmitterFirstName',
        'SubmitterLastName'
    ];

    // Stored as (xxx)xxx-xxxx whichever form it arrives in - see
    // App\Support\PhoneNumber, which also validates it.
    protected function phoneNumberFields(): array
    {
        return ['SubmitterPhoneNumber'];
    }

    public function uniqueIds(): array
    {
        return ['SubmissionID'];
    }
}

