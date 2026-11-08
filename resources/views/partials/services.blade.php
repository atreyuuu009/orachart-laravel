{{-- Price guide: one card per category, details open in a shared modal. Needs $categories. --}}
<div class="row g-3">
  @foreach ($categories as $c)
    @php
      $priced = $c->services->where('is_addon', false)->where('price_min_amount', '>', 0);
      $from = $priced->min('price_min_amount');
    @endphp
    <div class="col-sm-6 col-lg-4">
      <div class="card service-card h-100">
        <div class="card-body d-flex flex-column">
          <h5 class="card-title">{{ $c->category_name }}</h5>
          <p class="text-secondary small mb-2">{{ $c->services->where('is_addon', false)->count() }} option(s)</p>
          <div class="fw-bold text-primary mb-3">{{ $from ? 'From '.\App\Models\Service::peso($from) : 'Price on consultation' }}</div>
          <button type="button" class="btn btn-outline-primary btn-sm mt-auto" data-service-cat="{{ $c->category_id }}">View details &amp; prices</button>
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
