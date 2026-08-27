<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->decimal('discount', 12, 2)->default(0)->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('sales_invoices', function (Blueprint $table): void {
            $table->dropColumn('discount');
        });
    }
};
