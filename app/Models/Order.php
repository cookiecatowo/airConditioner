<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'customer_id', 'address', 'tax_id', 'public_notes', 'date', 'type', 'total_amount', 'notes', 'processing_status', 'payment_status'
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function equipments()
    {
        return $this->belongsToMany(Equipment::class, 'order_equipment')
                    ->withPivot('cost_price', 'sale_price', 'quantity', 'unit', 'is_adjustment', 'item_note')
                    ->withTimestamps();
    }

    public function materials()
    {
        return $this->belongsToMany(Material::class, 'order_material')
                    ->withPivot('unit_price', 'quantity', 'is_adjustment', 'item_note')
                    ->withTimestamps();
    }
}