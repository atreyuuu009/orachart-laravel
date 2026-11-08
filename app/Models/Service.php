<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    protected $table = 'svc_services';
    protected $primaryKey = 'service_id';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'price_min_amount' => 'float', 'price_max_amount' => 'float',
        'original_price_amount' => 'float', 'additional_unit_price_amount' => 'float',
        'is_addon' => 'boolean', 'is_bookable' => 'boolean',
        'is_multi_visit' => 'boolean', 'is_active' => 'boolean',
    ];

    public const UNIT_LABELS = [
        'flat' => '', 'per_arch' => '/ arch', 'per_tooth' => '/ tooth', 'per_surface' => '/ surface',
        'per_canal' => '/ canal', 'per_quadrant' => '/ quadrant', 'per_session' => '/ session',
        'per_post' => '/ post', 'per_clasp' => '/ clasp', 'package' => '(package)',
    ];

    public function category() { return $this->belongsTo(ServiceCategory::class, 'category_id'); }
    public function packageTerm() { return $this->hasOne(PackageTerm::class, 'service_id'); }
    public function requiredSpecialty() { return $this->belongsTo(Specialty::class, 'required_specialty_id'); }

    public function scopeBookable($q) { return $q->where('is_active', 1)->where('is_bookable', 1)->where('is_addon', 0); }
    public function scopeListed($q) { return $q->where('is_active', 1); }

    public static function peso(float $v): string
    {
        return '₱'.number_format($v, fmod($v, 1) == 0.0 ? 0 : 2);
    }

    public function getPriceLabelAttribute(): string
    {
        if ($this->price_min_amount <= 0 && ! $this->is_addon) return 'Price on consultation';
        $p = self::peso($this->price_min_amount);
        if ($this->price_max_amount) $p .= ' – '.self::peso($this->price_max_amount);
        return trim($p.' '.(self::UNIT_LABELS[$this->pricing_unit] ?? ''));
    }

    public function getAdditionalLabelAttribute(): ?string
    {
        return $this->additional_unit_price_amount
            ? self::peso($this->additional_unit_price_amount).' each succeeding surface' : null;
    }

    public function getDurationLabelAttribute(): string
    {
        $m = (int) $this->duration_minutes;
        if ($m < 60) return $m.' min';
        $h = intdiv($m, 60); $r = $m % 60;
        return $h.' hr'.($r ? ' '.$r.' min' : '');
    }
}
