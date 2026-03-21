<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Equipment extends Model
{
    protected $table = 'equipments';
    
    protected $fillable = [
        'brand_id', 'model_name', 'specs', 'default_cost_price', 'default_sale_price'
    ];

    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}