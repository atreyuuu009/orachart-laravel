<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdoTooth extends Model
{
    protected $table = 'odo_teeth';
    protected $primaryKey = 'tooth_number';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    public $incrementing = false;
}
