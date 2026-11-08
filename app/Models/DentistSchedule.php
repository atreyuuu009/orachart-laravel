<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DentistSchedule extends Model
{
    protected $table = 'sch_dentist_schedules';
    protected $primaryKey = 'schedule_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
