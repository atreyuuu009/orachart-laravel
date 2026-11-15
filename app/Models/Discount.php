<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Discount extends Model
{
    protected $table = 'pay_discounts';
    protected $primaryKey = 'discount_id';
    public $timestamps = false;
    protected $guarded = [];
}
