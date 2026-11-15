<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use SoftDeletes;

    protected $table = 'pat_patients';
    protected $primaryKey = 'patient_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['birth_date' => 'date:Y-m-d', 'deleted_at' => 'datetime'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function healthProfile() { return $this->hasOne(HealthProfile::class, 'patient_id'); }
    public function consents() { return $this->hasMany(Consent::class, 'patient_id'); }
    public function appointments() { return $this->hasMany(Appointment::class, 'patient_id'); }
    public function visitRecords() { return $this->hasMany(VisitRecord::class, 'patient_id')->orderByDesc('visit_date'); }
    public function attachments() { return $this->hasMany(PatientAttachment::class, 'patient_id')->whereNull('deleted_at'); }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.($this->middle_name ? $this->middle_name.' ' : '').$this->last_name);
    }
    public function getAgeAttribute(): int { return Carbon::parse($this->birth_date)->age; }

    /** Next auto-index code, e.g. GS-2026-00001. Call inside a transaction. */
    public static function nextCode(): string
    {
        $year = now()->year;
        $last = static::withTrashed()->where('patient_code', 'like', "GS-$year-%")
            ->orderByDesc('patient_code')->lockForUpdate()->value('patient_code');
        $n = $last ? ((int) substr($last, -5)) + 1 : 1;
        return sprintf('GS-%d-%05d', $year, $n);
    }
}
