<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdoConditionCode extends Model
{
    protected $table = 'odo_condition_codes';
    protected $primaryKey = 'condition_code_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
