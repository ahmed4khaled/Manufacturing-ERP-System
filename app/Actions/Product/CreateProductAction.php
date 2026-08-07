<?php

namespace App\Actions\Product;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\CodeGeneratorService;
use Illuminate\Support\Facades\DB;

class CreateProductAction
{
    public function __construct(
        private readonly CodeGeneratorService $codeGenerator,
    ) {}

    public function handle(array $data): Product
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = ProductStatus::ACTIVE;

            $product = Product::create($data);

            $product->update([
                'code' => $this->codeGenerator->generate('PRD', $product->id),
            ]);

            return $product->fresh();
        });
    }
}
