<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderMaterial extends Pivot
{
    protected $table = 'order_material';
    protected $fillable = ['order_id', 'material_id', 'unit_price', 'quantity', 'is_adjustment', 'item_note'];
}