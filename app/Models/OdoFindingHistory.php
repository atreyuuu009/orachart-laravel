<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdoFindingHistory extends Model
{
    protected $table = 'odo_finding_history';
    protected $primaryKey = 'finding_history_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['old_value' => 'array', 'new_value' => 'array', 'performed_at' => 'datetime'];
    public function dentist() { return $this->belongsTo(Dentist::class, 'performed_by_dentist_id'); }
}
