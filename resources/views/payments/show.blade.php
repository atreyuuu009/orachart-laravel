@extends('layouts.app')

@section('title', 'Bill #'.$payment->payment_id)
@section('page_title', 'Bill #'.$payment->payment_id)
@section('page_subtitle', $payment->patient?->full_name.' · '.$payment->payment_date?->format('F j, Y'))

@section('content')
  @if($errors->any())
    <div class="alert alert-danger">
      @foreach($errors->all() as $error)
        <div>{{ $error }}</div>
      @endforeach
    </div>
  @endif

  <div class="d-flex justify-content-end gap-2 mb-3">
    <a class="btn btn-outline-secondary" href="{{ route('payments.index') }}">All payments</a>
    @if($payment->receipt)
      <a class="btn btn-primary" target="_blank" href="{{ route('payments.receipt', $payment) }}">Print receipt</a>
    @endif
  </div>

  <div class="row g-3">
    <div class="col-lg-7">
      <div class="card">
        <div class="table-responsive">
          <table class="table mb-0">
            <thead><tr><th>Service</th><th>Qty</th><th>Unit</th><th>Total</th></tr></thead>
            <tbody>
              @foreach($payment->items as $item)
                <tr>
                  <td>{{ $item->service?->service_name }} @if($item->tooth_number) · Tooth {{ $item->tooth_number }} @endif</td>
                  <td>{{ $item->quantity }}</td>
                  <td>₱{{ number_format($item->unit_price_amount, 2) }}</td>
                  <td>₱{{ number_format($item->line_total_amount, 2) }}</td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
        <div class="p-3 ms-auto" style="max-width:320px">
          <div class="d-flex justify-content-between"><span>Subtotal</span><span>₱{{ number_format($payment->subtotal_amount, 2) }}</span></div>
          <div class="d-flex justify-content-between">
            <span>{{ $payment->discount?->discount_name ?? ($payment->discount_amount > 0 ? 'Custom discount' : 'Discount') }}</span>
            <span>- ₱{{ number_format($payment->discount_amount, 2) }}</span>
          </div>
          <hr>
          <div class="d-flex justify-content-between fw-bold"><span>Total</span><span>₱{{ number_format($payment->total_amount, 2) }}</span></div>
          <div class="d-flex justify-content-between"><span>Amount paid</span><span>₱{{ number_format($payment->amount_paid, 2) }}</span></div>
          <div class="d-flex justify-content-between fw-bold {{ $payment->balance_amount > 0 ? 'text-danger' : 'text-success' }}">
            <span>Balance</span><span>₱{{ number_format($payment->balance_amount, 2) }}</span>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <div class="card p-3 mb-3">
        <h2 class="h6">Payment status</h2>
        <p>
          <span class="badge text-bg-{{ ['verified' => 'success', 'pending_verification' => 'warning', 'rejected' => 'danger'][$payment->status] }}">
            {{ str_replace('_', ' ', $payment->status) }}
          </span>
          · {{ ucwords(str_replace('_', ' ', $payment->payment_method)) }}
        </p>
        @if($payment->rejection_reason)
          <p class="text-danger">{{ $payment->rejection_reason }}</p>
        @endif
        @if($payment->invoice_number)
          <p>Invoice: {{ $payment->invoice_number }}</p>
        @endif
        @if($payment->receipt)
          <p>Receipt: {{ $payment->receipt->receipt_number }}</p>
        @endif

        @if($payment->status === 'pending_verification')
          <form method="POST" action="{{ route('payments.verify', $payment) }}" class="mb-2">
            @csrf
            <button class="btn btn-success w-100">Verify and issue receipt</button>
          </form>
          <form method="POST" action="{{ route('payments.reject', $payment) }}">
            @csrf
            <label class="form-label">Rejection reason</label>
            <textarea class="form-control mb-2" name="rejection_reason" maxlength="500" required></textarea>
            <button class="btn btn-outline-danger w-100">Reject payment</button>
          </form>
        @endif
      </div>

      @if($payment->balance_amount > 0 && $payment->status !== 'pending_verification')
        <div class="card p-3">
          <h2 class="h6">Settle remaining balance</h2>
          <p class="small text-secondary">Discount remains fixed. This updates the bill's payment date, method, and invoice. Settlement history is kept in the audit log.</p>
          <form method="POST" action="{{ route('payments.settle', $payment) }}">
            @csrf
            <div class="mb-2">
              <label class="form-label">Amount received (max ₱{{ number_format($payment->balance_amount, 2) }})</label>
              <input class="form-control" name="amount_paid" type="number" step="0.01" min="0.01" max="{{ $payment->balance_amount }}" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Date</label>
              <input class="form-control" name="payment_date" type="date" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Method</label>
              <select class="form-select" name="payment_method">
                @foreach(['cash', 'gcash', 'bank_transfer', 'card', 'other'] as $method)
                  <option value="{{ $method }}">{{ ucwords(str_replace('_', ' ', $method)) }}</option>
                @endforeach
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Invoice number (if new invoice)</label>
              <input class="form-control" name="invoice_number" maxlength="50">
            </div>
            <button class="btn btn-primary w-100">Record settlement</button>
          </form>
        </div>
      @endif
    </div>
  </div>
@endsection
