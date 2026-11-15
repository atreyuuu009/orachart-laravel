@extends('layouts.app')
@section('title','User Management')
@section('page_title','User Management')
@section('page_subtitle','Manage account access and staff profiles.')
@section('content')
<style>
  .user-action { border: 0; border-radius: 999px; padding: .35rem .7rem; font-weight: 600; transition: transform .15s ease, box-shadow .15s ease; }
  .user-action:hover { transform: translateY(-1px); box-shadow: 0 .25rem .6rem #24324b20; }
  .user-action-edit { color: #6546a5; background: #f0eaff; }
  .user-action-deactivate { color: #a9435e; background: #ffe8ee; }
  .user-action-reactivate { color: #287b58; background: #e2f7eb; }
  .user-action-reset { color: #4866a8; background: #e8efff; }
  .user-action-unlock { color: #90651c; background: #fff3d6; }
  .user-action-verify { color: #39778a; background: #e2f6fa; }
</style>
<div class="row g-2 mb-3 align-items-stretch"><form id="userFilters" class="col-md-10 row g-2" method="GET"><div class="col-md-5"><input class="form-control" name="search" value="{{ request('search') }}" placeholder="Search name, username or email"></div><div class="col-md-3"><select class="form-select" name="role" aria-label="Filter by role"><option value="">All roles</option>@foreach($roles as $role)<option value="{{ $role->role_name }}" @selected(request('role')===$role->role_name)>{{ ['admin'=>'Admin','clinic_staff'=>'Clinic Staff','dentist'=>'Dentist','patient'=>'Patient'][$role->role_name] ?? ucfirst(str_replace('_',' ',$role->role_name)) }}</option>@endforeach</select></div><div class="col-md-4"><select class="form-select" name="status" aria-label="Filter by status"><option value="">All statuses</option>@foreach(['active'=>'Active','inactive'=>'Inactive','locked'=>'Locked'] as $v=>$label)<option value="{{ $v }}" @selected(request('status')===$v)>{{ $label }}</option>@endforeach</select></div></form><div class="col-md-2"><a class="btn btn-primary w-100 h-100 d-flex align-items-center justify-content-center" href="{{ route('admin.users.create') }}">Add Staff User</a></div></div>
<div class="table-responsive card"><table class="table table-hover align-middle mb-0"><thead><tr><th>Name</th><th>Username / Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Created</th><th></th></tr></thead><tbody>
@forelse($users as $u)<tr><td>{{ $u->full_name }}@if($u->hasRole('dentist'))<div class="small text-secondary">License: {{ $u->dentist?->license_number ?: '—' }} · {{ $u->dentist?->specialties->pluck('specialty_name')->join(', ') ?: 'General Dentistry' }}<br>Online bookings: {{ $u->dentist?->is_accepting_bookings ? 'Yes' : 'No' }}</div>@elseif($u->hasRole('patient'))<div class="small text-secondary">Patient code: {{ $u->patient?->patient_code ?: '—' }}</div>@endif</td><td>{{ $u->username }}<div class="small">{{ $u->email }}</div><div class="small text-secondary">{{ $u->contact_number ?: 'No contact number' }}</div></td><td>{{ $u->role_label }}</td><td>@if(!$u->is_active)<span class="badge text-bg-secondary">Inactive</span>@else<span class="badge text-bg-success">Active</span>@endif @if($u->locked_until?->isFuture())<span class="badge text-bg-danger">Locked</span>@endif<div class="small">{{ $u->is_email_verified?'Verified':'Unverified' }} · {{ $u->failed_login_count }} failed</div></td><td>{{ $u->last_login_at?->format('Y-m-d H:i') ?: 'Never' }}</td><td>{{ $u->created_at?->format('Y-m-d') }}</td><td class="text-nowrap"><div class="d-flex flex-wrap gap-1"><a class="btn btn-sm user-action user-action-edit" href="{{ route('admin.users.edit',$u) }}">Edit</a>
@if($u->is_active)<form method="POST" action="{{ route('admin.users.deactivate',$u) }}">@csrf<button class="btn btn-sm user-action user-action-deactivate" onclick="return confirm('Deactivate this account?')">Deactivate</button></form>@else<form method="POST" action="{{ route('admin.users.reactivate',$u) }}">@csrf<button class="btn btn-sm user-action user-action-reactivate">Reactivate</button></form>@endif
@if($u->locked_until)<form method="POST" action="{{ route('admin.users.unlock',$u) }}">@csrf<button class="btn btn-sm user-action user-action-unlock">Unlock</button></form>@endif
<form method="POST" action="{{ route('admin.users.reset',$u) }}">@csrf<button class="btn btn-sm user-action user-action-reset">Send password reset</button></form>@if(!$u->is_email_verified)<form method="POST" action="{{ route('admin.users.verification',$u) }}">@csrf<button class="btn btn-sm user-action user-action-verify">Resend verification</button></form>@endif</div></td></tr>@empty<tr><td colspan="7" class="text-center text-secondary p-4">No users found.</td></tr>@endforelse</tbody></table></div><div class="mt-3">{{ $users->links('pagination::bootstrap-5') }}</div>
<script>
  document.querySelectorAll('#userFilters select').forEach(select => select.addEventListener('change', () => document.getElementById('userFilters').requestSubmit()));
</script>
@endsection
