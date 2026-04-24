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
        'RiskIndicatior1',
        'RiskIndicatior2',
        'RiskIndicatior3',
        'RiskIndicatior4',
        'RiskIndicatior5',
        'RiskIndicatior6',
        'RiskIndicatior7',
        'RiskIndicatior8',
        'RiskIndicatior9',
        'RiskIndicatior10',
        'RiskIndicatior11'
    ];
}
