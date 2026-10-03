<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\OrderArchive;

class OrderObserver
{
    public function __construct(private OrderArchive $archive) {}

    public function saved(Order $order): void
    {
        $this->archive->sync($order);
    }

    public function deleted(Order $order): void
    {
        $this->archive->forget($order);
    }
}
