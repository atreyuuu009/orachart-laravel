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

    /** Next auto-index code, e.g. 00001-2026-GS. Call inside a transaction. */
    public static function nextCode(): string
    {
        $year = now()->year;
        $codes = static::withTrashed()
            ->where(function ($query) use ($year) {
                $query->where('patient_code', 'like', "GS-$year-%")
                    ->orWhere('patient_code', 'like', "%-$year-GS");
            })
            ->lockForUpdate()
            ->pluck('patient_code');

        $n = $codes->map(function (string $code) use ($year) {
            if (preg_match('/^GS-' . $year . '-(\d+)$/', $code, $matches)
                || preg_match('/^(\d+)-' . $year . '-GS$/', $code, $matches)) {
                return (int) $matches[1];
            }

            return 0;
        })->max() + 1;

        return sprintf('%05d-%d-GS', $n, $year);
    }
}
