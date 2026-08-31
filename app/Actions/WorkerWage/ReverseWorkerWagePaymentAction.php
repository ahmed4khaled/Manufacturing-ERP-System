<?php

namespace App\Actions\WorkerWage;

use App\Enums\WorkerWagePaymentStatus;
use App\Models\WorkerWagePayment;
use App\Services\Accounting\DocumentPostingService;
use App\Services\WorkerWagePaymentAllocationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReverseWorkerWagePaymentAction
{
    public function __construct(
        private readonly WorkerWagePaymentAllocationService $allocations,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(WorkerWagePayment $payment, ?string $date, ?int $reversedBy): WorkerWagePayment
    {
        return DB::transaction(function () use ($payment, $date, $reversedBy): WorkerWagePayment {
            $payment = WorkerWagePayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->status === WorkerWagePaymentStatus::REVERSED) {
                return $payment;
            }
            if ($payment->status !== WorkerWagePaymentStatus::POSTED || ! $payment->journal_entry_id) {
                throw ValidationException::withMessages(['payment' => 'لا يمكن عكس دفعة أجر غير مرحّلة.']);
            }

            $reversal = $this->accounting->reverse($payment, 'worker_wage_payment', $date);
            if (! $reversal) {
                throw ValidationException::withMessages(['payment' => 'تعذر إنشاء قيد عكس لدفعة الأجر.']);
            }

            $this->allocations->reverse($payment);
            $payment->update([
                'status' => WorkerWagePaymentStatus::REVERSED,
                'reversal_journal_entry_id' => $reversal->id,
                'reversed_by' => $reversedBy,
                'reversed_at' => now(),
            ]);

            return $payment->fresh(['allocations.wage', 'journalEntry', 'reversalJournalEntry']);
        });
    }
}
