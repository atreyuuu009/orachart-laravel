<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmergencyBlock extends Model
{
    protected $table = 'sch_emergency_blocks';
    protected $primaryKey = 'emergency_block_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean'];
}
