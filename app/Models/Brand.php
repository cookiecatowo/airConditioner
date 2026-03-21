<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Brand extends Model
{
    protected $fillable = ['name', 'phone'];

    public function equipments()
    {
        return $this->hasMany(Equipment::class);
    }
}