<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SchSetting extends Model
{
    protected $table = 'sch_settings';
    protected $primaryKey = 'setting_key';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    public $incrementing = false;
    protected $keyType = 'string';
}
