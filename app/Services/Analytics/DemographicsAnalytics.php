<?php
namespace App\Services\Analytics;
use Illuminate\Support\Facades\DB;
class DemographicsAnalytics
{
 public function data(array $r): array {
  $f=$r['filters']; $p=DB::table('pat_patients')->whereNull('deleted_at'); if(!empty($f['sex']))$p->where('sex',$f['sex']); if(($f['type']??'all')==='online')$p->whereNotNull('user_id'); if(($f['type']??'all')==='walkin')$p->whereNull('user_id');
  $patients=(clone $p)->count(); $new=(clone $p)->whereBetween('created_at',[$r['start']->startOfDay(),$r['end']->copy()->endOfDay()])->count();
  $age=DB::table('pat_patients')->whereNull('deleted_at')->selectRaw('TIMESTAMPDIFF(YEAR,birth_date,CURDATE()) age,sex')->get();
  // Collection::median() over the result objects returns an object for odd-sized cohorts.
  // Work with the numeric age values so both an empty cohort and one patient are safe.
  $ageValues=$age->pluck('age')->map(fn($value)=>(int)$value);
  $averageAge=$ageValues->isEmpty()?null:round((float)$ageValues->avg(),1);
  $medianAge=$ageValues->isEmpty()?null:round((float)$ageValues->median(),1);
  $appt=DB::table('apt_appointments as a')->join('pat_patients as p','p.patient_id','=','a.patient_id')->whereNull('p.deleted_at')->whereBetween(DB::raw('DATE(a.start_at)'),[$r['start']->toDateString(),$r['end']->toDateString()])->whereIn('a.status',['confirmed','completed']); if(!empty($f['sex']))$appt->where('p.sex',$f['sex']);
  return ['kpis'=>['total'=>$patients,'new'=>$new,'registered'=>(clone $p)->whereNotNull('user_id')->count(),'walkin'=>(clone $p)->whereNull('user_id')->count(),'active'=>DB::table('apt_appointments')->where('status','completed')->where('start_at','>=',now()->subMonths(12))->distinct()->count('patient_id'),'lapsed'=>0,'repeat'=>0,'age_average'=>$averageAge,'age_median'=>$medianAge],'sex'=>DB::table('pat_patients')->whereNull('deleted_at')->selectRaw("COALESCE(sex,'Not specified') label,COUNT(*) value")->groupBy('sex')->get(),'age_groups'=>$age->groupBy(fn($x)=>match(true){$x->age<=12=>'0–12',$x->age<=17=>'13–17',$x->age<=24=>'18–24',$x->age<=34=>'25–34',$x->age<=44=>'35–44',$x->age<=54=>'45–54',$x->age<=64=>'55–64',default=>'65+'})->map(fn($g,$k)=>['label'=>$k,'value'=>$g->count()])->values(),'new_months'=>DB::table('pat_patients')->whereNull('deleted_at')->where('created_at','>=',now()->subMonths(12))->selectRaw("DATE_FORMAT(created_at,'%Y-%m') month,SUM(user_id IS NOT NULL) online,SUM(user_id IS NULL) walkin")->groupBy('month')->orderBy('month')->get(),'blood'=>DB::table('pat_health_profiles')->selectRaw("COALESCE(blood_type,'unknown') label,COUNT(*) value")->groupBy('blood_type')->get(),'services'=> (clone $appt)->join('svc_services as s','s.service_id','=','a.service_id')->join('svc_categories as c','c.category_id','=','s.category_id')->selectRaw('c.category_name label,COUNT(*) value')->groupBy('c.category_name')->get()];
 }
}
