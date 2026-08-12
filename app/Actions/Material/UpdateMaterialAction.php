<?php

namespace App\Actions\Material;

use App\Models\Material;
use Illuminate\Support\Facades\DB;

class UpdateMaterialAction
{
    public function handle(Material $material, array $data): Material
    {
        return DB::transaction(function () use ($material, $data) {
            unset($data['quantity']);

            $material->update($data);

            return $material->fresh();
        });
    }
}
