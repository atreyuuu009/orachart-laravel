@if (session('status'))
  <div class="alert alert-success alert-dismissible fade show" role="alert">{{ session('status') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
@endif
@if ($errors->any())
  <div class="alert alert-danger alert-dismissible fade show" role="alert">
    @if ($errors->count() === 1) {{ $errors->first() }}
    @else <ul class="mb-0 ps-3">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    @endif
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
@endif
