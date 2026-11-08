<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreatmentPlan extends Model
{
    protected $table = 'apt_treatment_plans';
    protected $primaryKey = 'treatment_plan_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    public function service() { return $this->belongsTo(Service::class, 'service_id'); }
    public function appointments() { return $this->hasMany(Appointment::class, 'treatment_plan_id'); }
}
