<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotLock extends Model
{
    protected $table = 'apt_slot_locks';
    protected $primaryKey = 'slot_lock_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime', 'expires_at' => 'datetime'];
}
