<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dentist extends Model
{
    protected $table = 'usr_dentists';
    protected $primaryKey = 'dentist_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['is_accepting_bookings' => 'boolean'];

    public function user() { return $this->belongsTo(User::class, 'user_id'); }
    public function specialties() { return $this->belongsToMany(Specialty::class, 'usr_dentist_specialties', 'dentist_id', 'specialty_id'); }
    public function schedules() { return $this->hasMany(DentistSchedule::class, 'dentist_id'); }
    public function timeOffs() { return $this->hasMany(TimeOff::class, 'dentist_id'); }

    public function getNameAttribute(): string { return 'Dr. '.$this->user->full_name; }
    public function getInitialsAttribute(): string
    {
        return strtoupper(mb_substr($this->user->first_name, 0, 1).mb_substr($this->user->last_name, 0, 1));
    }
    public function getSpecialtyLabelAttribute(): string
    {
        return $this->specialties->pluck('specialty_name')->join(', ') ?: 'General Dentistry';
    }
}
