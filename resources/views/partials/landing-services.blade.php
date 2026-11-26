{{-- Landing page price guide: one glass card per service category. --}}
@php
  $serviceIcons = [
    'dentures' => 'bi-align-bottom', 'braces' => 'bi-braces',
    'tooth extraction' => 'bi-bandaid', 'crowns & bridges' => 'bi-gem',
    'fillings' => 'bi-plus-square', 'cleaning' => 'bi-stars',
    'veneers' => 'bi-emoji-smile', 'root canal' => 'bi-activity',
    'whitening' => 'bi-brightness-high', 'sealants' => 'bi-shield-check',
    'gum treatment' => 'bi-heart-pulse', 'inlay / onlay' => 'bi-nut',
    'consultation' => 'bi-chat-heart',
  ];
@endphp
<div class="service-grid">
  @foreach ($categories as $c)
    @php
      $priced = $c->services->where('is_addon', false)->where('price_min_amount', '>', 0);
      $from = $priced->min('price_min_amount');
      $categoryKey = mb_strtolower($c->category_name);
      $icon = $serviceIcons[$categoryKey] ?? 'bi-heart-pulse';
    @endphp
    <article class="service-card service-card--glass">
      <div class="service-card-icon" aria-hidden="true"><i class="bi {{ $icon }}"></i></div>
      <div class="service-card-copy">
        <h3>{{ $c->category_name }}</h3>
        <p>{{ $from ? 'From '.\App\Models\Service::peso($from) : 'Price on consultation' }}</p>
      </div>
      <button class="service-card-arrow" type="button" data-service-cat="{{ $c->category_id }}" aria-label="View {{ $c->category_name }} details and prices">
        <i class="bi bi-arrow-right" aria-hidden="true"></i>
      </button>
    </article>
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
    <div class="modal-header border-0 pb-0"><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body" id="serviceModalBody"></div>
  </div></div>
</div>
