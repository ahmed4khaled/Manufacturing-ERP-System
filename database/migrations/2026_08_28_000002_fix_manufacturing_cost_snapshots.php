<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('materials', function (Blueprint $table) {
            $table->decimal('standard_unit_cost', 16, 4)->nullable()->after('quantity');
        });

        Schema::table('bom_items', function (Blueprint $table) {
            $table->decimal('standard_unit_cost', 16, 4)->nullable()->after('quantity');
        });

        Schema::table('production_order_materials', function (Blueprint $table) {
            $table->decimal('standard_unit_cost', 16, 4)->nullable()->after('required_quantity');
            $table->decimal('standard_total_cost', 18, 2)->nullable()->after('standard_unit_cost');
        });

        Schema::table('production_orders', function (Blueprint $table) {
            $table->string('standard_material_cost_source')->default('product_base_fallback')->after('standard_material_cost');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropColumn('standard_material_cost_source');
        });

        Schema::table('production_order_materials', function (Blueprint $table) {
            $table->dropColumn(['standard_unit_cost', 'standard_total_cost']);
        });

        Schema::table('bom_items', function (Blueprint $table) {
            $table->dropColumn('standard_unit_cost');
        });

        Schema::table('materials', function (Blueprint $table) {
            $table->dropColumn('standard_unit_cost');
        });
    }
};
