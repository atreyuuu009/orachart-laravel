@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<h2 class="h4">Choose a new password</h2>
<form method="POST" action="{{ route('password.update') }}">@csrf
  <input type="hidden" name="token" value="{{ $token }}">
  <div class="mb-3"><label class="form-label">New password</label><input type="password" name="password" class="form-control" minlength="8" required autofocus></div>
  <div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password_confirmation" class="form-control" minlength="8" required></div>
  <button class="btn btn-primary w-100">Update password</button>
</form>
@endsection
