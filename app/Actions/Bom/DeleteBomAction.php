<?php

namespace App\Actions\Bom;

use App\Models\Bom;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteBomAction
{
    public function handle(Bom $bom): void
    {
        DB::transaction(function () use ($bom): void {
            $bom = Bom::query()->lockForUpdate()->findOrFail($bom->id);

            if ($bom->productionOrders()->exists()) {
                throw ValidationException::withMessages([
                    'bom' => 'لا يمكن حذف BOM مستخدم في أوامر إنتاج تاريخية.',
                ]);
            }

            $bom->delete();
        });
    }
}
