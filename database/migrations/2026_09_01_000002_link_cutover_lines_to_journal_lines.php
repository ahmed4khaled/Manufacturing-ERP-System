<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->foreignId('cutover_opening_balance_id')
                ->nullable()
                ->unique()
                ->after('id')
                ->constrained('cutover_opening_balances')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // The link is part of the append-only Cutover audit trail.
    }
};
