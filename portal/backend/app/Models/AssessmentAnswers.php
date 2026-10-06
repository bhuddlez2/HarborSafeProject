<?php

namespace App\Models;

use App\Models\Concerns\EditedOnlyThroughAssessmentEditor;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentAnswers extends BaseModel
{
    //Sets up UUID input for new records. Changes only through
    //App\Services\AssessmentEditor, so every answer change is logged;
    //civilian answers have no edit path at all.
    use EditedOnlyThroughAssessmentEditor, HasUuids;
    

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = '_assessment_answers';

    //Primary Key
    protected $primaryKey = 'AssessmentDocID';

    //UUID specifications
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