{{-- Price guide: one card per category, details open in a shared modal. Needs $categories. --}}
@php
  $categoryIcons = [
    'dentures' => 'emoji-smile', 'braces' => 'activity', 'tooth extraction' => 'heart-pulse',
    'crowns & bridges' => 'award', 'fillings' => 'wrench', 'cleaning' => 'stars',
    'veneers' => 'brightness-high', 'root canal' => 'diagram-3', 'whitening' => 'sun',
    'sealants' => 'shield', 'gum treatment' => 'droplet', 'inlay / onlay' => 'puzzle',
  ];
@endphp
<div class="row g-3">
  @foreach ($categories as $c)
    @php
      $priced = $c->services->where('is_addon', false)->where('price_min_amount', '>', 0);
      $from = $priced->min('price_min_amount');
      $categoryIcon = $categoryIcons[mb_strtolower($c->category_name)] ?? 'plus';
    @endphp
    <div class="col-6 col-lg-3">
      <div class="card service-card h-100">
        <div class="card-body d-flex flex-column">
          <div class="service-card-top"><span class="service-category-icon"><i class="bi bi-{{ $categoryIcon }}"></i></span><span class="service-option-count">{{ $c->services->where('is_addon', false)->count() }} options</span></div>
          <h3 class="service-card-title">{{ $c->category_name }}</h3>
          <div class="service-card-bottom"><div><span class="service-from">From</span><strong class="service-price">{{ $from ? \App\Models\Service::peso($from) : 'Price on consultation' }}</strong></div>
            <button type="button" class="service-details-btn" aria-label="View details and prices for {{ $c->category_name }}" data-service-cat="{{ $c->category_id }}"><i class="bi bi-arrow-right"></i></button>
          </div>
        </div>
      </div>
    </div>
    <template id="svc-tpl-{{ $c->category_id }}">
      <h5 class="mb-3">{{ $c->category_name }}</h5>
      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead><tr><th>Service</th><th class="text-end">Price</th></tr></thead>
          <tbody>
          @foreach ($c->services as $s)
            <tr>
              <td>{{ $s->service_name }} @if ($s->is_addon)<span class="badge text-bg-secondary">Add-on</span>@endif
                @if ($s->additional_label)<div class="small text-secondary">{{ $s->additional_label }}</div>@endif
                @if ($s->is_bookable)<div class="small text-secondary">About {{ $s->duration_label }}</div>@endif
              </td>
              <td class="text-end text-nowrap">
                @if ($s->original_price_amount)<div class="price-old">{{ \App\Models\Service::peso($s->original_price_amount) }}</div>@endif
                <strong>{{ $s->price_label }}</strong>
              </td>
            </tr>
            @if ($s->packageTerm)
              <tr><td colspan="2" class="bg-light small">
                <strong>Downpayment {{ \App\Models\Service::peso($s->packageTerm->downpayment_amount) }}</strong> ·
                Monthly adjustment {{ \App\Models\Service::peso($s->packageTerm->monthly_adjustment_amount) }}
                @if (!empty($s->packageTerm->inclusions))
                  <ul class="mb-0 mt-1">@foreach ($s->packageTerm->inclusions as $inc)<li>{{ $inc }}</li>@endforeach</ul>
                @endif
              </td></tr>
            @endif
          @endforeach
          </tbody>
        </table>
      </div>
      <p class="small text-secondary mb-0">Prices are indicative and may change after your dentist's assessment.</p>
    </template>
  @endforeach
</div>
<div class="modal fade" id="serviceModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header border-0 pb-0"><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body" id="serviceModalBody"></div>
  </div></div>
</div>
