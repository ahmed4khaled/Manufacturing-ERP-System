<?php

namespace App\Services;

use App\Models\ProductionOrder;
use App\Models\ProductionOrderActivity;

class ProductionActivityService
{
    public function record(ProductionOrder $order, string $action, string $description, array $metadata = []): ProductionOrderActivity
    {
        return $order->activities()->create(['action' => $action, 
        'description' => $description, 'metadata' => $metadata ?: null,
         'user_id' => auth()->id()]);
    }
}
