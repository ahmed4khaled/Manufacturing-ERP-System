<?php

namespace App\Actions\SalesReturn;

use App\Enums\SalesReturnStatus;
use App\Models\SalesReturn;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteSalesReturnAction
{
    public function handle(SalesReturn $salesReturn): void
    {
        DB::transaction(function () use ($salesReturn) {
            $salesReturn = SalesReturn::query()
                ->lockForUpdate()
                ->findOrFail($salesReturn->id);

            if (! in_array($salesReturn->status, [SalesReturnStatus::DRAFT, SalesReturnStatus::CANCELLED], true)) {
                throw ValidationException::withMessages([
                    'status' => 'لا يمكن حذف مرتجع مؤكد مباشرة. ألغِ المرتجع واعكس أثره أولاً.',
                ]);
            }

            $salesReturn->delete();
        });
    }
}
