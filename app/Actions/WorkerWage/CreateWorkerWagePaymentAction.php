<?php

namespace App\Actions\WorkerWage;

use App\Enums\WorkerWagePaymentStatus;
use App\Models\Worker;
use App\Models\WorkerWagePayment;
use App\Services\Accounting\DocumentPostingService;
use App\Services\Accounting\QuickExpenseAccountService;
use App\Services\CodeGeneratorService;
use App\Services\WorkerWagePaymentAllocationService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CreateWorkerWagePaymentAction
{
    public function __construct(
        private readonly CodeGeneratorService $codes,
        private readonly QuickExpenseAccountService $accounts,
        private readonly WorkerWagePaymentAllocationService $allocations,
        private readonly DocumentPostingService $accounting,
    ) {}

    public function handle(array $data, ?int $createdBy): WorkerWagePayment
    {
        return DB::transaction(function () use ($data, $createdBy): WorkerWagePayment {
            $existing = WorkerWagePayment::query()->where('submission_token', $data['submission_token'])->first();
            if ($existing) {
                return $existing;
            }

            $worker = Worker::withTrashed()->lockForUpdate()->findOrFail($data['worker_id']);
            $paymentAccount = $this->accounts->companyPaymentAccount((int) $data['payment_account_id']);

            try {
                $payment = WorkerWagePayment::query()->create([
                    'payment_number' => $this->codes->next('WP'),
                    'worker_id' => $worker->id,
                    'payment_date' => $data['payment_date'],
                    'payment_account_id' => $paymentAccount->id,
                    'amount' => $data['amount'],
                    'description' => $data['description'],
                    'reference' => $data['reference'] ?? null,
                    'submission_token' => $data['submission_token'],
                    'status' => WorkerWagePaymentStatus::DRAFT,
                    'created_by' => $createdBy,
                ]);
            } catch (QueryException $exception) {
                $existing = WorkerWagePayment::query()->where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    return $existing;
                }

                throw $exception;
            }

            $this->allocations->allocate($payment);
            $this->accounting->workerWagePayment($payment);

            return $payment->fresh([
                'worker', 'paymentAccount', 'allocations.wage.productionOrder', 'allocations.wage.operation', 'journalEntry.lines.account',
            ]);
        });
    }
}
