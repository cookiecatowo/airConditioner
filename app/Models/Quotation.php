<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Quotation extends Model
{
    // 龍哥，這行最重要，沒加這行電腦會鎖住不讓你存資料
    protected $fillable = [
        'customer_name',
        'customer_phone',
        'install_address',
        'ac_model',
        'price',
        'quantity',
        'total_amount',
        'notes',
    ];
}