<?php

namespace App\Services;

use App\Enums\ProductionWorkerWageStatus;
use App\Models\ProductionWorkerWage;
use App\Models\WorkerWagePayment;
use Illuminate\Validation\ValidationException;

class WorkerWagePaymentAllocationService
{
    public function allocate(WorkerWagePayment $payment): void
    {
        $wages = ProductionWorkerWage::query()
            ->where('worker_id', $payment->worker_id)
            ->where('remaining_amount', '>', 0)
            ->orderBy('earned_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $outstanding = $wages->reduce(
            fn (string $sum, ProductionWorkerWage $wage) => bcadd($sum, (string) $wage->remaining_amount, 2),
            '0.00',
        );

        if (bccomp((string) $payment->amount, $outstanding, 2) > 0) {
            throw ValidationException::withMessages([
                'amount' => "مبلغ الدفع يتجاوز المتبقي للعامل ({$outstanding}).",
            ]);
        }

        $unallocated = (string) $payment->amount;
        foreach ($wages as $wage) {
            if (bccomp($unallocated, '0', 2) === 0) {
                break;
            }

            $amount = bccomp($unallocated, (string) $wage->remaining_amount, 2) >= 0
                ? (string) $wage->remaining_amount
                : $unallocated;
            $payment->allocations()->create([
                'production_worker_wage_id' => $wage->id,
                'amount' => $amount,
            ]);
            $wage->update([
                'paid_amount' => bcadd((string) $wage->paid_amount, $amount, 2),
                'remaining_amount' => bcsub((string) $wage->remaining_amount, $amount, 2),
                'status' => $this->statusFor(
                    bcadd((string) $wage->paid_amount, $amount, 2),
                    bcsub((string) $wage->remaining_amount, $amount, 2),
                ),
            ]);
            $unallocated = bcsub($unallocated, $amount, 2);
        }

        if (bccomp($unallocated, '0', 2) !== 0) {
            throw ValidationException::withMessages(['amount' => 'تعذر تخصيص مبلغ الدفعة بالكامل.']);
        }
    }

    public function reverse(WorkerWagePayment $payment): void
    {
        $allocations = $payment->allocations()
            ->whereNull('reversed_at')
            ->with('wage')
            ->lockForUpdate()
            ->get();

        foreach ($allocations as $allocation) {
            $wage = ProductionWorkerWage::query()->lockForUpdate()->findOrFail($allocation->production_worker_wage_id);
            $paid = bcsub((string) $wage->paid_amount, (string) $allocation->amount, 2);
            $remaining = bcadd((string) $wage->remaining_amount, (string) $allocation->amount, 2);
            if (bccomp($paid, '0', 2) < 0 || bccomp($remaining, (string) $wage->gross_amount, 2) > 0) {
                throw ValidationException::withMessages(['payment' => 'تعذر عكس تخصيصات الدفعة بأمان.']);
            }
            $wage->update([
                'paid_amount' => $paid,
                'remaining_amount' => $remaining,
                'status' => $this->statusFor($paid, $remaining),
            ]);
            $allocation->update(['reversed_at' => now()]);
        }
    }

    private function statusFor(string $paid, string $remaining): ProductionWorkerWageStatus
    {
        if (bccomp($remaining, '0', 2) === 0) {
            return ProductionWorkerWageStatus::PAID;
        }

        return bccomp($paid, '0', 2) > 0
            ? ProductionWorkerWageStatus::PARTIALLY_PAID
            : ProductionWorkerWageStatus::UNPAID;
    }
}
