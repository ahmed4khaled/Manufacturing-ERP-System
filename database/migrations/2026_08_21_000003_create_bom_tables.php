<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boms', function (Blueprint $table) {
            $table->id();
            $table->string('bom_number');
            $table->unsignedInteger('version');
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('basis_quantity', 14, 3)->default(1);
            $table->string('status')->default('draft')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('activated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['bom_number', 'version']);
            $table->unique(['product_variant_id', 'version']);
            $table->index(['product_id', 'status']);
        });

        Schema::create('bom_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('material_code')->nullable();
            $table->string('material_name');
            $table->string('unit');
            $table->decimal('quantity', 14, 3);
            $table->timestamps();

            $table->unique(['bom_id', 'material_id']);
        });

        Schema::create('bom_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bom_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manufacturing_operation_id')->constrained()->restrictOnDelete();
            $table->string('operation_name');
            $table->unsignedInteger('sequence');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['bom_id', 'manufacturing_operation_id']);
            $table->unique(['bom_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bom_operations');
        Schema::dropIfExists('bom_items');
        Schema::dropIfExists('boms');
    }
};
