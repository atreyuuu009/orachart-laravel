@extends('layouts.app')
@section('title', $mode === 'create' ? 'Add Patient' : 'Edit Patient')
@section('page_title', $mode === 'create' ? 'Add Patient' : 'Edit Patient')
@section('page_subtitle', 'Identity and contact information')
@section('content')
@php $ownProfile=$ownProfile??false; @endphp
<form method="POST" action="{{ $mode === 'create' ? route('patients.store') : ($ownProfile ? route('patients.own.update') : route('patients.update',$patient)) }}" class="card card-body">
@csrf @if($mode === 'edit') @method('PUT') @endif
<h2 class="h6 mb-3">Identity and contact</h2><div class="row g-3">
@foreach([['first_name','First name',true],['middle_name','Middle name',false],['last_name','Last name',true]] as [$key,$label,$required])<div class="col-md-4"><label class="form-label">{{ $label }}</label><input name="{{ $key }}" class="form-control" maxlength="100" value="{{ old($key,$patient->$key) }}" @required($required)></div>@endforeach
<div class="col-md-4"><label class="form-label">Birth date</label><input type="date" name="birth_date" class="form-control" value="{{ old('birth_date',$patient->birth_date?->format('Y-m-d')) }}" max="{{ today()->format('Y-m-d') }}" required></div>
<div class="col-md-4"><label class="form-label">Sex</label><select name="sex" class="form-select"><option value="">Not specified</option>@foreach(['female'=>'Female','male'=>'Male','other'=>'Other'] as $v=>$label)<option value="{{ $v }}" @selected(old('sex',$patient->sex)===$v)>{{ $label }}</option>@endforeach</select></div>
<div class="col-md-4"><label class="form-label">Contact number</label><input name="contact_number" class="form-control" maxlength="20" value="{{ old('contact_number',$patient->contact_number) }}" required></div>
<div class="col-md-6"><label class="form-label">Email</label><input type="email" name="email" class="form-control" maxlength="255" value="{{ old('email',$patient->email) }}"></div>
<div class="col-12"><label class="form-label">Address</label><textarea name="address" class="form-control" rows="2" maxlength="500">{{ old('address',$patient->address) }}</textarea></div>
<h2 class="h6 mt-4 mb-0">Emergency contact</h2>
<div class="col-md-4"><label class="form-label">Name</label><input name="emergency_contact_name" class="form-control" value="{{ old('emergency_contact_name',$patient->emergency_contact_name) }}" maxlength="150"></div>
<div class="col-md-4"><label class="form-label">Relationship</label><input name="emergency_contact_relationship" class="form-control" value="{{ old('emergency_contact_relationship',$patient->emergency_contact_relationship) }}" maxlength="100"></div>
<div class="col-md-4"><label class="form-label">Contact number</label><input name="emergency_contact_number" class="form-control" value="{{ old('emergency_contact_number',$patient->emergency_contact_number) }}" maxlength="20"></div>
<h2 class="h6 mt-4 mb-0">Guardian (if applicable)</h2>
<div class="col-md-6"><label class="form-label">Guardian name</label><input name="guardian_name" class="form-control" value="{{ old('guardian_name',$patient->guardian_name) }}" maxlength="150"></div>
<div class="col-md-6"><label class="form-label">Guardian relationship</label><input name="guardian_relationship" class="form-control" value="{{ old('guardian_relationship',$patient->guardian_relationship) }}" maxlength="100"></div>
</div><div class="d-flex gap-2 mt-4"><button class="btn btn-primary">{{ $mode === 'create' ? 'Create patient' : 'Save changes' }}</button><a class="btn btn-light" href="{{ $ownProfile ? route('patients.own') : ($mode === 'edit' ? route('patients.show',$patient) : route('patients.index')) }}">Cancel</a></div>
</form>
@endsection
