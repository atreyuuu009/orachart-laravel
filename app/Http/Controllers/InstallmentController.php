<?php
namespace App\Http\Controllers;
use App\Models\{InstallmentPayment,InstallmentPlan,Service};
use App\Services\InstallmentService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class InstallmentController extends Controller
{
    private function patient(Request $r){return $r->user()->patient()->firstOrFail();}
    private function owned(Request $r,InstallmentPlan $p):void
    {
        $patientId = $r->user()->patient()->value('patient_id');
        $belongsToUser = $patientId !== null && (int) $p->patient_id === (int) $patientId;
        if (! $belongsToUser) {
            Log::warning('Installment action denied by ownership check', [
                'user_id' => $r->user()->user_id,
                'plan_id' => $p->getKey(),
                'plan_patient_id' => $p->patient_id,
                'user_patient_id' => $patientId,
                'route' => $r->route()?->getName(),
            ]);
        }
        abort_unless($belongsToUser, 403);
    }
    public function mine(Request $r){$plans=InstallmentPlan::with('service','payments')->where('patient_id',$this->patient($r)->patient_id)->latest('created_at')->get();return view('installments.mine',compact('plans'));}
    public function create(InstallmentService $s){$services=$s->eligibleServices()->get();return view('installments.create',['services'=>$services,'maxMonths'=>Settings::int('installment_max_months')]);}
    public function store(Request $r,InstallmentService $s){$v=$r->validate(['service_id'=>'required|exists:svc_services,service_id','months'=>'required|integer|min:2|max:'.Settings::int('installment_max_months')]);$service=Service::findOrFail($v['service_id']);$s->request($this->patient($r),$service,$v['months'],$r->user()->user_id);return redirect()->route('installments.mine')->with('status','Installment request sent for review.');}
    public function accept(Request $r,InstallmentPlan $plan,InstallmentService $s){$this->owned($r,$plan);abort_unless($plan->status==='approved',404);$s->accept($plan,$r->user()->user_id);return back()->with('status','Plan accepted.');}
    public function decline(Request $r,InstallmentPlan $plan,InstallmentService $s){$this->owned($r,$plan);abort_unless($plan->status==='approved',404);$s->decline($plan);return back()->with('status','Plan declined.');}
    public function pay(Request $r,InstallmentPayment $row,InstallmentService $s){$p=$row->plan;$this->owned($r,$p);abort_unless($p->status==='active'&&in_array($row->status,['unpaid','overdue','rejected']),404);$v=$r->validate(['proof'=>'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120','reference_number'=>'nullable|string|max:100']);$f=$v['proof'];$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f->getRealPath());abort_unless(in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true),422,'Unsupported proof file.');$s->submitProof($row,$f,$v['reference_number']??null);return back()->with('status','Payment proof uploaded.');}
    public function proof(Request $r,InstallmentPayment $row)
    {
        $this->owned($r,$row->plan);
        abort_unless($row->proof_file_path,404);
        $path=Storage::disk('local')->path($row->proof_file_path);
        abort_unless(is_file($path),404);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true),404);
        return response()->file($path,['Content-Type'=>$mime,'Content-Disposition'=>'inline; filename="'.basename($path).'"','X-Content-Type-Options'=>'nosniff']);
    }
}
