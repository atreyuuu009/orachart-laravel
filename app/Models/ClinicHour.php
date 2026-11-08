<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClinicHour extends Model
{
    protected $table = 'sch_clinic_hours';
    protected $primaryKey = 'clinic_hour_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
}
