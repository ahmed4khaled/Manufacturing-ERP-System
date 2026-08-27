<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DocumentSequenceSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['sales_invoice', 'payment_receipt', 'sales_return', 'purchase_order', 'goods_receipt', 'purchase_return', 'production_order'] as $key) {
            DB::table('document_sequences')->updateOrInsert(
                ['key' => $key],
                ['next_value' => 100, 'created_at' => now(), 'updated_at' => now()],
            );
        }
    }
}
