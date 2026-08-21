<?php

namespace App\Actions\ProductionMaterial;

use App\Enums\ProductionMaterialTransactionStatus;
use App\Enums\ProductionMaterialTransactionType;
use App\Models\ProductionMaterialTransaction;
use App\Services\ProductionActivityService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReviewWasteAction
{
    public function __construct(private readonly ProductionActivityService $activity) {}

    public function handle(ProductionMaterialTransaction $transaction, bool $approve): ProductionMaterialTransaction
    {
        return DB::transaction(function () use ($transaction, $approve) {
            $transaction = ProductionMaterialTransaction::query()->with('productionOrder')->lockForUpdate()->findOrFail($transaction->id);
            if ($transaction->type !== ProductionMaterialTransactionType::WASTE || $transaction->status !== ProductionMaterialTransactionStatus::PENDING_REVIEW) {
                throw ValidationException::withMessages(['status' => 'حركة الهالك ليست في حالة تسمح بالمراجعة.']);
            }
            if ($transaction->productionOrder->status->isLocked()) {
                throw ValidationException::withMessages(['status' => 'أمر الإنتاج مغلق.']);
            }
            $transaction->update(['status' => $approve ? ProductionMaterialTransactionStatus::APPROVED : ProductionMaterialTransactionStatus::REJECTED, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
            $this->activity->record($transaction->productionOrder, $approve ? 'waste_approved' : 'waste_rejected', $approve ? 'تم اعتماد هالك الخامة.' : 'تم رفض هالك الخامة.', ['transaction_id' => $transaction->id]);

            return $transaction;
        });
    }
}
