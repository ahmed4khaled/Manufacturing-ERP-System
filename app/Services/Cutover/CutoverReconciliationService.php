<?php

namespace App\Services\Cutover;

use App\Models\CutoverBatch;
use App\Models\FiscalYear;
use App\Services\Accounting\MonthlyFiscalPeriodService;

class CutoverReconciliationService
{
    private const LABELS = [
        'cash_bank' => 'الخزائن والبنوك',
        'material' => 'مخزون الخامات',
        'finished_goods' => 'المنتجات التامة',
        'wip' => 'الإنتاج تحت التشغيل',
        'worker_payable' => 'أجور العمال المستحقة',
        'partner' => 'أرصدة الشركاء',
        'other' => 'الحسابات الأخرى',
    ];

    public function __construct(private readonly MonthlyFiscalPeriodService $periods) {}

    public function report(CutoverBatch $batch): array
    {
        $batch->loadMissing(['balances.account', 'journalEntry.lines.account']);
        $entryLines = $batch->journalEntry?->lines ?? collect();
        $sections = collect(self::LABELS)->map(function (string $label, string $type) use ($batch, $entryLines): array {
            $details = $batch->balances->where('type', $type);
            $actualLines = $entryLines->whereIn('cutover_opening_balance_id', $details->pluck('id'));
            $expected = $details->reduce(fn (string $sum, $line) => bcadd($sum, $this->expectedDebitNet($line), 2), '0.00');
            $actual = $actualLines->reduce(fn (string $sum, $line) => bcadd($sum, bcsub((string) $line->debit, (string) $line->credit, 2), 2), '0.00');
            $difference = bcsub($expected, $actual, 2);

            return compact('label', 'expected', 'actual', 'difference') + [
                'matched' => bccomp($difference, '0', 2) === 0,
                'rows' => $details->count(),
            ];
        })->all();

        $clearingId = optional($batch->journalEntry?->lines->first(fn ($line) => $line->account?->subtype === 'opening_balance_clearing'))->account_id;
        $clearingLines = $clearingId ? $entryLines->where('account_id', $clearingId) : collect();
        $clearing = $clearingLines->reduce(fn (string $sum, $line) => bcadd($sum, bcsub((string) $line->debit, (string) $line->credit, 2), 2), '0.00');
        $unvalued = $batch->balances->whereIn('type', ['material', 'finished_goods'])->whereNull('unit_cost')->count();
        $debit = $entryLines->sum(fn ($line) => (float) $line->debit);
        $credit = $entryLines->sum(fn ($line) => (float) $line->credit);
        $journalBalanced = bccomp(number_format($debit, 2, '.', ''), number_format($credit, 2, '.', ''), 2) === 0;
        $year = FiscalYear::query()->whereDate('start_date', '<=', $batch->cutover_date)->whereDate('end_date', '>=', $batch->cutover_date)->first();
        $expectedPeriods = $year ? $this->periods->countExpected($year) : 0;
        $presentPeriods = $year ? $this->periods->countPresent($year) : 0;
        $monthlyPeriodsReady = $expectedPeriods > 0 && $presentPeriods >= $expectedPeriods;
        $ready = $batch->status === CutoverBatch::STATUS_POSTED
            && $journalBalanced
            && collect($sections)->every(fn ($section) => $section['matched'])
            && bccomp($clearing, '0', 2) === 0
            && $unvalued === 0
            && $monthlyPeriodsReady;

        return compact('sections', 'clearing', 'unvalued', 'journalBalanced', 'expectedPeriods', 'presentPeriods', 'monthlyPeriodsReady', 'ready');
    }

    private function expectedDebitNet($line): string
    {
        $amount = (string) $line->amount;
        $positive = bccomp($amount, '0', 2) > 0;
        $absolute = $positive ? $amount : bcmul($amount, '-1', 2);
        if (in_array($line->type, ['cash_bank', 'material', 'finished_goods', 'wip'], true)) {
            return $absolute;
        }
        if ($line->type === 'worker_payable') {
            return bcmul($absolute, '-1', 2);
        }
        if ($line->type === 'partner') {
            $debit = $line->partner_balance_type === 'withdrawals' ? $positive : ! $positive;

            return $debit ? $absolute : bcmul($absolute, '-1', 2);
        }
        $debit = $positive ? $line->account->normalSide() === 'debit' : $line->account->normalSide() !== 'debit';

        return $debit ? $absolute : bcmul($absolute, '-1', 2);
    }
}
