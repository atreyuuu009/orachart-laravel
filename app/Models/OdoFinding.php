<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdoFinding extends Model
{
    protected $table = 'odo_findings';
    protected $primaryKey = 'finding_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    public function condition() { return $this->belongsTo(OdoConditionCode::class, 'condition_code_id'); }
    public function dentist() { return $this->belongsTo(Dentist::class, 'recorded_by_dentist_id'); }
}
