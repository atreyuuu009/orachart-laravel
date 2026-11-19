@extends('layouts.app')
@section('title','Request installment') @section('page_title','Request an installment plan')
@section('content')
<div class="card"><form method="POST" action="{{ route('installments.store') }}">@csrf<div class="card-body">
<p class="text-secondary">Choose a treatment and preferred term. The clinic will confirm the final balance, any discount, finance charge and due dates before you accept.</p>
<label class="form-label">Eligible treatment</label><select class="form-select mb-3" name="service_id" required><option value="">Select a treatment</option>@foreach($services as $s)<option value="{{ $s->service_id }}" @selected(old('service_id')==$s->service_id)>{{ $s->service_name }} — estimate ₱{{ number_format($s->price_min_amount,2) }}</option>@endforeach</select>
<label class="form-label">Preferred number of months</label><select class="form-select" name="months" required>@for($i=2;$i<=$maxMonths;$i++)<option value="{{ $i }}" @selected(old('months')==$i)>{{ $i }} months</option>@endfor</select>
</div><div class="card-footer text-end"><button class="btn btn-primary">Send request</button></div></form></div>
@endsection
