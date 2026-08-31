<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_payments', 'status')) {
            Schema::table('supplier_payments', function (Blueprint $table): void {
                $table->string('status')->default('confirmed')->after('notes')->index();
            });
        }

        if (! Schema::hasTable('supplier_payment_allocations')) {
            Schema::create('supplier_payment_allocations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('supplier_payment_id')->constrained()->restrictOnDelete();
                $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
                $table->decimal('amount', 14, 2);
                $table->timestamps();

                $table->unique(['supplier_payment_id', 'supplier_invoice_id'], 'supplier_payment_invoice_unique');
                $table->index(['supplier_invoice_id', 'supplier_payment_id'], 'supplier_invoice_payment_index');
            });

            DB::table('supplier_payments')
                ->whereNotNull('supplier_invoice_id')
                ->select(['id', 'supplier_invoice_id', 'amount', 'created_at', 'updated_at'])
                ->orderBy('id')
                ->chunkById(500, function ($payments): void {
                    DB::table('supplier_payment_allocations')->insertOrIgnore(
                        $payments->map(fn ($payment): array => [
                            'supplier_payment_id' => $payment->id,
                            'supplier_invoice_id' => $payment->supplier_invoice_id,
                            'amount' => $payment->amount,
                            'created_at' => $payment->created_at,
                            'updated_at' => $payment->updated_at,
                        ])->all()
                    );
                });
        }
    }

    public function down(): void
    {
        // Payment status and allocation history are intentionally retained.
    }
};
