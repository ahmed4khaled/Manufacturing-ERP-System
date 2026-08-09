<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('reference_type');
            $table->unsignedBigInteger('reference_id');
            $table->string('reference_number')->nullable();
            $table->date('transaction_date');
            $table->string('description');
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['reference_type', 'reference_id']);
            $table->index(['customer_id', 'transaction_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};
