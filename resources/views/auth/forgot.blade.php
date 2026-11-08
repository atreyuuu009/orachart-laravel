@extends('layouts.auth')
@section('title', 'Forgot password')
@section('content')
<h2 class="h4">Forgot your password?</h2><p class="text-secondary">Enter your e-mail and we will send you a reset link.</p>
<form method="POST" action="{{ route('password.email') }}">@csrf
  <div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="{{ old('email') }}" required autofocus></div>
  <button class="btn btn-primary w-100">Send reset link</button>
</form>
<div class="text-center mt-3"><a href="{{ route('login') }}">Back to login</a></div>
@endsection
