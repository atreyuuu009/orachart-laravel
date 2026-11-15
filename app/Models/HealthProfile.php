<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthProfile extends Model
{
    protected $table = 'pat_health_profiles';
    protected $primaryKey = 'health_profile_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['structured_checklist' => 'array', 'last_reviewed_at' => 'datetime'];
    public function updatedBy() { return $this->belongsTo(User::class, 'updated_by_user_id'); }
}
