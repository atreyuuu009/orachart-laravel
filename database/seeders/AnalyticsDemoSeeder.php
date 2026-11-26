<?php
namespace Database\Seeders;
use App\Models\Patient;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Small, repeatable synthetic cohort for local analytics previews. */
class AnalyticsDemoSeeder extends Seeder
{
 public function run(): void
 {
  $services=DB::table('svc_services')->where('is_active',1)->where('is_addon',0)->get(); $dentists=DB::table('usr_dentists')->pluck('dentist_id'); $staff=DB::table('usr_users')->whereIn('role_id',[1,3])->value('user_id');
  if($services->isEmpty()||$dentists->isEmpty()||!$staff){$this->command?->warn('Analytics demo rows skipped: service, dentist, or staff seed data missing.');return;}
  $discount=DB::table('pay_discounts')->where('is_active',1)->first();
  for($i=1;$i<=120;$i++){
   $email="demo+{$i}@demo.orachart.test"; $p=Patient::where('email',$email)->first();
   if(!$p){$p=Patient::create(['patient_code'=>Patient::nextCode(),'first_name'=>'Demo','last_name'=>'Patient '.$i,'birth_date'=>now()->subYears(3+($i*7%78))->toDateString(),'sex'=>$i%12===0?null:($i%2?'female':'male'),'contact_number'=>sprintf('0900%07d',$i),'email'=>$email,'address'=>['Fairview, Quezon City','Novaliches, Quezon City','Commonwealth, Quezon City','Manila',''][($i-1)%5],'user_id'=>null]);}
   if(!DB::table('pat_health_profiles')->where('patient_id',$p->patient_id)->exists())DB::table('pat_health_profiles')->insert(['patient_id'=>$p->patient_id,'blood_type'=>['A+','B+','O+','unknown'][$i%4],'allergies'=>$i%3?'':'','medical_conditions'=>$i%4?'':'','current_medications'=>$i%5?'':'','last_reviewed_at'=>$i%2?now()->subMonths(4):null]);
   if($i>45)continue; $service=$services[($i-1)%$services->count()]; $date=now()->subDays($i*3)->setTime(9+($i%8),0);
   $appointment=DB::table('apt_appointments')->where('patient_id',$p->patient_id)->whereDate('start_at',$date)->first();
   if(!$appointment){$id=DB::table('apt_appointments')->insertGetId(['patient_id'=>$p->patient_id,'service_id'=>$service->service_id,'assigned_dentist_id'=>$dentists[($i-1)%$dentists->count()],'start_at'=>$date,'end_at'=>$date->copy()->addMinutes($service->duration_minutes),'status'=>'completed','is_emergency'=>$i%19===0,'booked_by_user_id'=>$staff]);$appointment=(object)['appointment_id'=>$id];}
   if(DB::table('pay_payments')->where('appointment_id',$appointment->appointment_id)->exists())continue;
   $amount=(float)$service->price_min_amount; $disc=$discount&&$i%10===0?round($amount*(float)$discount->discount_percent/100,2):0; $total=max(0,$amount-$disc); $paid=$i%10===1?round($total/2,2):$total;
   $paymentId=DB::table('pay_payments')->insertGetId(['patient_id'=>$p->patient_id,'appointment_id'=>$appointment->appointment_id,'subtotal_amount'=>$amount,'discount_id'=>$disc?$discount->discount_id:null,'discount_amount'=>$disc,'total_amount'=>$total,'amount_paid'=>$paid,'balance_amount'=>$total-$paid,'payment_method'=>['cash','gcash','bank_transfer','card','other'][$i%5],'payment_date'=>$date->toDateString(),'status'=>$i%20===0?'pending_verification':'verified','recorded_by_user_id'=>$staff]);
   DB::table('pay_payment_items')->insert(['payment_id'=>$paymentId,'service_id'=>$service->service_id,'quantity'=>1,'unit_price_amount'=>$amount,'line_total_amount'=>$amount]);
  }
  $this->command?->info('Analytics demo rows created/checked (synthetic demo+N@demo.orachart.test patients).');
 }
}
