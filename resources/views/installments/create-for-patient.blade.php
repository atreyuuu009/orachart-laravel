@extends('layouts.app')
@section('title', 'Create installment plan')
@section('page_title', 'Create installment plan')

@section('content')
<div class="card">
  <div class="card-body">
    <p class="text-secondary">Complete this after the consultation. Record the down payment collected at the clinic, then the patient can pay only the remaining balance through the system.</p>
    <form method="POST" action="{{ route('installments.store-for-patient') }}" id="installmentPlanForm">
      @csrf
      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label" for="patient_id">Patient</label>
          <select class="form-select @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" required>
            <option value="">Select a patient</option>
            @foreach($patients as $patient)<option value="{{ $patient->patient_id }}" @selected(old('patient_id') == $patient->patient_id)>{{ $patient->full_name }} ({{ $patient->patient_code }})</option>@endforeach
          </select>
          @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-6">
          <label class="form-label" for="service_id">Eligible treatment</label>
          <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
            <option value="">Select a treatment</option>
            @foreach($services as $service)<option value="{{ $service->service_id }}" data-price="{{ $service->price_min_amount }}" @selected(old('service_id') == $service->service_id)>{{ $service->service_name }} — {{ $service->price_label }}</option>@endforeach
          </select>
          @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
          <div class="form-text">Eligible treatments cost at least ₱{{ number_format($minimumAmount, 2) }}.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="total_amount">Agreed treatment total</label>
          <input class="form-control @error('total_amount') is-invalid @enderror" id="total_amount" name="total_amount" type="number" min="{{ $minimumAmount }}" step="0.01" value="{{ old('total_amount') }}" required>
          @error('total_amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label" for="downpayment_percent">Down payment collected at clinic</label>
          <div class="input-group"><input class="form-control @error('downpayment_percent') is-invalid @enderror" id="downpayment_percent" name="downpayment_percent" type="number" min="0.01" max="99.99" step="0.01" value="{{ old('downpayment_percent', 30) }}" required><span class="input-group-text">%</span></div>
          @error('downpayment_percent')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
          <div class="form-text">For example: 30 or 50.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Collected today</label>
          <div class="form-control bg-body-secondary" id="downpaymentAmount">₱0.00</div>
          <div class="form-text">Recorded as an in-clinic cash payment.</div>
        </div>
        <div class="col-md-4">
          <label class="form-label" for="number_of_installments">Monthly payments</label>
          <input class="form-control @error('number_of_installments') is-invalid @enderror" id="number_of_installments" name="number_of_installments" type="number" min="2" max="{{ $maxMonths }}" value="{{ old('number_of_installments', 2) }}" required>
          @error('number_of_installments')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label" for="first_due_date">First online payment due</label>
          <input class="form-control @error('first_due_date') is-invalid @enderror" id="first_due_date" name="first_due_date" type="date" min="{{ today()->toDateString() }}" value="{{ old('first_due_date') }}" required>
          @error('first_due_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
        <div class="col-md-4">
          <label class="form-label">Remaining balance for online payments</label>
          <div class="form-control bg-body-secondary" id="remainingAmount">₱0.00</div>
          <div class="form-text" id="monthlyEstimate">Enter the total and term to see the monthly amount.</div>
        </div>
        <div class="col-12">
          <label class="form-label" for="terms_note">Notes for the patient</label>
          <textarea class="form-control" id="terms_note" name="terms_note" maxlength="1000" rows="2">{{ old('terms_note') }}</textarea>
        </div>
      </div>
      <div class="d-flex gap-2 mt-4"><button class="btn btn-primary" type="submit" @disabled($patients->isEmpty() || $services->isEmpty())>Create plan and record down payment</button><a class="btn btn-outline-secondary" href="{{ route('installments.index') }}">Cancel</a></div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const service = document.getElementById('service_id');
  const total = document.getElementById('total_amount');
  const percent = document.getElementById('downpayment_percent');
  const months = document.getElementById('number_of_installments');
  const peso = amount => `₱${Number(amount || 0).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
  const update = () => {
    const amount = Number(total.value || 0);
    const rate = Number(percent.value || 0);
    const paid = Math.round(amount * rate) / 100;
    const remaining = Math.max(0, amount - paid);
    const term = Number(months.value || 0);
    document.getElementById('downpaymentAmount').textContent = peso(paid);
    document.getElementById('remainingAmount').textContent = peso(remaining);
    document.getElementById('monthlyEstimate').textContent = term >= 2 ? `${term} payments of about ${peso(remaining / term)}.` : 'Enter a term of at least 2 months.';
  };
  service.addEventListener('change', () => { if (!total.value) total.value = service.selectedOptions[0]?.dataset.price || ''; update(); });
  [total, percent, months].forEach(input => input.addEventListener('input', update));
  update();
})();
</script>
@endpush
