<?php

namespace App\Actions\MaterialInventory;

use App\Models\MaterialStockMovement;
use App\Services\MaterialStockService;
use Illuminate\Support\Facades\DB;

class CreateMaterialOpeningStockAction
{
    public function __construct(
        private readonly MaterialStockService $materialStockService,
    ) {}

    public function handle(array $data): MaterialStockMovement
    {
        return DB::transaction(fn () => $this->materialStockService->createOpeningStock($data + [
            'user_id' => auth()->id(),
        ]));
    }
}
