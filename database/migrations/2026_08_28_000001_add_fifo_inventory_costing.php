<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('material_cost_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('material_stock_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->date('layer_date');
            $table->decimal('original_quantity', 14, 3);
            $table->decimal('remaining_quantity', 14, 3);
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('total_original_cost', 18, 2)->nullable();
            $table->timestamps();
            $table->index(['material_id', 'remaining_quantity', 'layer_date', 'id'], 'material_fifo_lookup');
        });

        Schema::create('material_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('material_cost_layer_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_stock_movement_id')->constrained()->restrictOnDelete();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('direction', 10);
            $table->foreignId('source_allocation_id')->nullable()->constrained('material_cost_allocations')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('restored_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['material_stock_movement_id', 'material_cost_layer_id', 'direction'], 'material_cost_posting_unique');
            $table->index(['reference_type', 'reference_id', 'direction'], 'material_cost_reference_index');
        });

        Schema::create('product_cost_layers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_output_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->date('layer_date');
            $table->decimal('original_quantity', 14, 3);
            $table->decimal('remaining_quantity', 14, 3);
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('total_original_cost', 18, 2)->nullable();
            $table->boolean('is_final')->default(false);
            $table->timestamps();
            $table->index(['product_variant_id', 'is_final', 'remaining_quantity', 'layer_date', 'id'], 'product_fifo_lookup');
        });

        Schema::create('product_cost_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_cost_layer_id')->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->constrained()->restrictOnDelete();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('direction', 10);
            $table->foreignId('source_allocation_id')->nullable()->constrained('product_cost_allocations')->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('restored_quantity', 14, 3)->default(0);
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('total_cost', 18, 2)->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
            $table->unique(['stock_movement_id', 'product_cost_layer_id', 'direction'], 'product_cost_posting_unique');
            $table->index(['reference_type', 'reference_id', 'direction'], 'product_cost_reference_index');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('standard_cost', 16, 4)->nullable()->after('default_price');
            $table->decimal('actual_cost', 16, 4)->nullable()->after('standard_cost');
            $table->decimal('last_production_cost', 16, 4)->nullable()->after('actual_cost');
        });

        Schema::table('production_material_transactions', function (Blueprint $table): void {
            $table->decimal('actual_cost', 18, 2)->nullable()->after('quantity');
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->decimal('final_unit_cost', 16, 4)->nullable()->change();
            $table->decimal('standard_material_cost', 18, 2)->default(0)->after('estimated_total_cost');
            $table->decimal('actual_material_cost', 18, 2)->nullable()->after('standard_material_cost');
            $table->decimal('standard_operation_cost', 18, 2)->default(0)->after('actual_material_cost');
            $table->decimal('actual_operation_cost', 18, 2)->nullable()->after('standard_operation_cost');
            $table->decimal('manufacturing_overhead_cost', 18, 2)->default(0)->after('actual_operation_cost');
            $table->decimal('cost_variance', 18, 2)->nullable()->after('manufacturing_overhead_cost');
        });

        Schema::table('production_outputs', function (Blueprint $table): void {
            $table->decimal('unit_cost', 16, 4)->default(0)->change();
        });

        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->decimal('total_cogs', 18, 2)->nullable()->after('total_amount');
        });

        Schema::table('sales_returns', function (Blueprint $table): void {
            $table->decimal('total_cogs', 18, 2)->nullable()->after('total_amount');
        });
    }

    public function down(): void
    {
        // Cost history is append-only and is intentionally retained on rollback.
    }
};
