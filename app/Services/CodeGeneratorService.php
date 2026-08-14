<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class CodeGeneratorService
{
    public function generate(string $prefix, int $sequence): string
    {
        return $prefix . str_pad(
            (string) $sequence,
            6,
            '0',
            STR_PAD_LEFT
        );
    }

    public function next(string $prefix): string
    {
        return DB::transaction(function () use ($prefix) {

            DB::table('document_sequences')->insertOrIgnore([
                'key' => $prefix,
                'next_value' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $row = DB::table('document_sequences')
                ->where('key', $prefix)
                ->lockForUpdate()
                ->first();

            $current = (int) ($row->next_value ?? 1);

            /*
            |--------------------------------------------------------------------------
            | Keep sequence ahead of existing records
            |--------------------------------------------------------------------------
            */

          $sequenceConfig = [
    'INV' => [
        'table' => 'sales_invoices',
        'column' => 'invoice_number',
    ],

    'SPAY' => [
        'table' => 'supplier_payments',
        'column' => 'payment_number',
    ],

    'PO' => [
        'table' => 'purchase_orders',
        'column' => 'purchase_order_number',
    ],

    'RCPT' => [
        'table' => 'payment_receipts',
        'column' => 'receipt_number',
    ],
];

            if (isset($sequenceConfig[$prefix])) {

                $config = $sequenceConfig[$prefix];

                do {

                    $code = $this->generate(
                        $prefix,
                        $current
                    );

                    $exists = DB::table($config['table'])
                        ->where($config['column'], $code)
                        ->exists();

                    if ($exists) {
                        $current++;
                    }

                } while ($exists);

            } else {

                $code = $this->generate(
                    $prefix,
                    $current
                );
            }

            /*
            |--------------------------------------------------------------------------
            | Save next sequence
            |--------------------------------------------------------------------------
            */

            DB::table('document_sequences')
                ->where('id', $row->id)
                ->update([
                    'next_value' => $current + 1,
                    'updated_at' => now(),
                ]);

            return $code;
        });
    }
}