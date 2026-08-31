<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('material_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2);
            $table->decimal('line_total', 14, 2);
            $table->timestamps();

            $table->unique(['supplier_invoice_id', 'material_id']);
        });

        Schema::table('supplier_invoices', function (Blueprint $table) {
            $table->string('supplier_reference')->nullable()->after('invoice_number')->index();
            $table->string('receipt_mode')->nullable()->after('goods_receipt_id')->index();
            $table->uuid('submission_token')->nullable()->after('receipt_mode')->unique();
        });

        Schema::table('goods_receipts', function (Blueprint $table) {
            $table->foreignId('purchase_order_id')->nullable()->change();
            $table->foreignId('supplier_invoice_id')
                ->nullable()
                ->after('purchase_order_id')
                ->unique()
                ->constrained()
                ->nullOnDelete();
        });

        Schema::table('goods_receipt_items', function (Blueprint $table) {
            $table->foreignId('purchase_order_item_id')->nullable()->change();
            $table->foreignId('supplier_invoice_item_id')
                ->nullable()
                ->after('purchase_order_item_id')
                ->unique()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        // Intentionally retained: direct-purchase documents and their audit trail must not be dropped.
    }
};
