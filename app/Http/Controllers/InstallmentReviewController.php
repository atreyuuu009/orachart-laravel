<?php
namespace App\Http\Controllers;
use App\Models\{Discount,InstallmentPayment,InstallmentPlan};use App\Services\InstallmentService;use Illuminate\Http\Request;use Illuminate\Support\Facades\Storage;
class InstallmentReviewController extends Controller
{
 public function index(){ $u=request()->user(); return view('installments.index',['requests'=>$u->hasRole('admin','dentist')?InstallmentPlan::with('patient','service')->where('status','requested')->get():collect(),'proofs'=>$u->hasRole('admin','clinic_staff')?InstallmentPayment::with('plan.patient','plan.service')->where('status','proof_submitted')->get():collect()]); }
 public function show(InstallmentPlan $plan){$plan->load('patient','service','payments');return view('installments.show',['plan'=>$plan,'discounts'=>Discount::where('is_active',1)->get()]);}
 public function approve(Request $r,InstallmentPlan $plan,InstallmentService $s){$v=$r->validate(['total_amount'=>'required|numeric|min:0.01','discount_id'=>'nullable|exists:pay_discounts,discount_id','interest_rate_percent'=>'required|numeric|min:0|max:100','number_of_installments'=>'required|integer|min:2|max:'.\App\Support\Settings::int('installment_max_months'),'first_due_date'=>'required|date|after_or_equal:today','terms_note'=>'nullable|string|max:1000']);$s->approve($plan,$v,$r->user()->user_id);return back()->with('status','Plan terms saved and schedule created.');}
 public function reject(Request $r,InstallmentPlan $plan,InstallmentService $s){$v=$r->validate(['reason'=>'required|string|max:500']);$s->reject($plan,$v['reason'],$r->user()->user_id);return back()->with('status','Request rejected.');}
 public function verify(InstallmentPayment $row,InstallmentService $s){$s->verify($row,request()->user()->user_id);return back()->with('status','Proof verified and recorded as a payment.');}
 public function rejectProof(Request $r,InstallmentPayment $row,InstallmentService $s){$v=$r->validate(['reason'=>'required|string|max:500']);$s->rejectProof($row,$v['reason']);return back()->with('status','Proof rejected.');}
 public function proof(InstallmentPayment $row){abort_unless($row->proof_file_path,404);$path=Storage::disk('local')->path($row->proof_file_path);abort_unless(is_file($path),404);$mime=(new \finfo(FILEINFO_MIME_TYPE))->file($path);abort_unless(in_array($mime,['image/jpeg','image/png','image/webp','application/pdf'],true),404);return response()->file($path,['Content-Type'=>$mime,'Content-Disposition'=>'inline; filename="'.basename($path).'"','X-Content-Type-Options'=>'nosniff']);}
}
