<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('expenses', function (Blueprint $table): void {
            $table->foreignId('paid_by_partner_id')->nullable()->after('amount')->constrained('partners')->restrictOnDelete();
            $table->string('funding_source', 20)->default('company')->after('paid_by_partner_id')->index();
            $table->uuid('submission_token')->nullable()->after('reference')->unique();
            $table->foreignId('reversal_journal_entry_id')->nullable()->after('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->foreignId('reversed_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable()->after('posted_at');
            $table->index(['paid_by_partner_id', 'expense_date'], 'expenses_payer_date_index');
        });
    }

    public function down(): void
    {
        // Accounting documents are append-only; rollback intentionally never removes their data.
    }
};
