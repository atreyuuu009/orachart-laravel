@extends('layouts.auth')
@section('title', 'Create account')
@section('content')
<ul class="nav nav-tabs mb-3"><li class="nav-item"><a class="nav-link" href="{{ route('login') }}">Login</a></li><li class="nav-item"><a class="nav-link active" href="{{ route('register') }}">Register</a></li></ul>
<h2 class="h4">Create Patient Account</h2><p class="text-secondary">Register for online dental services.</p>
<form method="POST" action="{{ route('register.store') }}">@csrf
  <div class="row g-3">
    <div class="col-6"><label class="form-label">First Name</label><input name="first_name" class="form-control" value="{{ old('first_name') }}" required></div>
    <div class="col-6"><label class="form-label">Last Name</label><input name="last_name" class="form-control" value="{{ old('last_name') }}" required></div>
    <div class="col-12"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required></div>
    <div class="col-md-6"><label class="form-label">Phone Number</label><input type="tel" name="contact_number" class="form-control" value="{{ old('contact_number') }}" placeholder="09XXXXXXXXX" required></div>
    <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" name="birth_date" class="form-control" max="{{ now()->subDay()->toDateString() }}" value="{{ old('birth_date') }}" required></div>
    <div class="col-md-6"><label class="form-label">Password</label><input type="password" name="password" class="form-control" minlength="8" required></div>
    <div class="col-md-6"><label class="form-label">Confirm Password</label><input type="password" name="password_confirmation" class="form-control" minlength="8" required></div>
  </div>
  <div class="demo-box my-3">
    <strong>Data Privacy Consent</strong>
    <p class="mb-2 small">I consent to the collection and processing of my personal information for dental appointment scheduling and clinic-related services.</p>
    <div class="form-check"><input class="form-check-input" type="checkbox" name="privacy_consent" value="1" id="pc" required>
      <label class="form-check-label small" for="pc">I have read and agree to the Data Privacy Consent Form.</label></div>
  </div>
  <button class="btn btn-primary w-100">Create Account</button>
</form>
@endsection
