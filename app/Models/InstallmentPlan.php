<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class InstallmentPlan extends Model {
    protected $table='pay_installment_plans'; protected $primaryKey='installment_plan_id'; public $timestamps=false; protected $guarded=[];
    protected $casts=['total_amount'=>'float','discount_amount'=>'float','interest_amount'=>'float','interest_rate_percent'=>'float','net_amount'=>'float','reviewed_at'=>'datetime','accepted_at'=>'datetime','first_due_date'=>'date:Y-m-d'];
    public function patient(){return $this->belongsTo(Patient::class,'patient_id');} public function service(){return $this->belongsTo(Service::class,'service_id');}
    public function discount(){return $this->belongsTo(Discount::class,'discount_id');} public function payments(){return $this->hasMany(InstallmentPayment::class,'installment_plan_id')->orderBy('installment_number');}
}
