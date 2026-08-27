<?php

namespace App\Actions\ManufacturingOperation;

use App\Models\ManufacturingOperation;
use Illuminate\Support\Facades\DB;

class DeleteManufacturingOperationAction
{
    public function handle(ManufacturingOperation $operation): void
    {
        DB::transaction(fn () => $operation->delete());
    }
}
