<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    protected $table = 'pay_receipts';
    protected $primaryKey = 'receipt_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['issued_at' => 'datetime'];
}
