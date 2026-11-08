<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeOff extends Model
{
    protected $table = 'sch_time_offs';
    protected $primaryKey = 'time_off_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];
    public function dentist() { return $this->belongsTo(Dentist::class, 'dentist_id'); }
}
