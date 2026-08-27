<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['customer_ledger_entries', 'stock_movements', 'material_stock_movements'] as $table) {
            DB::table($table)->whereNotNull('deleted_at')->update(['deleted_at' => null]);
        }
    }

    public function down(): void
    {
        // Append-only history must never be hidden again during a rollback.
    }
};
