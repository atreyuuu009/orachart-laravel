@extends('layouts.app')
@section('title', 'Appointments')
@php $dentistView = auth()->user()->hasRole('dentist'); @endphp
@section('page_title', $dentistView ? 'My Appointments' : 'Manage Appointments')
@section('page_subtitle', 'Review and manage patient appointments.')
@section('page_actions')
  @if (auth()->user()->hasRole('admin', 'clinic_staff'))<a class="btn btn-primary" href="{{ route('booking.staff') }}">Create Appointment</a>@endif
@endsection
@section('content')
<form class="card card-body mb-3" method="GET"><div class="row g-2 align-items-end">
  <div class="col-md-3"><label class="form-label small">Search patient</label><input name="q" value="{{ request('q') }}" class="form-control" placeholder="Name, code or phone"></div>
  <div class="col-md-3"><label class="form-label small">Status</label><select name="status" class="form-select"><option value="">All</option>
    @foreach (['pending' => 'Pending', 'confirmed' => 'Confirmed', 'completed' => 'Completed', 'cancelled' => 'Cancelled', 'no_show' => 'No-show', 'rescheduled' => 'Rescheduled', 'needs_reschedule' => 'Needs reschedule'] as $v => $l)
      <option value="{{ $v }}" @selected(request('status') === $v)>{{ $l }}</option>@endforeach</select></div>
  <div class="col-md-2"><label class="form-label small">From</label><input type="date" name="from" value="{{ request('from') }}" class="form-control"></div>
  <div class="col-md-2"><label class="form-label small">To</label><input type="date" name="to" value="{{ request('to') }}" class="form-control"></div>
  <div class="col-md-2 d-flex gap-2"><button class="btn btn-primary flex-fill">Filter</button><a class="btn btn-light" href="{{ route('appointments.index') }}">Reset</a></div>
</div></form>
<div class="card">@include('appointments._table', ['rows' => $appointments, 'actions' => true, 'empty' => 'No appointments match your filters.'])</div>
<div class="mt-3">{{ $appointments->links('pagination::bootstrap-5') }}</div>
@include('appointments._modals')
@endsection
