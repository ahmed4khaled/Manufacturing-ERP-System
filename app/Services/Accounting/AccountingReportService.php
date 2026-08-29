<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\JournalEntryLine;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AccountingReportService
{
    public function ledger(Account $account, ?string $from, ?string $to): array
    {
        $opening = $from
            ? $this->lineQuery()->where('account_id', $account->id)
                ->whereHas('journalEntry', fn (Builder $entry) => $entry->whereDate('entry_date', '<', $from))
                ->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')->first()
            : (object) ['debit' => '0', 'credit' => '0'];
        $openingBalance = $this->naturalBalance($account, (string) $opening->debit, (string) $opening->credit);
        $running = $openingBalance;
        $lines = $this->lineQuery()->with('journalEntry')->where('account_id', $account->id)
            ->when($from, fn (Builder $query) => $query->whereHas('journalEntry', fn (Builder $entry) => $entry->whereDate('entry_date', '>=', $from)))
            ->when($to, fn (Builder $query) => $query->whereHas('journalEntry', fn (Builder $entry) => $entry->whereDate('entry_date', '<=', $to)))
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->orderBy('journal_entries.entry_date')->orderBy('journal_entry_lines.id')
            ->select('journal_entry_lines.*')->get();
        foreach ($lines as $line) {
            $movement = $this->naturalBalance($account, (string) $line->debit, (string) $line->credit);
            $running = bcadd($running, $movement, 2);
            $line->running_balance = $running;
        }

        return compact('openingBalance', 'lines');
    }

    public function trialBalance(?string $from, ?string $to): array
    {
        $rows = Account::query()->where('allow_posting', true)->orderBy('code')->get()->map(function (Account $account) use ($from, $to): array {
            $opening = $from ? $this->sums($account->id, null, date('Y-m-d', strtotime($from.' -1 day'))) : (object) ['debit' => '0', 'credit' => '0'];
            $period = $this->sums($account->id, $from, $to);
            $openingNet = bcsub((string) $opening->debit, (string) $opening->credit, 2);
            $closingNet = bcadd($openingNet, bcsub((string) $period->debit, (string) $period->credit, 2), 2);

            return [
                'account' => $account,
                'opening_debit' => bccomp($openingNet, '0', 2) > 0 ? $openingNet : '0.00',
                'opening_credit' => bccomp($openingNet, '0', 2) < 0 ? bcmul($openingNet, '-1', 2) : '0.00',
                'period_debit' => bcadd((string) $period->debit, '0', 2),
                'period_credit' => bcadd((string) $period->credit, '0', 2),
                'closing_debit' => bccomp($closingNet, '0', 2) > 0 ? $closingNet : '0.00',
                'closing_credit' => bccomp($closingNet, '0', 2) < 0 ? bcmul($closingNet, '-1', 2) : '0.00',
            ];
        })->filter(fn (array $row) => collect($row)->except('account')->contains(fn ($value) => bccomp((string) $value, '0', 2) !== 0))->values();
        $totals = [];
        foreach (['opening_debit', 'opening_credit', 'period_debit', 'period_credit', 'closing_debit', 'closing_credit'] as $column) {
            $totals[$column] = $rows->reduce(fn (string $sum, array $row) => bcadd($sum, $row[$column], 2), '0.00');
        }
        $balanced = bccomp($totals['closing_debit'], $totals['closing_credit'], 2) === 0
            && bccomp($totals['period_debit'], $totals['period_credit'], 2) === 0;

        return compact('rows', 'totals', 'balanced');
    }

    public function profitAndLoss(?string $from, ?string $to): array
    {
        $revenue = $this->balancesByType('revenue', $from, $to);
        $expenses = $this->balancesByType('expense', $from, $to);
        $salesReturns = ltrim((string) ($revenue->firstWhere('code', '4200')?->balance ?? '0.00'), '-');
        $grossRevenue = $revenue->reject(fn ($row) => $row->code === '4200')->sum(fn ($row) => (float) $row->balance);
        $netSales = bcsub(number_format($grossRevenue, 2, '.', ''), (string) $salesReturns, 2);
        $cogs = (string) ($expenses->firstWhere('code', '5100')?->balance ?? '0.00');
        $operatingExpenses = number_format($expenses->reject(fn ($row) => $row->code === '5100')->sum(fn ($row) => (float) $row->balance), 2, '.', '');
        $grossProfit = bcsub($netSales, $cogs, 2);
        $netProfit = bcsub($grossProfit, $operatingExpenses, 2);

        return compact('revenue', 'expenses', 'netSales', 'cogs', 'grossProfit', 'operatingExpenses', 'netProfit');
    }

    public function balanceSheet(?string $to): array
    {
        $assets = $this->balancesByType('asset', null, $to);
        $liabilities = $this->balancesByType('liability', null, $to);
        $equity = $this->balancesByType('equity', null, $to);
        $currentProfit = $this->profitAndLoss(null, $to)['netProfit'];
        $assetTotal = number_format($assets->sum(fn ($row) => (float) $row->balance), 2, '.', '');
        $liabilityTotal = number_format($liabilities->sum(fn ($row) => (float) $row->balance), 2, '.', '');
        $equityTotal = bcadd(number_format($equity->sum(fn ($row) => (float) $row->balance), 2, '.', ''), $currentProfit, 2);
        $balanced = bccomp($assetTotal, bcadd($liabilityTotal, $equityTotal, 2), 2) === 0;

        return compact('assets', 'liabilities', 'equity', 'currentProfit', 'assetTotal', 'liabilityTotal', 'equityTotal', 'balanced');
    }

    public function balancesByType(string $type, ?string $from, ?string $to): Collection
    {
        return Account::query()->where('type', $type)->where('allow_posting', true)->orderBy('code')->get()->map(function (Account $account) use ($from, $to) {
            $sum = $this->sums($account->id, $from, $to);
            $account->balance = $this->naturalBalance($account, (string) $sum->debit, (string) $sum->credit);

            return $account;
        })->filter(fn (Account $account) => bccomp($account->balance, '0', 2) !== 0)->values();
    }

    private function sums(int $accountId, ?string $from, ?string $to): object
    {
        return $this->lineQuery()->where('account_id', $accountId)
            ->when($from, fn (Builder $query) => $query->whereHas('journalEntry', fn (Builder $entry) => $entry->whereDate('entry_date', '>=', $from)))
            ->when($to, fn (Builder $query) => $query->whereHas('journalEntry', fn (Builder $entry) => $entry->whereDate('entry_date', '<=', $to)))
            ->selectRaw('COALESCE(SUM(debit),0) debit, COALESCE(SUM(credit),0) credit')->first();
    }

    private function lineQuery(): Builder
    {
        return JournalEntryLine::query()->whereHas('journalEntry', fn (Builder $query) => $query->where('status', 'posted'));
    }

    private function naturalBalance(Account $account, string $debit, string $credit): string
    {
        return $account->normalSide() === 'debit' ? bcsub($debit, $credit, 2) : bcsub($credit, $debit, 2);
    }
}
