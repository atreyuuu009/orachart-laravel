@extends('layouts.app')
@section('title', 'My Appointments')
@section('page_title', 'My Appointments')
@section('page_subtitle', 'Track and manage your appointments.')
@section('page_actions')<a class="btn btn-primary" href="{{ route('booking.create') }}">Book Appointment</a>@endsection
@section('content')
@if ($upcoming->contains(fn ($a) => $a->needs_reschedule && $a->isOpen()))
  <div class="alert alert-warning mb-4 shadow-sm border-warning">
    <div class="d-flex align-items-start gap-2">
      <div class="fs-4 text-warning">⚠️</div>
      <div>
        <strong class="d-block">Action Required: Clinic Schedule Change</strong>
        <div>One or more of your upcoming appointments was affected by a clinic schedule closure. Please reschedule or cancel at your convenience. <strong>No penalty applies</strong>, and booking notice restrictions have been waived for you.</div>
      </div>
    </div>
  </div>
@endif
<div class="card mb-4"><div class="card-header bg-white fw-bold">Upcoming</div>
  @include('appointments._table', ['rows' => $upcoming, 'actions' => true, 'patientView' => true, 'empty' => 'You have no upcoming appointments.'])</div>
<div class="card"><div class="card-header bg-white fw-bold">History</div>
  @include('appointments._table', ['rows' => $history, 'actions' => false, 'patientView' => true, 'empty' => 'No past appointments yet.'])</div>
@include('appointments._modals')
@endsection
