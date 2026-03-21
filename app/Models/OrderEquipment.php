<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class OrderEquipment extends Pivot
{
    protected $table = 'order_equipment';
    protected $fillable = ['order_id', 'equipment_id', 'cost_price', 'sale_price', 'quantity', 'is_adjustment', 'item_note'];
}