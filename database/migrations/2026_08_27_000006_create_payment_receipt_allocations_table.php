<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_receipt_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_receipt_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->unique(['payment_receipt_id', 'sales_invoice_id'], 'receipt_invoice_allocation_unique');
            $table->index(['sales_invoice_id', 'payment_receipt_id'], 'invoice_receipt_allocation_index');
        });

        DB::table('payment_receipts')
            ->whereNotNull('sales_invoice_id')
            ->select(['id', 'sales_invoice_id', 'amount', 'created_at', 'updated_at'])
            ->orderBy('id')
            ->chunkById(500, function ($receipts): void {
                $rows = $receipts->map(fn ($receipt): array => [
                    'payment_receipt_id' => $receipt->id,
                    'sales_invoice_id' => $receipt->sales_invoice_id,
                    'amount' => $receipt->amount,
                    'created_at' => $receipt->created_at,
                    'updated_at' => $receipt->updated_at,
                ])->all();

                DB::table('payment_receipt_allocations')->insertOrIgnore($rows);
            });
    }

    public function down(): void
    {
        // Intentionally non-destructive: allocation history must survive rollback.
    }
};
