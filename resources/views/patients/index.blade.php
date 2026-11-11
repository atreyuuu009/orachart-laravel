@extends('layouts.app')
@section('title', 'Patients')
@section('page_title', 'Patient Management')
@section('page_subtitle', 'Patient directory and clinical records.')
@section('page_actions')
  @if(auth()->user()->hasRole('admin','clinic_staff'))<a class="btn btn-primary" href="{{ route('patients.create') }}">Add patient</a>@endif
@endsection
@section('content')
<form class="card card-body mb-3" method="GET"><div class="row g-2"><div class="col-md-8"><label class="form-label small">Find a patient</label><input class="form-control" name="q" value="{{ request('q') }}" placeholder="Name, patient code or phone"></div><div class="col-md-4 d-flex align-items-end gap-2"><button class="btn btn-primary">Search</button><a class="btn btn-light" href="{{ route('patients.index') }}">Reset</a></div></div></form>
<div class="card table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Patient</th><th>Patient code</th><th>Birth date</th><th>Contact</th><th>Appointments</th><th></th></tr></thead><tbody>
@forelse($patients as $patient)<tr><td><a class="fw-semibold text-decoration-none" href="{{ route('patients.show',$patient) }}">{{ $patient->full_name }}</a><div class="small text-secondary">{{ ucfirst($patient->sex ?? 'Not specified') }}</div></td><td>{{ $patient->patient_code }}</td><td>{{ $patient->birth_date?->format('M j, Y') }} <span class="text-secondary small">({{ $patient->age }})</span></td><td>{{ $patient->contact_number }}<div class="small text-secondary">{{ $patient->email }}</div></td><td>{{ $patient->appointments_count }}</td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="{{ route('patients.show',$patient) }}">Open record</a></td></tr>
@empty<tr><td colspan="6" class="text-center text-secondary py-5">No patient records found.</td></tr>@endforelse
</tbody></table></div><div class="mt-3">{{ $patients->links('pagination::bootstrap-5') }}</div>
@endsection
