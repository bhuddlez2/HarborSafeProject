<?php

namespace App\Models;

use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class SubmitterInfo extends BaseModel
{
    //Sets up UUID input for new records
    use HasUuids;

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
    protected function submitterPhoneNumber(): Attribute
    {
        return Attribute::set(fn (?string $value): ?string => PhoneNumber::format($value));
    }

    public function uniqueIds(): array
    {
        return ['SubmissionID'];
    }
}

