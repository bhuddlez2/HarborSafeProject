<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class AssessmentAnswers extends Model
{
    //Sets up UUID input for new records
    use HasUuids;

    //DB Connection
    protected $connection = 'Portal';

    //Mapped Table
    protected $table = 'assessment_answers';

    //UUID specifications
    protected $keyType = 'string';
    public $incrementing = false;

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
}