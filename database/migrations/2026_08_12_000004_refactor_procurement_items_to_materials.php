<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropUnique(['purchase_order_id', 'product_variant_id']);
            $table->dropColumn('product_variant_id');
            $table->foreignId('material_id')->after('purchase_order_id')->constrained()->restrictOnDelete();
            $table->unique(['purchase_order_id', 'material_id']);
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
            $table->foreignId('material_id')->after('purchase_order_item_id')->constrained()->restrictOnDelete();
        });

        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropForeign(['product_variant_id']);
            $table->dropColumn('product_variant_id');
            $table->foreignId('material_id')->after('purchase_return_id')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_return_items', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropColumn('material_id');
            $table->foreignId('product_variant_id')->after('purchase_return_id')->constrained()->restrictOnDelete();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropColumn('material_id');
            $table->foreignId('product_variant_id')->after('purchase_order_item_id')->constrained()->restrictOnDelete();
        });

        Schema::table('purchase_order_items', function (Blueprint $table) {
            $table->dropForeign(['material_id']);
            $table->dropUnique(['purchase_order_id', 'material_id']);
            $table->dropColumn('material_id');
            $table->foreignId('product_variant_id')->after('purchase_order_id')->constrained()->restrictOnDelete();
            $table->unique(['purchase_order_id', 'product_variant_id']);
        });
    }
};
