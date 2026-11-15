<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitRecord extends Model
{
    protected $table = 'pat_visit_records';
    protected $primaryKey = 'visit_record_id';
    protected $guarded = [];
    protected $casts = ['visit_date' => 'date:Y-m-d', 'last_xray_date' => 'date:Y-m-d'];
    public function patient() { return $this->belongsTo(Patient::class, 'patient_id')->withTrashed(); }
    public function appointment() { return $this->belongsTo(Appointment::class, 'appointment_id'); }
    public function dentist() { return $this->belongsTo(Dentist::class, 'dentist_id'); }
    public function prescriptions() { return $this->hasMany(Prescription::class, 'visit_record_id'); }
    public function labRequests() { return $this->hasMany(LabRequest::class, 'visit_record_id'); }
}
