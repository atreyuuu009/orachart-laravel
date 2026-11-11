@extends('layouts.app')
@section('title', 'Reschedule')
@section('page_title', 'Reschedule Appointment')
@section('page_subtitle', 'Pick a new date and time. Your current slot is released once the new one is saved.')
@section('content')
<form method="POST" action="{{ route('appointments.reschedule.store', $appointment) }}" id="bookingForm" class="card"><div class="card-body p-4">@csrf
  @if ($appointment->needs_reschedule)
    <div class="alert alert-warning border-warning mb-3 shadow-sm">
      <div class="d-flex gap-2">
        <div class="fs-4 text-warning">⚠️</div>
        <div>
          <strong class="d-block">Clinic Schedule Change — Reschedule Notice</strong>
          <div>This appointment is being rescheduled due to a clinic schedule closure or adjustment. <strong>No rescheduling fees or penalties apply.</strong> Standard booking notice and daily clinic capacity limits have been waived for your rescheduled visit.</div>
          <div class="mt-2 small text-dark">
            <strong>Dentist options:</strong>
            <ul class="mb-0 ps-3">
              <li>To stay with <strong>{{ $appointment->dentist->name }}</strong>, pick another date.</li>
              <li>To keep an appointment time on the same date, select <strong>"No preference – assign available dentist"</strong> or choose another dentist from the dropdown below.</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  @endif
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
