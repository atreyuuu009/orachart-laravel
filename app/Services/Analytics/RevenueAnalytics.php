<?php
namespace App\Services\Analytics;

use Illuminate\Support\Facades\DB;

class RevenueAnalytics
{
    public function data(array $r): array
    {
        $f=$r['filters']; $base=DB::table('pay_payments as p')->leftJoin('apt_appointments as a','a.appointment_id','=','p.appointment_id')->where('p.status','verified')->whereBetween('p.payment_date',[$r['start']->toDateString(),$r['end']->toDateString()]);
        if(!empty($f['dentist']))$base->where('a.assigned_dentist_id',$f['dentist']); if(!empty($f['category']))$base->whereExists(fn($q)=>$q->selectRaw('1')->from('pay_payment_items as pi')->join('svc_services as s','s.service_id','=','pi.service_id')->whereColumn('pi.payment_id','p.payment_id')->where('s.category_id',$f['category'])); if(!empty($f['method']))$base->where('p.payment_method',$f['method']);
        $sum=(clone $base)->selectRaw('COALESCE(SUM(p.amount_paid),0) collected,COALESCE(SUM(p.total_amount),0) billed,COALESCE(SUM(p.balance_amount),0) outstanding,COALESCE(SUM(p.discount_amount),0) discounts,COALESCE(SUM(p.subtotal_amount),0) subtotal,COUNT(*) n')->first();
        $daily=(clone $base)->selectRaw('p.payment_date day,SUM(p.amount_paid) amount')->groupBy('p.payment_date')->orderBy('p.payment_date')->get();
        $services=DB::table('pay_payment_items as i')->join('pay_payments as p','p.payment_id','=','i.payment_id')->join('svc_services as s','s.service_id','=','i.service_id')->where('p.status','verified')->whereBetween('p.payment_date',[$r['start']->toDateString(),$r['end']->toDateString()])->selectRaw('s.service_name label,SUM(i.line_total_amount) amount,SUM(i.quantity) quantity')->groupBy('s.service_id','s.service_name')->orderByDesc('amount')->limit(10)->get();
        $patients=DB::table('apt_appointments')->where('status','completed')->whereBetween(DB::raw('DATE(start_at)'),[$r['start']->toDateString(),$r['end']->toDateString()])->distinct()->count('patient_id');
        $cap=(int)(DB::table('sch_settings')->where('setting_key','max_patients_per_day')->value('setting_value')??17);
        return ['kpis'=>['collected'=>(float)$sum->collected,'billed'=>(float)$sum->billed,'collection_rate'=>$sum->billed?(float)$sum->collected/$sum->billed*100:null,'outstanding'=>(float)$sum->outstanding,'average'=>$sum->n?(float)$sum->collected/$sum->n:0,'discounts'=>(float)$sum->discounts,'discount_rate'=>$sum->subtotal?(float)$sum->discounts/$sum->subtotal*100:null,'patients'=>$patients,'cap'=>$cap], 'trend'=>$daily,'services'=>$services,'attention'=>['pending'=>DB::table('pay_payments')->where('status','pending_verification')->count(),'proofs'=>DB::table('pay_installment_payments')->where('status','proof_submitted')->count(),'overdue'=>DB::table('pay_installment_payments')->whereIn('status',['unpaid','overdue'])->whereDate('due_date','<',today())->sum(DB::raw('amount_due - amount_paid')),'unbilled'=>DB::table('apt_appointments')->where('status','completed')->whereNotExists(fn($q)=>$q->selectRaw('1')->from('pay_payments')->whereColumn('pay_payments.appointment_id','apt_appointments.appointment_id'))->count()], 'categories'=>DB::table('pay_payment_items as i')->join('pay_payments as p','p.payment_id','=','i.payment_id')->join('svc_services as s','s.service_id','=','i.service_id')->join('svc_categories as c','c.category_id','=','s.category_id')->where('p.status','verified')->whereBetween('p.payment_date',[$r['start']->toDateString(),$r['end']->toDateString()])->groupBy('c.category_name')->selectRaw('c.category_name label,SUM(i.line_total_amount) amount')->get()];
    }
}
