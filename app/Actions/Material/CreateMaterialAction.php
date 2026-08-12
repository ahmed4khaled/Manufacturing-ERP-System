<?php

namespace App\Actions\Material;

use App\Enums\MaterialStatus;
use App\Models\Material;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreateMaterialAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    public function handle(array $data): Material
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = MaterialStatus::ACTIVE;
            $data['quantity'] = 0;

            $material = Material::create($data);

            $material->update([
                'code' => $this->codeGenerator->generate('MAT', $material->id),
            ]);

            return $material->fresh();
        });
    }
}
