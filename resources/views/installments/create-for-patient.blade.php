@extends('layouts.app')
@section('title', 'Create installment request')
@section('page_title', 'Create installment request')

@section('content')
<div class="card">
    <div class="card-body">
        <p class="text-secondary">Choose a patient and eligible service to submit an installment request. The plan still needs review, and the patient must accept the final terms.</p>
        <form method="POST" action="{{ route('installments.store-for-patient') }}">
            @csrf
            <div class="mb-3">
                <label class="form-label" for="patient_id">Patient</label>
                <select class="form-select @error('patient_id') is-invalid @enderror" id="patient_id" name="patient_id" required>
                    <option value="">Select a patient</option>
                    @foreach($patients as $patient)
                        <option value="{{ $patient->patient_id }}" @selected(old('patient_id') == $patient->patient_id)>{{ $patient->full_name }} ({{ $patient->patient_code }})</option>
                    @endforeach
                </select>
                @error('patient_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($patients->isEmpty())<div class="form-text">No patients with a login account are available.</div>@endif
            </div>
            <div class="mb-3">
                <label class="form-label" for="service_id">Eligible service</label>
                <select class="form-select @error('service_id') is-invalid @enderror" id="service_id" name="service_id" required>
                    <option value="">Select a service</option>
                    @foreach($services as $service)
                        <option value="{{ $service->service_id }}" @selected(old('service_id') == $service->service_id)>{{ $service->service_name }} — {{ $service->price_label }}</option>
                    @endforeach
                </select>
                @error('service_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                @if($services->isEmpty())<div class="form-text">There are currently no eligible services.</div>@endif
            </div>
            <div class="mb-3">
                <label class="form-label" for="months">Requested number of months</label>
                <input class="form-control @error('months') is-invalid @enderror" type="number" id="months" name="months" min="2" max="{{ $maxMonths }}" value="{{ old('months', 2) }}" required>
                @error('months')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Choose 2 to {{ $maxMonths }} months. Final terms are set during review.</div>
            </div>
            <div class="d-flex gap-2">
                <button class="btn btn-primary" type="submit" @disabled($patients->isEmpty() || $services->isEmpty())>Create request</button>
                <a class="btn btn-outline-secondary" href="{{ route('installments.index') }}">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
