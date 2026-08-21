<?php

namespace App\Actions\ProductionMaterial;

use App\Enums\ProductionMaterialTransactionType;
use App\Models\ProductionMaterialTransaction;
use App\Models\WorkAssignmentMaterial;
use App\Services\ProductionMaterialService;

class IssueProductionMaterialsAction
{
    public function __construct(private readonly ProductionMaterialService $service) {}

    public function handle(WorkAssignmentMaterial $allocation, array $data): ProductionMaterialTransaction
    {
        return $this->service->record($allocation, ProductionMaterialTransactionType::ISSUE, $data);
    }
}
