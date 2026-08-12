<?php

use App\Models\ProductVariant;
use App\Services\VariantBarcodeGeneratorService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('barcode')->nullable(false)->change();
        });

        $generator = app(VariantBarcodeGeneratorService::class);

        ProductVariant::query()
            ->where(function ($query) {
                $query->whereNull('barcode')->orWhere('barcode', '');
            })
            ->each(function (ProductVariant $variant) use ($generator) {
                do {
                    $barcode = $generator->generate();
                } while (ProductVariant::query()->where('barcode', $barcode)->exists());

                $variant->update(['barcode' => $barcode]);
            });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('barcode')->nullable()->change();
        });
    }
};
