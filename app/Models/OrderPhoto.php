<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class OrderPhoto extends Model
{
    protected $fillable = ['order_id', 'path', 'caption'];

    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return Storage::url($this->path);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
