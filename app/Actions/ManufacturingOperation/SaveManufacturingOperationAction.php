<?php

namespace App\Actions\ManufacturingOperation;

use App\Models\ManufacturingOperation;
use Illuminate\Support\Facades\DB;

class SaveManufacturingOperationAction
{
    public function handle(array $data, ?ManufacturingOperation $operation = null): ManufacturingOperation
    {
        return DB::transaction(function () use ($data, $operation) {
            if (! $operation) {
                return ManufacturingOperation::create($data);
            }
            $operation = ManufacturingOperation::query()->lockForUpdate()->findOrFail($operation->id);
            $operation->update($data);

            return $operation->fresh();
        });
    }
}
