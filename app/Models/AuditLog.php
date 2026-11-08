<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $table = 'sys_audit_logs';
    protected $primaryKey = 'audit_log_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['old_value' => 'array', 'new_value' => 'array'];
    public function user() { return $this->belongsTo(User::class, 'user_id'); }
}
