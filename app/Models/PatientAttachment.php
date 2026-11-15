<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PatientAttachment extends Model
{
    protected $table = 'pat_attachments';
    protected $primaryKey = 'attachment_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['created_at' => 'datetime', 'deleted_at' => 'datetime'];
    public function visitRecord() { return $this->belongsTo(VisitRecord::class, 'visit_record_id'); }
}
