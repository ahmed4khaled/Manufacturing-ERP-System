<?php

namespace App\Actions\ProductVariant;

use App\Enums\VariantStatus;
use App\Models\Color;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Size;
use App\Services\VariantBarcodeGeneratorService;
use App\Services\VariantSkuGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateProductVariantAction
{
    public function __construct(
        private readonly VariantSkuGeneratorService $skuGenerator,
        private readonly VariantBarcodeGeneratorService $barcodeGenerator,
    ) {}

    public function handle(array $data): ProductVariant
    {
        return DB::transaction(function () use ($data) {
            $data['status'] = VariantStatus::ACTIVE;

            unset($data['quantity']);

            $product = Product::query()->findOrFail($data['product_id']);
            $color = Color::query()->findOrFail($data['color_id']);
            $size = Size::query()->findOrFail($data['size_id']);

            $sku = $this->skuGenerator->generate(
                $product,
                $color,
                $size
            );

            if (ProductVariant::query()->where('sku', $sku)->exists()) {
                throw ValidationException::withMessages([
                    'size_id' => 'A variant with this SKU combination already exists.',
                ]);
            }

            $data['sku'] = $sku;

            $data['barcode'] = $this->barcodeGenerator->generate();

            $variant = ProductVariant::create($data);

            return $variant->fresh();
        });
    }
}