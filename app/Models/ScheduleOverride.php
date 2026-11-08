<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduleOverride extends Model
{
    protected $table = 'sch_overrides';
    protected $primaryKey = 'override_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['override_date' => 'date:Y-m-d', 'is_closed' => 'boolean'];
}
