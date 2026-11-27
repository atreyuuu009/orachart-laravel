<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InstallmentPayment extends Model {
    protected $table='pay_installment_payments'; protected $primaryKey='installment_payment_id'; public $timestamps=false; protected $guarded=[];
    protected $casts=['due_date'=>'date:Y-m-d','amount_due'=>'float','amount_paid'=>'float','late_fee_amount'=>'float','late_fee_applied_at'=>'datetime','proof_uploaded_at'=>'datetime','verified_at'=>'datetime'];
    public function plan(){return $this->belongsTo(InstallmentPlan::class,'installment_plan_id');}
}
