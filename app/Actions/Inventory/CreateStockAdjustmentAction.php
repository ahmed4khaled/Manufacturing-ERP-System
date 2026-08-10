<?php

namespace App\Actions\Inventory;

use App\Models\StockMovement;
use App\Services\StockService;
use Illuminate\Support\Facades\DB;

class CreateStockAdjustmentAction
{
    public function __construct(
        private readonly StockService $stockService,
    ) {}

    public function handle(array $data): StockMovement
    {
        return DB::transaction(fn () => $this->stockService->createAdjustment($data + [
            'user_id' => auth()->id(),
        ]));
    }
}
