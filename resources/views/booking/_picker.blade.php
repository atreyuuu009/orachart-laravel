{{-- Shared date + slot picker. Expects: $dentists, optional $fixedDentist, $dentistValue --}}
<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Preferred dentist</label>
    <select name="dentist_id" id="dentistSelect" class="form-select">
      <option value="">No preference – assign available dentist</option>
      @foreach ($dentists as $d)
        <option value="{{ $d->dentist_id }}" @selected((string) old('dentist_id', $dentistValue ?? '') === (string) $d->dentist_id)>{{ $d->name }} ({{ $d->specialty_label }})</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-6">
    <label class="form-label">Preferred date</label>
    <input type="date" name="date" id="dateInput" class="form-control" min="{{ today()->toDateString() }}" value="{{ old('date') }}" required>
  </div>
  <div class="col-12">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="emergency" id="emergency" value="1" @checked(old('emergency'))>
      <label class="form-check-label" for="emergency">This is an emergency (severe pain, swelling or bleeding)</label>
    </div>
    <div id="emergencyBox" class="mt-2 d-none">
      <textarea name="emergency_details" class="form-control" rows="2" maxlength="400" placeholder="Describe the symptoms and when they started">{{ old('emergency_details') }}</textarea>
      <div class="form-text text-danger">If symptoms are severe or life-threatening, seek emergency medical care immediately.</div>
    </div>
  </div>
  <div class="col-12">
    <label class="form-label">Available times</label>
    <div id="slotMsg" class="text-secondary small mb-2">Choose a procedure and a date to see available times.</div>
    <div id="slotGrid" class="slot-grid"></div>
    <input type="hidden" name="time" id="timeInput" value="">
    <div id="holdBox" class="alert alert-info py-2 mt-3 d-none mb-0"></div>
  </div>
</div>
