@extends('layouts.app')
@section('title', 'My Appointments')
@section('page_title', 'My Appointments')
@section('page_subtitle', 'Track and manage your appointments.')
@section('page_actions')<a class="btn btn-primary" href="{{ route('booking.create') }}">Book Appointment</a>@endsection
@section('content')
<div class="card mb-4"><div class="card-header bg-white fw-bold">Upcoming</div>
  @include('appointments._table', ['rows' => $upcoming, 'actions' => true, 'patientView' => true, 'empty' => 'You have no upcoming appointments.'])</div>
<div class="card"><div class="card-header bg-white fw-bold">History</div>
  @include('appointments._table', ['rows' => $history, 'actions' => false, 'patientView' => true, 'empty' => 'No past appointments yet.'])</div>
@include('appointments._modals')
@endsection
