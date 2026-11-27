<?php
namespace App\Services;

use App\Models\{EmailLog, InstallmentPayment, InstallmentPlan, Payment, PaymentItem, Patient, Service};
use App\Support\Audit;
use App\Support\Settings;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class InstallmentService
{
    public function eligibleServices()
    {
        return Service::bookable()
            ->where('price_min_amount', '>', 0)
            ->orderBy('service_name');
    }

    public function createPlan(Patient $patient, Service $service, array $terms, int $userId): InstallmentPlan
    {
        $minimum = max(10000, Settings::int('installment_min_amount'));
        $total = round((float) $terms['total_amount'], 2);
        $percent = round((float) $terms['downpayment_percent'], 2);
        $months = (int) $terms['number_of_installments'];

        if (! $patient->user_id || ! $patient->user()->where('is_email_verified', 1)->exists()) {
            throw ValidationException::withMessages(['patient_id' => 'The patient must have a verified email address before an installment plan can be created.']);
        }
        if (! $this->eligibleServices()->where('service_id', $service->service_id)->exists()) {
            throw ValidationException::withMessages(['service_id' => 'Only eligible treatments costing at least ₱'.number_format($minimum, 2).' can have installments.']);
        }
        if ($total < $minimum) throw ValidationException::withMessages(['total_amount' => 'The installment treatment total must be at least ₱'.number_format($minimum, 2).'.']);
        if ($percent <= 0 || $percent >= 100) throw ValidationException::withMessages(['downpayment_percent' => 'Enter a clinic down payment between 0% and 100%.']);
        if ($months < 2 || $months > Settings::int('installment_max_months')) throw ValidationException::withMessages(['number_of_installments' => 'Choose a valid installment term.']);

        return DB::transaction(function () use ($patient, $service, $terms, $userId, $total, $percent, $months) {
            Patient::whereKey($patient->patient_id)->lockForUpdate()->firstOrFail();
            if (InstallmentPlan::where('patient_id',$patient->patient_id)->whereIn('status',['requested','approved','active'])->exists()) throw ValidationException::withMessages(['patient_id'=>'This patient already has an open installment plan.']);

            $downpayment = round($total * $percent / 100, 2);
            $remaining = round($total - $downpayment, 2);
            $downpaymentPayment = (new PaymentService)->create([
                'patient_id' => $patient->patient_id,
                'appointment_id' => null,
                'invoice_number' => null,
                'items' => [[
                    'service_id' => $service->service_id,
                    'quantity' => 1,
                    'unit_price_amount' => $downpayment,
                ]],
                'discount_type' => 'none',
                'amount_paid' => $downpayment,
                'payment_method' => 'cash',
                'payment_date' => today()->toDateString(),
            ], $userId);

            $first = Carbon::parse($terms['first_due_date']);
            $plan = InstallmentPlan::create([
                'patient_id' => $patient->patient_id,
                'service_id' => $service->service_id,
                'total_amount' => $total,
                'discount_amount' => 0,
                'interest_rate_percent' => 0,
                'interest_amount' => 0,
                'downpayment_percent' => $percent,
                'downpayment_amount' => $downpayment,
                'downpayment_payment_id' => $downpaymentPayment->payment_id,
                'net_amount' => $remaining,
                'number_of_installments' => $months,
                'first_due_date' => $first->toDateString(),
                'terms_note' => $terms['terms_note'] ?? null,
                'status' => 'active',
                'reviewed_by_user_id' => $userId,
                'reviewed_at' => now(),
                'accepted_at' => now(),
            ]);
            $monthly = round($remaining / $months, 2);
            for ($i = 1; $i <= $months; $i++) {
                $due = $i === $months ? round($remaining - $monthly * ($months - 1), 2) : $monthly;
                InstallmentPayment::create(['installment_plan_id' => $plan->installment_plan_id, 'installment_number' => $i, 'due_date' => $first->copy()->addMonthsNoOverflow($i - 1)->toDateString(), 'amount_due' => $due, 'status' => 'unpaid']);
            }
            Audit::log('create', 'pay_installment_plans', $plan->installment_plan_id, 'Clinic-created installment plan with in-clinic down payment', null, ['total' => $total, 'downpayment_percent' => $percent, 'downpayment_amount' => $downpayment, 'remaining' => $remaining, 'months' => $months], $userId);
            $this->notify($plan->fresh(), 'installment_approved', ['months' => $months, 'net_amount' => number_format($remaining, 2)]);
            return $plan;
        });
    }
    public function submitProof(InstallmentPayment $row,$file,?string $reference):void{$old=$row->proof_file_path;$path=$file->store('installment-proofs/'.$row->installment_plan_id);$row->update(['proof_file_path'=>$path,'proof_uploaded_at'=>now(),'reference_number'=>$reference,'status'=>'proof_submitted','rejection_reason'=>null]);if($old)Storage::disk('local')->delete($old);Audit::log('upload_proof','pay_installment_payments',$row->installment_payment_id,'Payment proof uploaded');}
    public function verify(InstallmentPayment $row,int $userId):void
    {
        DB::transaction(function()use($row,$userId){$r=InstallmentPayment::whereKey($row->installment_payment_id)->lockForUpdate()->firstOrFail();if($r->status!=='proof_submitted')throw ValidationException::withMessages(['proof'=>'Only submitted proofs can be verified.']);$p=InstallmentPlan::whereKey($r->installment_plan_id)->lockForUpdate()->firstOrFail()->load('patient','service');$amount=(float)$r->amount_due;
            $pay=Payment::create(['patient_id'=>$p->patient_id,'invoice_number'=>null,'subtotal_amount'=>$amount,'discount_amount'=>0,'total_amount'=>$amount,'amount_paid'=>$amount,'balance_amount'=>0,'payment_method'=>'gcash','payment_date'=>today(),'status'=>'verified','recorded_by_user_id'=>$userId,'verified_by_user_id'=>$userId,'verified_at'=>now()]);
            PaymentItem::create(['payment_id'=>$pay->payment_id,'service_id'=>$p->service_id,'quantity'=>1,'unit_price_amount'=>$amount,'line_total_amount'=>$amount]);$r->update(['status'=>'verified','amount_paid'=>$amount,'verified_by_user_id'=>$userId,'verified_at'=>now(),'payment_id'=>$pay->payment_id]);
            if(!InstallmentPayment::where('installment_plan_id',$p->installment_plan_id)->where('status','!=','verified')->exists())$p->update(['status'=>'completed']);
            Audit::log('verify','pay_installment_payments',$r->installment_payment_id,'GCash proof verified; reference: '.($r->reference_number?:'not provided'));$this->notify($p,'payment_verified',['amount'=>number_format($amount,2),'installment_number'=>$r->installment_number,'months'=>$p->number_of_installments]);
            (new PaymentService)->issueInstallmentReceipt($pay,$userId);
        });
    }
    public function rejectProof(InstallmentPayment $row,string $reason):void{DB::transaction(function()use($row,$reason){$r=InstallmentPayment::whereKey($row->installment_payment_id)->lockForUpdate()->firstOrFail();if($r->status!=='proof_submitted')throw ValidationException::withMessages(['proof'=>'Only submitted proofs can be rejected.']);$r->update(['status'=>'rejected','rejection_reason'=>$reason]);Audit::log('reject_proof','pay_installment_payments',$r->installment_payment_id,'Payment proof rejected: '.$reason);$this->notify($r->plan,'payment_rejected',['reason'=>$reason,'amount'=>number_format($r->amount_due,2),'installment_number'=>$r->installment_number]);});}
    public function markOverdue():void{InstallmentPayment::whereIn('status',['unpaid','overdue'])->whereDate('due_date','<',today()->subDays(2))->whereHas('plan',fn($q)=>$q->where('status','active'))->update(['status'=>'overdue']);}
    public function sendDueReminders():void
    {
        foreach(InstallmentPayment::with('plan.patient.user','plan.service')->whereIn('status',['unpaid','overdue','rejected'])->whereHas('plan',fn($q)=>$q->where('status','active'))->get() as $row){
            $days=today()->diffInDays($row->due_date,false);
            if(!in_array($days,[3,0,-3,-7],true))continue;
            $plan=$row->plan;
            if($days<=-7){
                if(!$row->late_fee_applied_at){$fee=$this->lateFee($row);$row->update(['amount_due'=>round($row->amount_due+$fee,2),'late_fee_amount'=>$fee,'late_fee_applied_at'=>now()]);$row->refresh();}
                $key='installment_overdue';
            }else{$key='installment_due';}
            $template=\App\Models\EmailTemplate::where('template_key',$key)->where('is_active',1)->first();
            if(!$template||EmailLog::where('template_id',$template->template_id)->where('recipient_user_id',$plan->patient->user_id)->whereDate('created_at',today())->exists())continue;
            $this->notify($plan,$key,['amount'=>number_format($row->amount_due,2),'late_fee'=>number_format($row->late_fee_amount,2),'due_date'=>$row->due_date->format('M j, Y'),'installment_number'=>$row->installment_number,'months'=>$plan->number_of_installments,'days_until_due'=>$days]);
        }
    }

    private function lateFee(InstallmentPayment $row): float
    {
        $value=(float)Settings::get('installment_late_fee_amount');
        if(Settings::get('installment_late_fee_type')==='percent')$value=(float)$row->amount_due*$value/100;
        return round(max(0,$value),2);
    }

    public function moveDueDate(InstallmentPayment $row,string $date,int $userId):void
    {
        if(!in_array($row->status,['unpaid','overdue','rejected'],true))throw ValidationException::withMessages(['installment'=>'Only unpaid installments can be rescheduled.']);
        $old=$row->due_date->toDateString();$row->update(['due_date'=>$date,'status'=>'unpaid','rejection_reason'=>null]);
        Audit::log('reschedule','pay_installment_payments',$row->installment_payment_id,"Installment due date moved from {$old} to {$date}",null,null,$userId);
        $this->notify($row->fresh()->plan, 'installment_schedule_changed', ['change_message' => 'Installment '.$row->installment_number.' is now due '.Carbon::parse($date)->format('M j, Y').'.']);
    }

    public function splitPayment(InstallmentPayment $row,string $secondDueDate,int $userId):void
    {
        DB::transaction(function()use($row,$secondDueDate,$userId){
            $plan=InstallmentPlan::whereKey($row->installment_plan_id)->lockForUpdate()->firstOrFail();
            $current=InstallmentPayment::whereKey($row->installment_payment_id)->lockForUpdate()->firstOrFail();
            if(!in_array($current->status,['unpaid','overdue','rejected'],true)||$current->amount_paid>0)throw ValidationException::withMessages(['installment'=>'Only an unpaid installment can be split.']);
            $half=round((float)$current->amount_due/2,2);$other=round((float)$current->amount_due-$half,2);
            InstallmentPayment::where('installment_plan_id',$plan->installment_plan_id)->where('installment_number','>',$current->installment_number)->orderByDesc('installment_number')->get()->each(fn($later)=>$later->update(['installment_number'=>$later->installment_number+1]));
            $current->update(['amount_due'=>$half,'status'=>'unpaid','rejection_reason'=>null]);
            InstallmentPayment::create(['installment_plan_id'=>$plan->installment_plan_id,'installment_number'=>$current->installment_number+1,'due_date'=>$secondDueDate,'amount_due'=>$other,'status'=>'unpaid']);
            $plan->update(['number_of_installments'=>$plan->number_of_installments+1]);
            Audit::log('split','pay_installment_payments',$current->installment_payment_id,'Installment split into two payments; second due '.$secondDueDate,null,null,$userId);
            $this->notify($plan->fresh(), 'installment_schedule_changed', ['change_message' => 'Installment '.$current->installment_number.' has been split into two payments. The second payment is due '.Carbon::parse($secondDueDate)->format('M j, Y').'.']);
        });
    }

    public function extendPlan(InstallmentPlan $plan,int $userId):void
    {
        $last=$plan->payments()->whereIn('status',['unpaid','overdue','rejected'])->orderByDesc('installment_number')->first();
        if(!$last)throw ValidationException::withMessages(['plan'=>'There is no unpaid installment to extend.']);
        $this->splitPayment($last,$last->due_date->copy()->addMonthNoOverflow()->toDateString(),$userId);
        Audit::log('extend','pay_installment_plans',$plan->installment_plan_id,'Plan extended by one monthly installment',null,null,$userId);
    }
    private function notify(InstallmentPlan $p,string $template,array $extra=[]):void{$p->loadMissing('patient.user','service');$patient=$p->patient;$amount=(float)($extra['amount']??$p->net_amount);$this->queue($template,$patient->email?:$patient->user?->email,['user_id'=>$patient->user_id,'patient_name'=>$patient->full_name,'first_name'=>$patient->first_name,'service'=>$p->service->service_name,'action_url'=>route('installments.mine'),'net_amount'=>number_format($p->net_amount,2),'amount'=>number_format($amount,2),'months'=>$p->number_of_installments,'amount_paid'=>number_format($amount,2),'total_amount'=>number_format($amount,2),'balance_amount'=>number_format($template==='payment_rejected'?$amount:0,2),'receipt_number'=>'See My Installments','payment_context'=>isset($extra['installment_number'])?'for installment '.$extra['installment_number'].' of '.$p->number_of_installments:'','reason'=>$p->rejection_reason??'']+$extra);}
    private function queue(string $key,?string $email,array $vars):void{if(!$email)return;$tpl=\App\Models\EmailTemplate::where('template_key',$key)->where('is_active',1)->first();if(!$tpl)return;$vars+=['clinic_name'=>Settings::get('clinic_name'),'app_url'=>url('/')];$merge=fn($s)=>preg_replace_callback('/\{(\w+)\}/',fn($m)=>e((string)($vars[$m[1]]??$m[0])),$s);$log=EmailLog::create(['template_id'=>$tpl->template_id,'recipient_user_id'=>$vars['user_id']??null,'recipient_email'=>$email,'subject'=>$tpl->subject,'body_html'=>view('emails.layout',['subject'=>$tpl->subject,'body'=>$merge($tpl->body_html)])->render(),'status'=>'queued']);Notifier::deliver($log);}
}
