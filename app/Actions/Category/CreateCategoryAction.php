<?php

namespace App\Actions\Category;

use App\Enums\CategoryStatus;
use App\Models\Category;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreateCategoryAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    public function handle(array $data): Category
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = CategoryStatus::ACTIVE;

            $category = Category::create($data);

            $category->update([
                'code' => $this->codeGenerator->generate('CAT', $category->id),
            ]);

            return $category->fresh();
        });
    }
}
