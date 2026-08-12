<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('product_variants', fn (Blueprint $table) => $table->decimal('quantity', 14, 3)->default(0)->change());
        Schema::table('stock_movements', fn (Blueprint $table) => $table->decimal('quantity', 14, 3)->change());
        Schema::table('sales_invoice_items', fn (Blueprint $table) => $table->decimal('quantity', 14, 3)->change());
        Schema::table('sales_invoices', fn (Blueprint $table) => $table->decimal('total_quantity', 14, 3)->default(0)->change());
    }

    public function down(): void
    {
        // Intentionally no lossy down migration: decimal quantities may contain fractions.
    }
};
