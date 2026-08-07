<?php

use App\Models\SalesInvoiceItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->string('product_name')->nullable()->after('product_variant_id');
            $table->string('color_name')->nullable()->after('product_name');
            $table->string('size_name')->nullable()->after('color_name');
            $table->string('sku')->nullable()->after('size_name');
            $table->string('barcode')->nullable()->after('sku');
        });

        SalesInvoiceItem::query()
            ->with(['productVariant.product', 'productVariant.color', 'productVariant.size'])
            ->each(function (SalesInvoiceItem $item) {
                $variant = $item->productVariant;

                if (! $variant) {
                    return;
                }

                $item->update([
                    'product_name' => $variant->product?->name,
                    'color_name' => $variant->color?->name,
                    'size_name' => $variant->size?->name,
                    'sku' => $variant->sku,
                    'barcode' => $variant->barcode,
                ]);
            });
    }

    public function down(): void
    {
        Schema::table('sales_invoice_items', function (Blueprint $table) {
            $table->dropColumn(['product_name', 'color_name', 'size_name', 'sku', 'barcode']);
        });
    }
};
