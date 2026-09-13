<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = ['name', 'phone', 'tax_id', 'created_date'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /** 這位顧客用過的地址，最近使用的排前面 */
    public function knownAddresses(): array
    {
        return $this->orders()
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->where('address', '!=', '未填寫')
            ->orderByDesc('date')
            ->pluck('address')
            ->unique()
            ->values()
            ->all();
    }
}
