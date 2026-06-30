<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentAnswers extends BaseModel
{
    //Sets up UUID input for new records
    use HasUuids;
    

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = '_assessment_answers';

    //Primary Key
    protected $primaryKey = 'AssessmentDocID';

    //UUID specifications
    protected $primaryKey = 'AssessmentDocID';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;


    //Fillable Columns
    protected $fillable = [
        'RiskIndicator1',
        'RiskIndicator2',
        'RiskIndicator3',
        'RiskIndicator4',
        'RiskIndicator5',
        'RiskIndicator6',
        'RiskIndicator7',
        'RiskIndicator8',
        'RiskIndicator9',
        'RiskIndicator10',
        'RiskIndicator11'
    ];

    public function uniqueIds(): array
    {
        return [$this->primaryKey];
    }
}