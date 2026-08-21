<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->decimal('assigned_quantity', 14, 3);
            $table->decimal('produced_quantity', 14, 3)->default(0);
            $table->decimal('good_quantity', 14, 3)->default(0);
            $table->decimal('defective_quantity', 14, 3)->default(0);
            $table->string('status')->default('assigned')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['production_order_operation_id', 'worker_id']);
            $table->index(['production_order_id', 'status']);
        });

        Schema::create('work_assignment_materials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('work_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('production_order_material_id')->constrained(indexName: 'wa_material_requirement_fk')->restrictOnDelete();
            $table->decimal('standard_quantity', 14, 3)->default(0);
            $table->decimal('allocated_quantity', 14, 3);
            $table->boolean('is_overridden')->default(false);
            $table->text('override_reason')->nullable();
            $table->foreignId('allocated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['work_assignment_id', 'production_order_material_id'], 'assignment_material_unique');
        });

        Schema::create('production_material_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_assignment_material_id')->constrained(indexName: 'pmt_assignment_material_fk')->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->string('type')->index();
            $table->decimal('quantity', 14, 3);
            $table->string('status')->default('recorded')->index();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['work_assignment_material_id', 'type'], 'pmt_assignment_type_index');
            $table->index(['production_order_id', 'occurred_at'], 'pmt_order_occurred_index');
        });

        Schema::create('production_defects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('work_assignment_id')->constrained()->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_order_operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->string('reason');
            $table->string('status')->default('pending_review')->index();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'status']);
        });

        Schema::create('production_reworks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_defect_id')->constrained()->restrictOnDelete();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('source_work_assignment_id')->constrained('work_assignments')->restrictOnDelete();
            $table->foreignId('production_order_operation_id')->constrained()->restrictOnDelete();
            $table->foreignId('worker_id')->constrained()->restrictOnDelete();
            $table->decimal('assigned_quantity', 14, 3);
            $table->decimal('good_quantity', 14, 3)->default(0);
            $table->decimal('defective_quantity', 14, 3)->default(0);
            $table->string('status')->default('assigned')->index();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'status']);
        });

        Schema::create('production_outputs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->date('output_date');
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();

            $table->index(['production_order_id', 'output_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_outputs');
        Schema::dropIfExists('production_reworks');
        Schema::dropIfExists('production_defects');
        Schema::dropIfExists('production_material_transactions');
        Schema::dropIfExists('work_assignment_materials');
        Schema::dropIfExists('work_assignments');
    }
};
