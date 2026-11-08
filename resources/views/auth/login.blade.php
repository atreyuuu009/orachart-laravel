@extends('layouts.auth')
@section('title', 'Login')
@section('content')
<ul class="nav nav-tabs mb-3"><li class="nav-item"><a class="nav-link active" href="{{ route('login') }}">Login</a></li><li class="nav-item"><a class="nav-link" href="{{ route('register') }}">Register</a></li></ul>
<h2 class="h4">Welcome Back</h2><p class="text-secondary">Login to continue to your account.</p>
<form method="POST" action="{{ route('login.attempt') }}">@csrf
  <div class="mb-3"><label class="form-label" for="email">Email</label>
    <input id="email" type="email" name="email" class="form-control" value="{{ old('email') }}" placeholder="you@example.com" required autofocus></div>
  <div class="mb-2"><label class="form-label" for="password">Password</label>
    <input id="password" type="password" name="password" class="form-control" required></div>
  <div class="text-end mb-3"><a class="small" href="{{ route('password.request') }}">Forgot password?</a></div>
  <button class="btn btn-primary w-100">Login</button>
</form>
@if (session('unverified_email'))
  <form method="POST" action="{{ route('verification.resend') }}" class="mt-3">@csrf
    <input type="hidden" name="email" value="{{ session('unverified_email') }}">
    <button class="btn btn-link p-0">Resend verification e-mail</button>
  </form>
@endif
@if (app()->environment('local'))
  <div class="demo-box mt-4"><strong>Demo accounts</strong> (local only, password <code>123456</code>)<br>
    Patient: patient@demo.com<br>Dentists: Guda@gmail.com · Soriano@gmail.com<br>Staff: pericano@gmail.com · Admin: atreyu@gmail.com</div>
@endif
@endsection
