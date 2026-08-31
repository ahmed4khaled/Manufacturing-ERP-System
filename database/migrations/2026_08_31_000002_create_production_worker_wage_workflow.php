<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('production_worker_wages')) {
            Schema::create('production_worker_wages', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('worker_id')->constrained()->restrictOnDelete();
                $table->foreignId('production_order_id')->constrained()->restrictOnDelete();
                $table->foreignId('work_assignment_id')->unique()->constrained()->restrictOnDelete();
                $table->foreignId('production_order_operation_id')->constrained()->restrictOnDelete();
                $table->decimal('quantity', 14, 3);
                $table->decimal('unit_rate', 18, 4);
                $table->decimal('gross_amount', 18, 2);
                $table->decimal('paid_amount', 18, 2)->default(0);
                $table->decimal('remaining_amount', 18, 2);
                $table->string('status', 30)->default('unpaid')->index();
                $table->date('earned_at')->index();
                $table->timestamps();
                $table->index(['worker_id', 'earned_at']);
                $table->index(['production_order_id', 'production_order_operation_id'], 'worker_wages_order_operation_index');
            });
        }

        if (! Schema::hasTable('worker_wage_payments')) {
            Schema::create('worker_wage_payments', function (Blueprint $table): void {
                $table->id();
                $table->string('payment_number', 40)->unique();
                $table->foreignId('worker_id')->constrained()->restrictOnDelete();
                $table->date('payment_date')->index();
                $table->foreignId('payment_account_id')->constrained('accounts')->restrictOnDelete();
                $table->decimal('amount', 18, 2);
                $table->text('description');
                $table->string('reference')->nullable();
                $table->uuid('submission_token')->nullable()->unique();
                $table->string('status', 20)->default('draft')->index();
                $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
                $table->foreignId('reversal_journal_entry_id')->nullable()->unique()->constrained('journal_entries')->restrictOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('posted_at')->nullable();
                $table->timestamp('reversed_at')->nullable();
                $table->timestamps();
                $table->index(['worker_id', 'payment_date']);
            });
        }

        if (! Schema::hasTable('worker_wage_payment_allocations')) {
            Schema::create('worker_wage_payment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('worker_wage_payment_id');
                $table->foreignId('production_worker_wage_id');
                $table->decimal('amount', 18, 2);
                $table->timestamp('reversed_at')->nullable()->index('wwpa_reversed_idx');
                $table->timestamps();
                $table->index('worker_wage_payment_id', 'wwpa_payment_idx');
                $table->index('production_worker_wage_id', 'wwpa_wage_idx');
                $table->unique(['worker_wage_payment_id', 'production_worker_wage_id'], 'wwpa_payment_wage_unique');
                $table->foreign('worker_wage_payment_id', 'wwpa_payment_fk')->references('id')->on('worker_wage_payments')->restrictOnDelete();
                $table->foreign('production_worker_wage_id', 'wwpa_wage_fk')->references('id')->on('production_worker_wages')->restrictOnDelete();
            });
        } else {
            $indexes = collect(Schema::getIndexes('worker_wage_payment_allocations'))->pluck('name');
            $foreignKeys = collect(Schema::getForeignKeys('worker_wage_payment_allocations'))->pluck('name');

            Schema::table('worker_wage_payment_allocations', function (Blueprint $table) use ($indexes, $foreignKeys): void {
                if (! $indexes->contains('wwpa_reversed_idx')) {
                    $table->index('reversed_at', 'wwpa_reversed_idx');
                }
                if (! $indexes->contains('wwpa_wage_idx')) {
                    $table->index('production_worker_wage_id', 'wwpa_wage_idx');
                }
                if (! $indexes->contains('worker_wage_payment_allocation_unique') && ! $indexes->contains('wwpa_payment_wage_unique')) {
                    $table->unique(['worker_wage_payment_id', 'production_worker_wage_id'], 'wwpa_payment_wage_unique');
                }
                if (! $foreignKeys->contains('wwpa_wage_fk')) {
                    $table->foreign('production_worker_wage_id', 'wwpa_wage_fk')->references('id')->on('production_worker_wages')->restrictOnDelete();
                }
            });
        }

        if (! Schema::hasColumn('journal_entry_lines', 'worker_id')) {
            Schema::table('journal_entry_lines', function (Blueprint $table): void {
                $table->foreignId('worker_id')->nullable()->after('partner_id')->constrained()->restrictOnDelete();
                $table->index(['worker_id', 'account_id']);
            });
        }
    }

    public function down(): void
    {
        // Production wage and accounting history is append-only; rollback never removes it.
    }
};
