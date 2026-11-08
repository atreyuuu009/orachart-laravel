@extends('layouts.app')
@section('title', 'Dashboard')
@php $isPatient = $user->hasRole('patient'); @endphp
@section('page_title', $isPatient ? 'Home' : 'Dashboard')
@section('page_subtitle', $isPatient ? 'Welcome to Guda & Soriano Dental Clinic' : 'Overview of clinic activities.')
@section('page_actions')
  @if ($isPatient)<a class="btn btn-primary" href="{{ route('booking.create') }}">Book Appointment</a>
  @elseif ($user->hasRole('admin', 'clinic_staff'))<a class="btn btn-primary" href="{{ route('booking.staff') }}">Create Appointment</a>@endif
@endsection
@section('content')
<div class="row g-3 mb-4">
  @foreach ([['Total', $stats['total']], ['Pending', $stats['pending']], ['Confirmed', $stats['confirmed']], ['Completed', $stats['completed']], ['Cancelled', $stats['cancelled']]] as [$label, $n])
    <div class="col-6 col-md"><div class="card stat-card h-100"><div class="card-body"><div class="text-secondary small mb-1">{{ $label }}</div><div class="stat-value">{{ $n }}</div></div></div></div>
  @endforeach
  @if ($patientCount !== null)
    <div class="col-6 col-md"><div class="card stat-card h-100"><div class="card-body"><div class="text-secondary small mb-1">Patients</div><div class="stat-value">{{ $patientCount }}</div></div></div></div>
  @endif
</div>

@if ($needsReschedule)
  <div class="alert alert-warning">{{ $needsReschedule }} appointment(s) need rescheduling because of a schedule change.
    <a href="{{ route('appointments.index', ['status' => 'needs_reschedule']) }}" class="alert-link">Review</a></div>
@endif

@unless ($isPatient)
  <div class="card mb-4"><div class="card-header bg-white fw-bold">Today's appointments ({{ now()->format('M j') }})</div>
    @include('appointments._table', ['rows' => $today, 'actions' => false, 'empty' => 'No open appointments today.'])</div>
@endunless

<div class="card mb-4"><div class="card-header bg-white fw-bold">{{ $isPatient ? 'Your upcoming appointments' : 'Upcoming appointments' }}</div>
  @include('appointments._table', ['rows' => $upcoming, 'actions' => false, 'empty' => 'Nothing scheduled.'])</div>

<div class="card mb-4"><div class="card-header bg-white fw-bold d-flex justify-content-between">Recent activity
  <a class="small" href="{{ route($isPatient ? 'appointments.mine' : 'appointments.index') }}">View all</a></div>
  @include('appointments._table', ['rows' => $recent, 'actions' => false, 'empty' => 'No appointments yet.'])</div>

@if ($isPatient)
  <h2 class="h4 mt-5 mb-3">Dental Services &amp; Prices</h2>
  @include('partials.services', ['categories' => $categories])
@endif
@endsection
