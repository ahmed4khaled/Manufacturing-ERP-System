<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var list<string> */
    private array $tables = [
        'goods_receipts',
        'supplier_invoices',
        'supplier_payments',
        'purchase_returns',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse($this->tables) as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->dropSoftDeletes();
            });
        }
    }
};
