<?php

namespace Database\Seeders;

use App\Actions\WorkerWage\CreateWorkerWagePaymentAction;
use App\Actions\WorkerWage\GenerateProductionWorkerWagesAction;
use App\Models\AccountMapping;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ProductionOrder;
use App\Models\ProductionWorkerWage;
use App\Models\User;
use App\Models\Worker;
use App\Models\WorkerWagePayment;
use App\Services\Accounting\JournalEntryService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WorkerWageDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(AccountingSeeder::class);
        $user = User::query()->where('email', 'demo.dataset@abaya.test')->firstOrFail();
        Auth::login($user);

        try {
            DB::transaction(function () use ($user): void {
                $orders = ProductionOrder::query()
                    ->where('notes', 'like', 'DEMO-DATASET-PRODUCTION-%')
                    ->where('status', 'completed')
                    ->with(['workAssignments.operation'])
                    ->orderBy('id')
                    ->get();

                if ($orders->isEmpty()) {
                    throw new ModelNotFoundException('Run DemoDatasetSeeder first; no completed DEMO production orders were found.');
                }

                foreach ($orders as $order) {
                    $wages = app(GenerateProductionWorkerWagesAction::class)->handle($order);
                    $this->ensureWagePayableClassification($order, $wages, $user->id);
                }

                $this->seedPayments($user->id);
                $this->verifyReconciliation();
            });
        } finally {
            Auth::logout();
        }
    }

    /** @param Collection<int, ProductionWorkerWage> $wages */
    private function ensureWagePayableClassification(ProductionOrder $order, Collection $wages, int $userId): void
    {
        $productionEntry = JournalEntry::query()
            ->where('reference_type', ProductionOrder::class)
            ->where('reference_id', $order->id)
            ->where('entry_type', 'production_completion')
            ->with('lines')
            ->firstOrFail();
        $wagePayableId = AccountMapping::query()->where('key', 'production_wages_payable')->value('account_id');
        $manufacturingPayableId = AccountMapping::query()->where('key', 'manufacturing_costs_payable')->value('account_id');
        $wageTotal = $wages->reduce(
            fn (string $sum, ProductionWorkerWage $wage) => bcadd($sum, (string) $wage->gross_amount, 2),
            '0.00',
        );
        $alreadyClassified = (string) $productionEntry->lines->where('account_id', $wagePayableId)->sum('credit');
        if (bccomp($alreadyClassified, $wageTotal, 2) === 0) {
            return;
        }

        $genericCredit = (string) $productionEntry->lines->where('account_id', $manufacturingPayableId)->sum('credit');
        if (bccomp($genericCredit, $wageTotal, 2) < 0) {
            throw new RuntimeException("Demo order {$order->order_number} has no liability balance that can be safely reclassified to worker wages.");
        }

        $lines = [[
            'account_id' => $manufacturingPayableId,
            'debit' => $wageTotal,
            'credit' => 0,
            'description' => 'إعادة تصنيف أجور الإنتاج التجريبية '.$order->order_number,
        ]];
        foreach ($wages as $wage) {
            if (bccomp((string) $wage->gross_amount, '0', 2) <= 0) {
                continue;
            }
            $lines[] = [
                'account_id' => $wagePayableId,
                'debit' => 0,
                'credit' => $wage->gross_amount,
                'worker_id' => $wage->worker_id,
                'description' => 'استحقاق أجر إنتاج تجريبي '.$order->order_number,
            ];
        }

        app(JournalEntryService::class)->post([
            'entry_date' => $order->closed_at?->toDateString() ?? now()->toDateString(),
            'description' => 'إعادة تصنيف أجور أمر الإنتاج التجريبي '.$order->order_number,
            'reference_type' => ProductionOrder::class,
            'reference_id' => $order->id,
            'entry_type' => 'demo_wage_reclassification',
            'created_by' => $userId,
        ], $lines);
    }

    private function seedPayments(int $userId): void
    {
        $definitions = [
            ['DEMO-WRK-001', '2500.00', 'cash', '00000000-0000-4000-8000-000000000001'],
            ['DEMO-WRK-003', '12250.00', 'bank', '00000000-0000-4000-8000-000000000002'],
            ['DEMO-WRK-005', '1000.00', 'cash', '00000000-0000-4000-8000-000000000003'],
        ];

        foreach ($definitions as [$workerCode, $amount, $accountMapping, $token]) {
            if (WorkerWagePayment::query()->where('submission_token', $token)->exists()) {
                continue;
            }
            $worker = Worker::query()->where('code', $workerCode)->firstOrFail();
            $outstanding = (string) $worker->productionWages()->sum('remaining_amount');
            if (bccomp($outstanding, '0', 2) <= 0) {
                continue;
            }
            $paymentAmount = bccomp($amount, $outstanding, 2) <= 0 ? $amount : $outstanding;

            app(CreateWorkerWagePaymentAction::class)->handle([
                'worker_id' => $worker->id,
                'payment_date' => '2026-03-01',
                'payment_account_id' => AccountMapping::query()->where('key', $accountMapping)->value('account_id'),
                'amount' => $paymentAmount,
                'description' => 'دفعة أجور إنتاج تجريبية - '.$worker->name,
                'reference' => 'DEMO-WAGE-PAY-'.$workerCode,
                'submission_token' => $token,
            ], $userId);
        }
    }

    private function verifyReconciliation(): void
    {
        $workerIds = Worker::query()->where('code', 'like', 'DEMO-WRK-%')->pluck('id');
        $wagePayableId = AccountMapping::query()->where('key', 'production_wages_payable')->value('account_id');
        $outstanding = (string) ProductionWorkerWage::query()->whereIn('worker_id', $workerIds)->sum('remaining_amount');
        $payable = (string) JournalEntryLine::query()
            ->where('account_id', $wagePayableId)
            ->whereIn('worker_id', $workerIds)
            ->selectRaw('COALESCE(SUM(credit), 0) - COALESCE(SUM(debit), 0) AS balance')
            ->value('balance');

        if (bccomp($outstanding, $payable, 2) !== 0) {
            throw new RuntimeException("Demo worker wage reconciliation failed: outstanding {$outstanding}, payable {$payable}.");
        }

        $this->command?->info('Worker wage demo seeded: '.ProductionWorkerWage::query()->whereIn('worker_id', $workerIds)->count().' wages, '.WorkerWagePayment::query()->where('reference', 'like', 'DEMO-WAGE-PAY-%')->count()." payments, outstanding {$outstanding} EGP.");
    }
}
