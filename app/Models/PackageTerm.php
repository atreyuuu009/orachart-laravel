<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageTerm extends Model
{
    protected $table = 'svc_package_terms';
    protected $primaryKey = 'package_term_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];
    protected $casts = ['inclusions' => 'array', 'downpayment_amount' => 'float', 'monthly_adjustment_amount' => 'float'];
}
