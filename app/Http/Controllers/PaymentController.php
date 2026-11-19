<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Discount;
use App\Models\Patient;
use App\Models\Payment;
use App\Models\Service;
use App\Services\Notifier;
use App\Services\PaymentService;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $payments = Payment::with(['patient', 'discount', 'receipt'])->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->when($request->filled('outstanding'), fn ($q) => $q->where('balance_amount', '>', 0))
            ->orderByDesc('payment_date')->orderByDesc('payment_id')->paginate(20)->withQueryString();
        return view('payments.index', compact('payments'));
    }

    public function create()
    {
        return view('payments.create', ['patients' => Patient::orderBy('last_name')->orderBy('first_name')->get(),
            'services' => Service::where('is_active', 1)->orderBy('service_name')->get(),
            'serviceOptions' => Service::where('is_active', 1)->orderBy('service_name')->get()->map(fn ($service) => [
                'id' => $service->service_id, 'name' => $service->service_name, 'price' => (float) $service->price_min_amount,
                'unit' => $service->pricing_unit, 'extra' => (float) ($service->additional_unit_price_amount ?? 0),
            ])->values(),
            'discounts' => Discount::where('is_active', 1)->where('discount_name', '!=', 'RCT Restoration Promo')->orderBy('discount_name')->get(),
            'appointments' => Appointment::whereIn('status', ['confirmed', 'completed'])->with('patient')->orderByDesc('start_at')->limit(300)->get()]);
    }

    public function store(Request $request, PaymentService $service)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:pat_patients,patient_id'],
            'appointment_id' => ['nullable', 'integer', 'exists:apt_appointments,appointment_id'],
            'invoice_number' => ['nullable', 'string', 'max:50', 'unique:pay_payments,invoice_number'],
            'payment_date' => ['required', 'date'], 'payment_method' => ['required', Rule::in(['cash', 'gcash', 'bank_transfer', 'card', 'other'])],
            'amount_paid' => ['required', 'numeric', 'gt:0'], 'discount_type' => ['required', Rule::in(['none', 'preset', 'custom_percent', 'custom_amount'])],
            'discount_id' => ['nullable', 'required_if:discount_type,preset', 'exists:pay_discounts,discount_id'],
            'custom_discount_value' => ['nullable', 'required_if:discount_type,custom_percent,custom_amount', 'numeric', 'gt:0', Rule::when($request->input('discount_type') === 'custom_percent', ['lte:100'])],
            'discount_reason' => ['nullable', 'required_if:discount_type,custom_percent,custom_amount', 'string', 'max:255'],
            'items' => ['required', 'array', 'min:1'], 'items.*.service_id' => ['required', 'exists:svc_services,service_id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'], 'items.*.unit_price_amount' => ['required', 'numeric', 'min:0'],
            'items.*.tooth_number' => ['nullable', 'integer', 'between:1,32'],
        ]);
        if ($data['appointment_id'] && Appointment::whereKey($data['appointment_id'])->value('patient_id') != $data['patient_id']) {
            return back()->withErrors(['appointment_id' => 'The selected appointment belongs to a different patient.'])->withInput();
        }
        $payment = $service->create($data, (int) $request->user()->user_id);
        return redirect()->route('payments.show', $payment)->with('status', 'Bill recorded. '.($payment->status === 'verified' ? 'Receipt issued.' : 'Payment is awaiting verification.'));
    }

    public function show(Payment $payment)
    {
        return view('payments.show', ['payment' => $payment->load('patient', 'appointment', 'discount', 'items.service', 'receipt')]);
    }

    public function settle(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['amount_paid' => ['required', 'numeric', 'gt:0'], 'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(['cash', 'gcash', 'bank_transfer', 'card', 'other'])],
            'invoice_number' => ['nullable', 'string', 'max:50', 'unique:pay_payments,invoice_number,'.$payment->payment_id.',payment_id']]);
        $service->settle($payment, $data, (int) $request->user()->user_id);
        return back()->with('status', 'Settlement recorded.');
    }

    public function verify(Payment $payment, PaymentService $service)
    {
        $service->verify($payment, (int) request()->user()->user_id);
        $this->notify($payment, 'payment_verified', 'Payment verified');
        return back()->with('status', 'Payment verified and receipt issued.');
    }

    public function reject(Request $request, Payment $payment, PaymentService $service)
    {
        $data = $request->validate(['rejection_reason' => ['required', 'string', 'max:500']]);
        $service->reject($payment, $data['rejection_reason']);
        $this->notify($payment, 'payment_rejected', $data['rejection_reason']);
        return back()->with('status', 'Payment rejected. The outstanding balance has been restored.');
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->status === 'verified' && $payment->receipt()->exists(), 404);
        return view('payments.receipt', ['payment' => $payment->load('patient', 'discount', 'items.service', 'receipt')]);
    }

    private function notify(Payment $payment, string $key, string $reason): void
    {
        $patient = $payment->patient;
        Notifier::queue($key, $patient->email, ['first_name' => $patient->first_name, 'patient_name' => $patient->full_name,
            'total_amount' => number_format((float) $payment->total_amount, 2), 'amount_paid' => number_format((float) $payment->amount_paid, 2),
            'balance_amount' => number_format((float) $payment->balance_amount, 2), 'receipt_number' => $payment->receipt?->receipt_number ?? '',
            'reason' => $reason, 'payment_context' => ''], $patient->user_id);
        Audit::log('update', 'pay_payments', $payment->payment_id, 'Payment notification requested: '.$key);
    }
}
