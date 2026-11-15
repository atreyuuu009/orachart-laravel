@extends('layouts.app')
@section('title', 'Logs')
@section('page_title', 'Audit & E-mail Logs')
@section('page_subtitle', 'Who did what, and every e-mail the system sent or queued.')
@section('content')
<ul class="nav nav-tabs mb-3"><li class="nav-item"><a class="nav-link {{ $tab === 'audit' ? 'active' : '' }}" href="{{ route('admin.logs') }}">Audit trail</a></li>
  <li class="nav-item"><a class="nav-link {{ $tab === 'email' ? 'active' : '' }}" href="{{ route('admin.logs', ['tab' => 'email']) }}">E-mail log</a></li></ul>
@if($tab==='audit')<form class="card card-body mb-3"><div class="row g-2"><div class="col-md"><select class="form-select" name="category"><option value="">All categories</option>@foreach(['payments'=>'Payments','appointments'=>'Appointments','odontogram'=>'Odontogram','users'=>'Users','settings'=>'Settings'] as $k=>$label)<option value="{{ $k }}" @selected(request('category')===$k)>{{ $label }}</option>@endforeach</select></div><div class="col-md"><select class="form-select" name="kind"><option value="">All events</option><option value="logins" @selected(request('kind')==='logins')>Logins</option><option value="changes" @selected(request('kind')==='changes')>Changes</option></select></div><div class="col-md"><select class="form-select" name="action_type"><option value="">All action types</option>@foreach($actions as $a)<option @selected(request('action_type')===$a)>{{ $a }}</option>@endforeach</select></div><div class="col-md"><select class="form-select" name="user_id"><option value="">All users</option>@foreach($users as $u)<option value="{{ $u->user_id }}" @selected(request('user_id')==$u->user_id)>{{ $u->full_name }}</option>@endforeach</select></div><div class="col-md"><input type="date" class="form-control" name="date_from" value="{{ request('date_from') }}" aria-label="From date"></div><div class="col-md"><input type="date" class="form-control" name="date_to" value="{{ request('date_to') }}" aria-label="To date"></div><div class="col-auto"><button class="btn btn-primary">Filter</button> <a class="btn btn-outline-secondary" href="{{ route('admin.logs') }}">Clear</a></div></div></form>@endif
<div class="card"><div class="table-responsive"><table class="table table-sm mb-0">
@if ($tab === 'audit')
  <thead><tr><th>When</th><th>User</th><th>Action</th><th>Table / record</th><th>Description</th><th>IP</th></tr></thead><tbody>
  @forelse ($audit as $l)<tr><td class="text-nowrap">{{ $l->created_at }}</td><td>{{ $l->user?->full_name ?? '—' }}</td><td><span class="badge text-bg-secondary">{{ $l->action_type }}</span></td>
    <td>{{ $l->table_affected }} {{ $l->record_id ? '#'.$l->record_id : '' }}</td><td>{{ $l->description }}</td><td>{{ $l->ip_address }}</td></tr>
  @empty<tr><td colspan="6" class="text-center text-secondary py-4">No entries.</td></tr>@endforelse
@else
  <thead><tr><th>Created</th><th>To</th><th>Subject</th><th>Status</th><th>Scheduled</th><th>Sent</th><th>Error</th></tr></thead><tbody>
  @forelse ($emails as $m)<tr><td class="text-nowrap">{{ $m->created_at }}</td><td>{{ $m->recipient_email }}</td><td>{{ $m->subject }}</td>
    <td><span class="badge text-bg-{{ ['sent' => 'success', 'queued' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary'][$m->status] ?? 'secondary' }}">{{ $m->status }}</span></td>
    <td>{{ $m->scheduled_for }}</td><td>{{ $m->sent_at }}</td><td class="small text-danger">{{ $m->error_message }}</td></tr>
  @empty<tr><td colspan="7" class="text-center text-secondary py-4">No e-mails yet.</td></tr>@endforelse
@endif
</tbody></table></div></div>
<div class="mt-3">{{ ($tab === 'audit' ? $audit : $emails)->links('pagination::bootstrap-5') }}</div>
@endsection
