<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->index(['status', 'invoice_date'], 'sales_status_date_index'));
        Schema::table('payment_receipts', fn (Blueprint $table) => $table->index(['status', 'payment_date'], 'payments_status_date_index'));
        Schema::table('sales_returns', fn (Blueprint $table) => $table->index(['status', 'return_date'], 'sales_returns_status_date_index'));
        Schema::table('supplier_invoices', fn (Blueprint $table) => $table->index('invoice_date', 'supplier_invoices_date_index'));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->index('payment_date', 'supplier_payments_date_index'));
        Schema::table('purchase_returns', fn (Blueprint $table) => $table->index(['status', 'return_date'], 'purchase_returns_status_date_index'));
        Schema::table('production_outputs', fn (Blueprint $table) => $table->index('output_date', 'production_outputs_date_index'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->index('movement_date', 'stock_movements_date_index'));
        Schema::table('material_stock_movements', fn (Blueprint $table) => $table->index('movement_date', 'material_movements_date_index'));
    }

    public function down(): void
    {
        Schema::table('material_stock_movements', fn (Blueprint $table) => $table->dropIndex('material_movements_date_index'));
        Schema::table('stock_movements', fn (Blueprint $table) => $table->dropIndex('stock_movements_date_index'));
        Schema::table('production_outputs', fn (Blueprint $table) => $table->dropIndex('production_outputs_date_index'));
        Schema::table('purchase_returns', fn (Blueprint $table) => $table->dropIndex('purchase_returns_status_date_index'));
        Schema::table('supplier_payments', fn (Blueprint $table) => $table->dropIndex('supplier_payments_date_index'));
        Schema::table('supplier_invoices', fn (Blueprint $table) => $table->dropIndex('supplier_invoices_date_index'));
        Schema::table('sales_returns', fn (Blueprint $table) => $table->dropIndex('sales_returns_status_date_index'));
        Schema::table('payment_receipts', fn (Blueprint $table) => $table->dropIndex('payments_status_date_index'));
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->dropIndex('sales_status_date_index'));
    }
};
