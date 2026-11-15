<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $table = 'apt_appointments';
    protected $primaryKey = 'appointment_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'start_at' => 'datetime', 'end_at' => 'datetime',
        'is_emergency' => 'boolean', 'needs_reschedule' => 'boolean',
    ];

    /** Statuses that occupy a dentist's time. 'rescheduled' = the superseded original, so it does not block. */
    public const BLOCKING = ['pending', 'confirmed', 'completed'];
    public const OPEN = ['pending', 'confirmed'];

    public function patient() { return $this->belongsTo(Patient::class, 'patient_id')->withTrashed(); }
    public function service() { return $this->belongsTo(Service::class, 'service_id'); }
    public function dentist() { return $this->belongsTo(Dentist::class, 'assigned_dentist_id'); }
    public function requestedDentist() { return $this->belongsTo(Dentist::class, 'requested_dentist_id'); }
    public function treatmentPlan() { return $this->belongsTo(TreatmentPlan::class, 'treatment_plan_id'); }
    public function emailLogs() { return $this->hasMany(EmailLog::class, 'related_appointment_id'); }

    public function latestRescheduleEmail(): ?EmailLog
    {
        return $this->emailLogs->sortByDesc('email_log_id')->first(function ($l) {
            $sub = strtolower($l->subject);
            return str_contains($sub, 'reschedul') || str_contains($sub, 'schedule update');
        });
    }

    public function hasSecondReminder(): bool
    {
        return $this->emailLogs->contains(function ($l) {
            return str_contains(strtolower($l->subject), 'second reminder');
        });
    }

    public function getPriorityOrderLabelAttribute(): string
    {
        if ($this->start_at && $this->start_at->isToday()) {
            return '🚨 Same Day (On the way)';
        }
        if ($this->is_emergency) {
            return '🔴 Emergency';
        }
        if ($this->treatment_plan_id || optional($this->service)->is_multi_visit) {
            return '🦷 Multi-Visit (Sequence)';
        }
        return '📅 Standard';
    }

    public function getPriorityBadgeColorAttribute(): string
    {
        if ($this->start_at && $this->start_at->isToday()) return 'danger';
        if ($this->is_emergency) return 'danger';
        if ($this->treatment_plan_id || optional($this->service)->is_multi_visit) return 'info';
        return 'secondary';
    }

    /** Patients see their own, dentists their own chair, staff/admin everything. */
    public function scopeVisibleTo($q, User $u)
    {
        if ($u->hasRole('patient')) return $q->where('patient_id', optional($u->patient)->patient_id ?? 0);
        if ($u->hasRole('dentist')) return $q->where('assigned_dentist_id', optional($u->dentist)->dentist_id ?? 0);
        return $q;
    }
    public function scopeOpen($q) { return $q->whereIn('status', self::OPEN); }
    public function isOpen(): bool { return in_array($this->status, self::OPEN, true); }

    public function getStatusLabelAttribute(): string
    {
        return ['pending' => 'Pending', 'confirmed' => 'Confirmed', 'rescheduled' => 'Rescheduled',
                'cancelled' => 'Cancelled', 'completed' => 'Completed', 'no_show' => 'No-show'][$this->status] ?? $this->status;
    }
    public function getStatusColorAttribute(): string
    {
        return ['pending' => 'warning', 'confirmed' => 'primary', 'rescheduled' => 'secondary',
                'cancelled' => 'danger', 'completed' => 'success', 'no_show' => 'dark'][$this->status] ?? 'secondary';
    }
}
