@extends('layouts.app')
@section('title', 'Payments')
@section('page_title', 'Payment Recording')
@section('page_subtitle', 'Bills, verification, receipts, and outstanding balances.')
@section('content')
<div class="d-flex flex-wrap align-items-center gap-2 mb-3">
  <form class="d-flex align-items-center gap-2">
    <select class="form-select" name="status" style="max-width:220px"><option value="">All statuses</option>@foreach(['pending_verification','verified','rejected'] as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ str_replace('_',' ',ucfirst($s)) }}</option>@endforeach</select>
    <label class="form-check align-self-center mb-0 text-nowrap"><input class="form-check-input" type="checkbox" name="outstanding" value="1" @checked(request('outstanding'))> Outstanding only</label>
    <button class="btn btn-outline-primary flex-shrink-0">Filter</button>
  </form>
  <a class="btn btn-primary ms-auto" href="{{ route('payments.create') }}"><i class="bi bi-plus-lg"></i> Record bill</a>
</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Date</th><th>Patient</th><th>Invoice</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th></th></tr></thead><tbody>
@forelse($payments as $p)<tr><td>{{ $p->payment_date?->format('Y-m-d') }}</td><td>{{ $p->patient?->full_name }}</td><td>{{ $p->invoice_number ?: '—' }}</td><td>₱{{ number_format($p->total_amount,2) }}</td><td>₱{{ number_format($p->amount_paid,2) }}</td><td class="{{ $p->balance_amount > 0 ? 'text-danger fw-semibold' : '' }}">₱{{ number_format($p->balance_amount,2) }}</td><td><span class="badge text-bg-{{ ['verified'=>'success','pending_verification'=>'warning','rejected'=>'danger'][$p->status] }}">{{ str_replace('_',' ',$p->status) }}</span></td><td><a class="btn btn-sm btn-outline-primary" href="{{ route('payments.show',$p) }}">Open</a></td></tr>@empty<tr><td colspan="8" class="text-center text-secondary py-4">No bills recorded.</td></tr>@endforelse
</tbody></table></div></div><div class="mt-3">{{ $payments->links('pagination::bootstrap-5') }}</div>
@endsection
