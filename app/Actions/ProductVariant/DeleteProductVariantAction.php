<?php

namespace App\Actions\ProductVariant;

use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class DeleteProductVariantAction
{
    public function handle(ProductVariant $productVariant): void
    {
        DB::transaction(function () use ($productVariant) {
            $productVariant->delete();
        });
    }
}
