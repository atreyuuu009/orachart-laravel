<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabRequest extends Model
{
    protected $table = 'pat_lab_requests';
    protected $primaryKey = 'lab_request_id';
    public $timestamps = false;
    protected $guarded = [];
}
