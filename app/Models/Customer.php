<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'tax_id', 'address', 'created_date'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}