<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutover_batches', function (Blueprint $table): void {
            $table->id();
            $table->string('batch_number', 40)->unique();
            $table->string('reference', 120)->unique();
            $table->date('cutover_date')->index();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('reversal_journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('cutover_opening_balances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cutover_batch_id')->constrained()->restrictOnDelete();
            $table->string('type', 40)->index();
            $table->foreignId('account_id')->nullable()->constrained('accounts')->restrictOnDelete();
            $table->foreignId('material_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('production_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('worker_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('partner_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('partner_balance_type', 30)->nullable();
            $table->decimal('quantity', 14, 3)->nullable();
            $table->decimal('unit_cost', 16, 4)->nullable();
            $table->decimal('amount', 18, 2);
            $table->string('layer_reference', 120);
            $table->text('description')->nullable();
            $table->foreignId('material_stock_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('stock_movement_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('material_cost_layer_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('product_cost_layer_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('production_worker_wage_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['cutover_batch_id', 'layer_reference'], 'cutover_layer_reference_unique');
            $table->index(['cutover_batch_id', 'type'], 'cutover_batch_type_index');
        });

        Schema::table('production_orders', function (Blueprint $table): void {
            $table->decimal('opening_wip_cost', 18, 2)->default(0)->after('manufacturing_overhead_cost');
        });

        Schema::table('production_worker_wages', function (Blueprint $table): void {
            $table->foreignId('cutover_opening_balance_id')->nullable()->unique()->after('id')->constrained('cutover_opening_balances')->restrictOnDelete();
            $table->foreignId('production_order_id')->nullable()->change();
            $table->foreignId('work_assignment_id')->nullable()->change();
            $table->foreignId('production_order_operation_id')->nullable()->change();
            $table->timestamp('reversed_at')->nullable()->after('earned_at')->index();
        });
    }

    public function down(): void
    {
        // Cutover and opening history is append-only. Rollback never destroys it.
    }
};
