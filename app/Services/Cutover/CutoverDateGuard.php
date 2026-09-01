<?php

namespace App\Services\Cutover;

use App\Models\CutoverBatch;
use Illuminate\Validation\ValidationException;

class CutoverDateGuard
{
    public function assertLegacyOpeningAllowed(): void
    {
        $active = $this->activeBatch();

        if ($active) {
            throw ValidationException::withMessages([
                'opening_stock' => "تم اعتماد {$active->batch_number}. استخدم وحدة الأرصدة الافتتاحية والانتقال للتشغيل بدل شاشة الرصيد الافتتاحي القديمة.",
            ]);
        }
    }

    public function assertAllowed(mixed $date, ?string $referenceType = null): void
    {
        if ($referenceType === CutoverBatch::class) {
            return;
        }

        $active = $this->activeBatch();

        if ($active && (string) $date < $active->cutover_date->toDateString()) {
            throw ValidationException::withMessages([
                'date' => "لا يمكن إنشاء حركة بتاريخ يسبق تاريخ التشغيل {$active->cutover_date->toDateString()} بعد ترحيل {$active->batch_number}.",
            ]);
        }
    }

    private function activeBatch(): ?CutoverBatch
    {
        return CutoverBatch::query()
            ->where('status', CutoverBatch::STATUS_POSTED)
            ->latest('cutover_date')
            ->first();
    }
}
