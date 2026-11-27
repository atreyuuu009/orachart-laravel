@extends('layouts.app')
@section('title', 'Manage installment schedule')
@section('page_title', 'Manage installment schedule')

@section('content')
<div class="d-flex justify-content-between align-items-start mb-3"><div><h2 class="h5 mb-1">{{ $plan->patient->full_name }} · {{ $plan->service->service_name }}</h2><div class="text-secondary">Remaining balance ₱{{ number_format($plan->net_amount, 2) }} · {{ $plan->number_of_installments }} payments</div></div><a class="btn btn-outline-secondary" href="{{ route('installments.index') }}">Back</a></div>
<div class="alert alert-info">You can move an unpaid due date, split an unpaid amount into two payments, or extend the plan by splitting its last unpaid amount over one additional month. Changes are recorded in the audit log.</div>
<div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th><th>Due</th><th>Amount</th><th>Status</th><th>Adjust</th></tr></thead><tbody>
@foreach($plan->payments as $row)
<tr><td>{{ $row->installment_number }}</td><td>{{ $row->due_date->format('M j, Y') }}</td><td>₱{{ number_format($row->amount_due, 2) }}@if($row->late_fee_amount > 0)<div class="small text-danger">Includes ₱{{ number_format($row->late_fee_amount, 2) }} late fee</div>@endif</td><td>{{ str_replace('_', ' ', ucfirst($row->status)) }}</td><td>
@if(in_array($row->status, ['unpaid', 'overdue', 'rejected']))
<div class="d-flex flex-wrap gap-2">
<form method="POST" action="{{ route('installments.schedule.move', $row) }}" class="d-flex gap-1">@csrf<input class="form-control form-control-sm" type="date" name="due_date" min="{{ today()->toDateString() }}" value="{{ $row->due_date->toDateString() }}" required aria-label="New due date"><button class="btn btn-sm btn-outline-primary">Move date</button></form>
<form method="POST" action="{{ route('installments.schedule.split', $row) }}" class="d-flex gap-1">@csrf<input class="form-control form-control-sm" type="date" name="second_due_date" min="{{ today()->toDateString() }}" value="{{ max(today()->toDateString(), $row->due_date->copy()->addDays(10)->toDateString()) }}" required aria-label="Second payment due date"><button class="btn btn-sm btn-outline-primary">Split in two</button></form>
</div>
@else<span class="text-secondary">—</span>@endif
</td></tr>
@endforeach
</tbody></table></div></div>
<form method="POST" action="{{ route('installments.schedule.extend', $plan) }}" class="mt-3">@csrf<button class="btn btn-primary" @disabled(!$plan->payments->contains(fn($payment) => in_array($payment->status, ['unpaid', 'overdue', 'rejected'])))>Extend plan by one month</button></form>
@endsection
