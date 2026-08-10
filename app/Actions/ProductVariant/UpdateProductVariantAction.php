<?php

namespace App\Actions\ProductVariant;

use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\VariantSkuGeneratorService;
use Illuminate\Support\Facades\DB;

class UpdateProductVariantAction
{
    public function __construct(
        private readonly VariantSkuGeneratorService $skuGenerator,
    ) {}

    public function handle(ProductVariant $productVariant, array $data): ProductVariant
    {
        return DB::transaction(function () use ($productVariant, $data) {
            unset($data['sku'], $data['barcode'], $data['quantity']);

            $combinationChanged = isset($data['product_id'], $data['color_id'], $data['size_id'])
                && (
                    (int) $data['product_id'] !== (int) $productVariant->product_id
                    || (int) $data['color_id'] !== (int) $productVariant->color_id
                    || (int) $data['size_id'] !== (int) $productVariant->size_id
                );

            if ($combinationChanged) {
                $product = Product::query()->findOrFail($data['product_id']);
                $color = Color::query()->findOrFail($data['color_id']);
                $size = Size::query()->findOrFail($data['size_id']);

                $data['sku'] = $this->skuGenerator->generate($product, $color, $size);
            }

            $productVariant->update($data);

            return $productVariant->fresh();
        });
    }
}
