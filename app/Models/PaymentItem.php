<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentItem extends Model
{
    protected $table = 'pay_payment_items';
    protected $primaryKey = 'payment_item_id';
    public $timestamps = false;
    protected $guarded = [];
    public function service() { return $this->belongsTo(Service::class, 'service_id'); }
}
