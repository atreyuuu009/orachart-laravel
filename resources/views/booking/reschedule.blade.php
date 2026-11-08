@extends('layouts.app')
@section('title', 'Reschedule')
@section('page_title', 'Reschedule Appointment')
@section('page_subtitle', 'Pick a new date and time. Your current slot is released once the new one is saved.')
@section('content')
<form method="POST" action="{{ route('appointments.reschedule.store', $appointment) }}" id="bookingForm" class="card"><div class="card-body p-4">@csrf
  <div class="alert alert-light border">
    <strong>{{ $appointment->patient->full_name }}</strong> — {{ $appointment->service->service_name }} ({{ $appointment->service->duration_label }})<br>
    Current: {{ $appointment->start_at->format('l, M j, Y · g:i A') }} with {{ $appointment->dentist->name }}
  </div>
  <input type="hidden" name="service_id" id="serviceId" value="{{ $appointment->service_id }}">
  @include('booking._picker', ['dentists' => $dentists, 'dentistValue' => $appointment->assigned_dentist_id])
  <div class="d-flex justify-content-end gap-2 mt-4">
    <a class="btn btn-light" href="{{ url()->previous() }}">Back</a>
    <button class="btn btn-primary px-4" id="submitBtn">Save new time</button>
  </div>
</div></form>
@endsection
@push('scripts')
<script>window.BOOKING = {slotsUrl: @json(route('booking.slots')), holdUrl: @json(route('booking.hold')), exclude: {{ $appointment->appointment_id }}, mode: 'reschedule'};</script>
<script src="{{ asset('js/booking.js') }}"></script>
@endpush
