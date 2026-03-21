<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id', 'address', 'date', 'type', 'total_amount', 'notes'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function equipments()
    {
        return $this->belongsToMany(Equipment::class, 'order_equipment')
                    ->withPivot('cost_price', 'sale_price', 'quantity')
                    ->withTimestamps();
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'order_material')
                    ->withPivot('unit_price', 'quantity')
                    ->withTimestamps();
    }
}