<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->foreignId('sales_invoice_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('sales_invoices')
                ->nullOnDelete();

            $table->string('payment_type')
                ->default('invoice')
                ->after('sales_invoice_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_receipts', function (Blueprint $table) {
            $table->dropForeign(['sales_invoice_id']);
            $table->dropColumn([
                'sales_invoice_id',
                'payment_type',
            ]);
        });
    }
};