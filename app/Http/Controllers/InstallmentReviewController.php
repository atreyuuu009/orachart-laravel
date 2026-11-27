<?php

namespace App\Http\Controllers;

use App\Models\{InstallmentPayment, InstallmentPlan, Patient, Service};
use App\Services\InstallmentService;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class InstallmentReviewController extends Controller
{
    public function index()
    {
        $user = request()->user();

        return view('installments.index', [
            'plans' => InstallmentPlan::with('patient', 'service')->whereIn('status', ['active', 'completed'])->latest('created_at')->get(),
            'proofs' => $user->hasRole('admin', 'clinic_staff')
                ? InstallmentPayment::with('plan.patient', 'plan.service')->where('status', 'proof_submitted')->get()
                : collect(),
        ]);
    }

    public function createForPatient(InstallmentService $service)
    {
        return view('installments.create-for-patient', [
            'patients' => Patient::whereNotNull('user_id')->whereHas('user', fn ($query) => $query->where('is_email_verified', 1))
                ->whereNotIn('patient_id', InstallmentPlan::whereIn('status', ['requested', 'approved', 'active'])->select('patient_id'))
                ->orderBy('last_name')->orderBy('first_name')->get(),
            'services' => $service->eligibleServices()->get(),
            'maxMonths' => Settings::int('installment_max_months'),
            'minimumAmount' => max(10000, Settings::int('installment_min_amount')),
        ]);
    }

    public function storeForPatient(Request $request, InstallmentService $service)
    {
        $data = $request->validate([
            'patient_id' => 'required|exists:pat_patients,patient_id',
            'service_id' => 'required|exists:svc_services,service_id',
            'total_amount' => 'required|numeric|min:10000',
            'downpayment_percent' => 'required|numeric|gt:0|lt:100',
            'number_of_installments' => 'required|integer|min:2|max:'.Settings::int('installment_max_months'),
            'first_due_date' => 'required|date|after_or_equal:today',
            'terms_note' => 'nullable|string|max:1000',
        ]);
        $patient = Patient::whereNotNull('user_id')->findOrFail($data['patient_id']);
        $service->createPlan($patient, Service::findOrFail($data['service_id']), $data, $request->user()->user_id);

        return redirect()->route('installments.index')->with('status', 'Installment plan created. The in-clinic down payment was recorded and the patient can now pay the remaining balance online.');
    }

    public function schedule(InstallmentPlan $plan)
    {
        abort_unless(in_array($plan->status, ['active', 'approved'], true), 404);
        $plan->load('patient', 'service', 'payments');
        return view('installments.schedule', compact('plan'));
    }

    public function movePayment(Request $request, InstallmentPayment $row, InstallmentService $service)
    {
        $data = $request->validate(['due_date' => 'required|date|after_or_equal:today']);
        abort_unless(in_array($row->plan->status, ['active', 'approved'], true), 404);
        $service->moveDueDate($row, $data['due_date'], (int) $request->user()->user_id);
        return back()->with('status', 'Installment due date updated.');
    }

    public function splitPayment(Request $request, InstallmentPayment $row, InstallmentService $service)
    {
        $minimumDate = max(today()->toDateString(), $row->due_date->toDateString());
        $data = $request->validate(['second_due_date' => 'required|date|after_or_equal:'.$minimumDate]);
        abort_unless(in_array($row->plan->status, ['active', 'approved'], true), 404);
        $service->splitPayment($row, $data['second_due_date'], (int) $request->user()->user_id);
        return back()->with('status', 'Installment split into two scheduled payments.');
    }

    public function extendPlan(Request $request, InstallmentPlan $plan, InstallmentService $service)
    {
        abort_unless($plan->status === 'active', 404);
        $service->extendPlan($plan, (int) $request->user()->user_id);
        return back()->with('status', 'Plan extended by one month and its last unpaid amount was split.');
    }

    public function verify(InstallmentPayment $row, InstallmentService $service)
    {
        $service->verify($row, request()->user()->user_id);
        return back()->with('status', 'Proof verified and recorded as a payment.');
    }

    public function rejectProof(Request $request, InstallmentPayment $row, InstallmentService $service)
    {
        $data = $request->validate(['reason' => 'required|string|max:500']);
        $service->rejectProof($row, $data['reason']);
        return back()->with('status', 'Proof rejected.');
    }

    public function proof(InstallmentPayment $row)
    {
        abort_unless($row->proof_file_path, 404);
        $path = Storage::disk('local')->path($row->proof_file_path);
        abort_unless(is_file($path), 404);
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png'], true), 404);

        return response()->file($path, ['Content-Type' => $mime, 'Content-Disposition' => 'inline; filename="'.basename($path).'"', 'X-Content-Type-Options' => 'nosniff']);
    }
}
