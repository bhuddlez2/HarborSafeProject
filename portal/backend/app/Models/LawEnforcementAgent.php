<?php

namespace App\Models;

class LawEnforcementAgent extends BaseModel
{
    protected $connection = 'Portal';

    protected $table = 'law_enforcement_agents';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'badge_number',
        'agency_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }
}
