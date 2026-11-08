<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model
{
    protected $table = 'ntf_email_log';
    protected $primaryKey = 'email_log_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['scheduled_for' => 'datetime', 'sent_at' => 'datetime'];
}
