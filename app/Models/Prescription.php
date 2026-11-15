<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Prescription extends Model
{
    protected $table = 'pat_prescriptions';
    protected $primaryKey = 'prescription_id';
    public $timestamps = false;
    protected $guarded = [];
}
