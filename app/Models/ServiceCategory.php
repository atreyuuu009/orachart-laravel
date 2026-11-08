<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCategory extends Model
{
    protected $table = 'svc_categories';
    protected $primaryKey = 'category_id';
    public $timestamps = false;   // created_at / updated_at are handled by MySQL defaults
    protected $guarded = [];

    public function services() { return $this->hasMany(Service::class, 'category_id'); }
}
