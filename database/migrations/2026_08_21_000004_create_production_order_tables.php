<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->foreignId('bom_id')->constrained()->restrictOnDelete();
            $table->string('product_name');
            $table->string('color_name')->nullable();
            $table->string('size_name')->nullable();
            $table->string('sku');
            $table->string('bom_number');
            $table->unsignedInteger('bom_version');
            $table->decimal('planned_quantity', 14, 3);
            $table->date('planned_start_date');
            $table->date('planned_completion_date')->nullable();
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('held_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('held_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['product_variant_id', 'status']);
            $table->index(['planned_start_date', 'status']);
        });

        Schema::create('production_order_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('material_code')->nullable();
            $table->string('material_name');
            $table->string('unit');
            $table->decimal('standard_quantity', 14, 3);
            $table->decimal('required_quantity', 14, 3);
            $table->boolean('is_overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['production_order_id', 'material_id'], 'po_material_unique');
        });

        Schema::create('production_order_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bom_operation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('manufacturing_operation_id')->constrained()->restrictOnDelete();
            $table->string('operation_name');
            $table->unsignedInteger('sequence');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['production_order_id', 'manufacturing_operation_id'], 'po_operation_definition_unique');
            $table->unique(['production_order_id', 'sequence'], 'po_operation_sequence_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_operations');
        Schema::dropIfExists('production_order_materials');
        Schema::dropIfExists('production_orders');
    }
};
