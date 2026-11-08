<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'usr_users';
    protected $primaryKey = 'user_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['password_hash'];
    protected $casts = [
        'is_active' => 'boolean',
        'is_email_verified' => 'boolean',
        'locked_until' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    // The schema stores the bcrypt hash in password_hash and has no remember_token column.
    public function getAuthPassword() { return $this->password_hash; }
    public function getAuthPasswordName() { return 'password_hash'; }
    public function getRememberTokenName() { return ''; }

    public function role() { return $this->belongsTo(Role::class, 'role_id'); }
    public function dentist() { return $this->hasOne(Dentist::class, 'user_id'); }
    public function patient() { return $this->hasOne(Patient::class, 'user_id'); }

    public function roleName(): string { return $this->role->role_name ?? ''; }
    public function hasRole(string ...$roles): bool { return in_array($this->roleName(), $roles, true); }
    public function getFullNameAttribute(): string { return trim($this->first_name.' '.$this->last_name); }
    public function getRoleLabelAttribute(): string
    {
        return ['admin' => 'Admin', 'dentist' => 'Dentist', 'clinic_staff' => 'Clinic Staff', 'patient' => 'Patient'][$this->roleName()] ?? $this->roleName();
    }
}
