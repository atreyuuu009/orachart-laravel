<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HealthProfile extends Model
{
    protected $table = 'pat_health_profiles';
    protected $primaryKey = 'health_profile_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
}
