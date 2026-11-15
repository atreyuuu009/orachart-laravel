<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'pay_payments';
    protected $primaryKey = 'payment_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['payment_date' => 'date:Y-m-d', 'verified_at' => 'datetime'];

    public function patient() { return $this->belongsTo(Patient::class, 'patient_id')->withTrashed(); }
    public function appointment() { return $this->belongsTo(Appointment::class, 'appointment_id'); }
    public function discount() { return $this->belongsTo(Discount::class, 'discount_id'); }
    public function items() { return $this->hasMany(PaymentItem::class, 'payment_id'); }
    public function receipt() { return $this->hasOne(Receipt::class, 'payment_id'); }
    public function recorder() { return $this->belongsTo(User::class, 'recorded_by_user_id'); }
}
