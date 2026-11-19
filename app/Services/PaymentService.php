<?php

namespace App\Services;

use App\Models\Discount;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\Receipt;
use App\Models\Service;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function create(array $data, int $userId): Payment
    {
        return DB::transaction(function () use ($data, $userId) {
            $items = $this->items($data['items']);
            $subtotal = round(array_sum(array_column($items, 'line_total_amount')), 2);
            [$discountId, $discountAmount, $custom] = $this->discount($data, $subtotal);
            $total = round($subtotal - $discountAmount, 2);
            $paid = (float) $data['amount_paid'];
            if ($paid <= 0 || $paid > $total) throw ValidationException::withMessages(['amount_paid' => 'Payment must be greater than zero and no more than the bill total.']);
            $status = $data['payment_method'] === 'cash' ? 'verified' : 'pending_verification';
            $payment = Payment::create([
                'patient_id' => $data['patient_id'], 'appointment_id' => $data['appointment_id'] ?: null,
                'invoice_number' => $data['invoice_number'] ?: null, 'subtotal_amount' => $subtotal,
                'discount_id' => $discountId, 'discount_amount' => $discountAmount, 'total_amount' => $total,
                'amount_paid' => $paid, 'balance_amount' => round($total - $paid, 2),
                'payment_method' => $data['payment_method'], 'payment_date' => $data['payment_date'],
                'status' => $status, 'recorded_by_user_id' => $userId,
                'verified_by_user_id' => $status === 'verified' ? $userId : null,
                'verified_at' => $status === 'verified' ? now() : null,
            ]);
            foreach ($items as $item) $payment->items()->create($item);
            Audit::log('create', 'pay_payments', $payment->payment_id, 'Payment bill recorded', null, $payment->only(['total_amount', 'amount_paid', 'balance_amount', 'status']));
            if ($custom) Audit::log('custom_discount', 'pay_payments', $payment->payment_id, 'Custom discount applied', null, $custom);
            if ($status === 'verified') $this->issueReceipt($payment, $userId);
            return $payment->load('items.service', 'discount', 'patient', 'receipt');
        });
    }

    private function items(array $rows): array
    {
        $out = [];
        foreach ($rows as $row) {
            $service = Service::where('is_active', 1)->findOrFail($row['service_id']);
            $quantity = (int) $row['quantity'];
            $unit = (float) $row['unit_price_amount'];
            $out[] = ['service_id' => $service->service_id, 'tooth_number' => ($row['tooth_number'] ?? '') ?: null,
                'quantity' => $quantity, 'unit_price_amount' => $unit, 'line_total_amount' => round($quantity * $unit, 2)];
        }
        return $out;
    }

    private function discount(array $data, float $subtotal): array
    {
        $type = $data['discount_type'] ?? 'none';
        if ($type === 'preset') {
            $discount = Discount::where('is_active', 1)->findOrFail($data['discount_id']);
            return [$discount->discount_id, round($subtotal * (float) $discount->discount_percent / 100, 2), null];
        }
        if ($type === 'custom_percent' || $type === 'custom_amount') {
            $value = (float) $data['custom_discount_value'];
            $amount = $type === 'custom_percent' ? round($subtotal * $value / 100, 2) : round($value, 2);
            if ($amount <= 0 || $amount > $subtotal) throw ValidationException::withMessages(['custom_discount_value' => 'Discount must be greater than zero and cannot exceed the subtotal.']);
            return [null, $amount, ['discount_type' => $type, 'value' => $value, 'reason' => $data['discount_reason']]];
        }
        return [null, 0, null];
    }

    public function settle(Payment $payment, array $data, int $userId): void
    {
        DB::transaction(function () use ($payment, $data, $userId) {
            $payment = Payment::whereKey($payment->payment_id)->lockForUpdate()->firstOrFail();
            $balance = (float) $payment->total_amount - (float) $payment->amount_paid;
            $amount = (float) $data['amount_paid'];
            if ($amount <= 0 || $amount > $balance) throw ValidationException::withMessages(['amount_paid' => 'Settlement must be greater than zero and no more than the remaining balance.']);
            $old = ['amount_paid' => (float) $payment->amount_paid, 'balance_amount' => $balance];
            $old['status'] = $payment->status;
            $old['verified_by_user_id'] = $payment->verified_by_user_id;
            $old['verified_at'] = $payment->verified_at?->toDateTimeString();
            $payment->update(['amount_paid' => round($payment->amount_paid + $amount, 2), 'balance_amount' => round($balance - $amount, 2),
                'payment_method' => $data['payment_method'], 'payment_date' => $data['payment_date'], 'invoice_number' => $data['invoice_number'] ?: null,
                'status' => $data['payment_method'] === 'cash' ? 'verified' : 'pending_verification',
                'verified_by_user_id' => $data['payment_method'] === 'cash' ? $userId : null, 'verified_at' => $data['payment_method'] === 'cash' ? now() : null,
                'rejection_reason' => null]);
            Audit::log('settle', 'pay_payments', $payment->payment_id, 'Bill settlement recorded', $old,
                ['amount_received' => $amount, 'amount_paid' => (float) $payment->amount_paid, 'balance_amount' => (float) $payment->balance_amount, 'status' => $payment->status]);
            if ($payment->status === 'verified') $this->issueReceipt($payment, $userId);
        });
    }

    public function verify(Payment $payment, int $userId): void
    {
        DB::transaction(function () use ($payment, $userId) {
            $payment = Payment::whereKey($payment->payment_id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending_verification') throw ValidationException::withMessages(['payment' => 'Only pending payments can be verified.']);
            $payment->update(['status' => 'verified', 'verified_by_user_id' => $userId, 'verified_at' => now(), 'rejection_reason' => null]);
            Audit::log('verify', 'pay_payments', $payment->payment_id, 'Payment verified');
            $this->issueReceipt($payment, $userId);
        });
    }

    public function reject(Payment $payment, string $reason): void
    {
        DB::transaction(function () use ($payment, $reason) {
            $payment = Payment::whereKey($payment->payment_id)->lockForUpdate()->firstOrFail();
            if ($payment->status !== 'pending_verification') throw ValidationException::withMessages(['payment' => 'Only pending payments can be rejected.']);
            $old = ['amount_paid' => (float) $payment->amount_paid, 'balance_amount' => (float) $payment->balance_amount, 'status' => $payment->status];
            $settlement = AuditLog::where('table_affected', 'pay_payments')->where('record_id', (string) $payment->payment_id)
                ->where('action_type', 'settle')->orderByDesc('audit_log_id')->first();
            $prior = $settlement?->old_value;
            $updates = ['status' => $prior['status'] ?? 'rejected', 'rejection_reason' => $reason,
                'verified_by_user_id' => $prior['verified_by_user_id'] ?? null, 'verified_at' => $prior['verified_at'] ?? null,
                'amount_paid' => $prior['amount_paid'] ?? 0, 'balance_amount' => $prior['balance_amount'] ?? $payment->total_amount];
            if (($updates['status'] ?? null) === 'verified') $updates['rejection_reason'] = null;
            $payment->update($updates);
            Audit::log('reject', 'pay_payments', $payment->payment_id, 'Payment rejected: '.$reason, $old,
                ['amount_paid' => (float) $payment->amount_paid, 'balance_amount' => (float) $payment->balance_amount, 'rejection_reason' => $reason]);
        });
    }

    public function issueInstallmentReceipt(Payment $payment, int $userId): void
    {
        $this->issueReceipt($payment, $userId);
    }

    private function issueReceipt(Payment $payment, int $userId): void
    {
        if (! $payment->receipt()->exists()) Receipt::create(['payment_id' => $payment->payment_id,
            'receipt_number' => 'RC-'.now()->format('Ymd').'-'.str_pad((string) $payment->payment_id, 6, '0', STR_PAD_LEFT), 'issued_by_user_id' => $userId]);
    }
}
