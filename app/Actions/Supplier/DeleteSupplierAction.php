<?php

namespace App\Actions\Supplier;

use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSupplierAction
{
    public function handle(Supplier $supplier): void
    {
        DB::transaction(function () use ($supplier): void {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);

            $invoiceTotal = (string) $supplier->invoices()->withTrashed()->sum('total');
            $paymentTotal = (string) $supplier->payments()->withTrashed()->sum('amount');
            $returnSummary = $supplier->returns()
                ->withTrashed()
                ->where('status', 'confirmed')
                ->join('purchase_return_items', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
                ->selectRaw('COALESCE(SUM(purchase_return_items.quantity * purchase_return_items.unit_cost), 0) AS return_total')
                ->first();
            $returnTotal = (string) ($returnSummary?->return_total ?? 0);

            $balance = bcsub(bcsub($invoiceTotal, $paymentTotal, 2), $returnTotal, 2);

            if (bccomp($balance, '0.00', 2) !== 0) {
                throw ValidationException::withMessages([
                    'supplier' => 'لا يمكن حذف المورد لأنه عليه رصيد مستحق.',
                ]);
            }

            $supplier->delete();
        });
    }
}
