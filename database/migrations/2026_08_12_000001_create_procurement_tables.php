<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('code')->unique();
            $t->string('phone')->nullable();
            $t->string('email')->nullable()->index();
            $t->text('address')->nullable();
            $t->string('tax_number')->nullable()->unique();
            $t->text('notes')->nullable();
            $t->string('status')->default('active')->index();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('supplier_contacts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $t->string('name');
            $t->string('job_title')->nullable();
            $t->string('phone')->nullable();
            $t->string('email')->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_primary')->default(false);
            $t->timestamps();
            $t->index(['supplier_id', 'is_primary']);
        });
        Schema::create('purchase_orders', function (Blueprint $t) {
            $t->id();
            $t->string('purchase_order_number')->unique();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->date('order_date');
            $t->date('expected_delivery_date')->nullable();
            $t->string('status')->default('draft')->index();
            $t->text('notes')->nullable();
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('total', 14, 2)->default(0);
            $t->timestamps();
            $t->softDeletes();
            $t->index(['supplier_id', 'order_date']);
        });
        Schema::create('purchase_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 14, 3);
            $t->decimal('unit_price', 14, 2);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('line_total', 14, 2);
            $t->timestamps();
            $t->unique(['purchase_order_id', 'product_variant_id']);
        });
        Schema::create('goods_receipts', function (Blueprint $t) {
            $t->id();
            $t->string('receipt_number')->unique();
            $t->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->dateTime('received_at');
            $t->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $t->string('status')->default('draft')->index();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('goods_receipt_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $t->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->decimal('ordered_quantity', 14, 3);
            $t->decimal('received_quantity', 14, 3);
            $t->decimal('accepted_quantity', 14, 3);
            $t->decimal('rejected_quantity', 14, 3)->default(0);
            $t->decimal('unit_cost', 14, 2);
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('supplier_invoices', function (Blueprint $t) {
            $t->id();
            $t->string('invoice_number')->unique();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $t->date('invoice_date');
            $t->date('due_date')->nullable();
            $t->decimal('subtotal', 14, 2)->default(0);
            $t->decimal('discount', 14, 2)->default(0);
            $t->decimal('tax', 14, 2)->default(0);
            $t->decimal('total', 14, 2);
            $t->decimal('paid_amount', 14, 2)->default(0);
            $t->decimal('remaining_amount', 14, 2);
            $t->string('status')->default('unpaid')->index();
            $t->text('notes')->nullable();
            $t->timestamps();
        });
        Schema::create('supplier_payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $t->date('payment_date');
            $t->decimal('amount', 14, 2);
            $t->string('payment_method');
            $t->string('reference')->nullable();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('purchase_returns', function (Blueprint $t) {
            $t->id();
            $t->string('return_number')->unique();
            $t->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $t->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $t->foreignId('goods_receipt_id')->nullable()->constrained()->nullOnDelete();
            $t->date('return_date');
            $t->text('reason');
            $t->string('status')->default('draft')->index();
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('purchase_return_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('purchase_return_id')->constrained()->cascadeOnDelete();
            $t->foreignId('product_variant_id')->constrained()->restrictOnDelete();
            $t->decimal('quantity', 14, 3);
            $t->decimal('unit_cost', 14, 2);
            $t->text('reason')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('purchase_return_items');
        Schema::dropIfExists('purchase_returns');
        Schema::dropIfExists('supplier_payments');
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('goods_receipt_items');
        Schema::dropIfExists('goods_receipts');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('supplier_contacts');
        Schema::dropIfExists('suppliers');
    }
};
