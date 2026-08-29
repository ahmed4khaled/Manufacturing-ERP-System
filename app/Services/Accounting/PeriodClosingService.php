<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\FiscalPeriod;
use App\Models\FiscalYear;
use App\Models\JournalEntryLine;
use Illuminate\Support\Facades\DB;

class PeriodClosingService
{
    public function __construct(private readonly JournalEntryService $journal) {}

    public function closePeriod(FiscalPeriod $period): FiscalPeriod
    {
        return DB::transaction(function () use ($period): FiscalPeriod {
            $period = FiscalPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($period->status === 'closed') {
                return $period;
            }
            $period->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);

            return $period;
        });
    }

    public function closeYear(FiscalYear $year): FiscalYear
    {
        return DB::transaction(function () use ($year): FiscalYear {
            $year = FiscalYear::query()->with('periods')->lockForUpdate()->findOrFail($year->id);
            if ($year->status === 'closed') {
                return $year;
            }
            $accounts = Account::query()->whereIn('type', ['revenue', 'expense'])->where('allow_posting', true)->get();
            $lines = [];
            $profit = '0.00';
            foreach ($accounts as $account) {
                $sum = JournalEntryLine::query()->where('account_id', $account->id)
                    ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted')->whereBetween('entry_date', [$year->start_date, $year->end_date]))
                    ->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')->first();
                $balance = $account->type === 'revenue'
                    ? bcsub((string) $sum->credit, (string) $sum->debit, 2)
                    : bcsub((string) $sum->debit, (string) $sum->credit, 2);
                if (bccomp($balance, '0', 2) <= 0) {
                    continue;
                }
                $lines[] = ['account_id' => $account->id, 'debit' => $account->type === 'revenue' ? $balance : 0, 'credit' => $account->type === 'expense' ? $balance : 0];
                $profit = $account->type === 'revenue' ? bcadd($profit, $balance, 2) : bcsub($profit, $balance, 2);
            }
            if ($lines) {
                $retained = $this->journal->account('retained_earnings');
                $lines[] = ['account_id' => $retained->id, 'debit' => bccomp($profit, '0', 2) < 0 ? bcmul($profit, '-1', 2) : 0, 'credit' => bccomp($profit, '0', 2) > 0 ? $profit : 0];
                $this->journal->post([
                    'entry_date' => $year->end_date->toDateString(), 'description' => 'إقفال السنة المالية '.$year->name,
                    'reference_type' => FiscalYear::class, 'reference_id' => $year->id, 'entry_type' => 'year_closing',
                    'allow_closed_period' => true,
                ], $lines);
            }
            FiscalPeriod::query()->where('fiscal_year_id', $year->id)->where('status', 'open')->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);
            $year->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => auth()->id()]);

            return $year->fresh('periods');
        });
    }
}
