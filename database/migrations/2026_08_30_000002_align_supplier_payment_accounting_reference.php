<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('supplier_payments', 'payment_number')) {
            Schema::table('supplier_payments', function (Blueprint $table): void {
                $table->string('payment_number')->nullable()->unique()->after('id');
            });
        }
    }

    public function down(): void
    {
        // Existing supplier payment references are business history and are retained.
    }
};
