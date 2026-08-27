<?php

namespace App\Services;

use App\Models\CustomerLedgerEntry;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class CustomerLedgerService
{
    public function getTotalDebit(int $customerId): string
    {
        return (string) CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->sum('debit');
    }

    public function getTotalCredit(int $customerId): string
    {
        return (string) CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->sum('credit');
    }

    public function getBalance(int $customerId): string
    {
        $debit = $this->getTotalDebit($customerId);
        $credit = $this->getTotalCredit($customerId);

        return bcsub($debit, $credit, 2);
    }

    /**
     * @param  Collection<int, int>|null  $customerIds
     * @return Collection<int, string>
     */
    public function getBalances(?Collection $customerIds = null): Collection
    {
        return CustomerLedgerEntry::query()
            ->selectRaw('customer_id, SUM(debit) - SUM(credit) AS balance')
            ->when($customerIds !== null, fn ($query) => $query->whereIn('customer_id', $customerIds))
            ->groupBy('customer_id')
            ->pluck('balance', 'customer_id')
            ->map(fn ($balance) => bcadd((string) $balance, '0', 2));
    }

    public function getAvailableCredit(string $creditLimit, string $balance): string
    {
        return bcsub($creditLimit, $balance, 2);
    }

    /**
     * @return array{balance: string, total_debit: string, total_credit: string}
     */
    public function getSummary(int $customerId): array
    {
        return [
            'balance' => $this->getBalance($customerId),
            'total_debit' => $this->getTotalDebit($customerId),
            'total_credit' => $this->getTotalCredit($customerId),
        ];
    }

    public function getOpeningBalance(int $customerId, string $transactionDate, int $entryId): string
    {
        $debit = (string) CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->where(function ($query) use ($transactionDate, $entryId) {
                $query->where('transaction_date', '<', $transactionDate)
                    ->orWhere(function ($query) use ($transactionDate, $entryId) {
                        $query->where('transaction_date', $transactionDate)
                            ->where('id', '<', $entryId);
                    });
            })
            ->sum('debit');

        $credit = (string) CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->where(function ($query) use ($transactionDate, $entryId) {
                $query->where('transaction_date', '<', $transactionDate)
                    ->orWhere(function ($query) use ($transactionDate, $entryId) {
                        $query->where('transaction_date', $transactionDate)
                            ->where('id', '<', $entryId);
                    });
            })
            ->sum('credit');

        return bcsub($debit, $credit, 2);
    }

    /**
     * @param  LengthAwarePaginator<int, CustomerLedgerEntry>  $entries
     */
    public function attachRunningBalances(LengthAwarePaginator $entries): LengthAwarePaginator
    {
        $runningByCustomer = [];

        foreach ($entries as $entry) {
            $customerId = $entry->customer_id;

            if (! isset($runningByCustomer[$customerId])) {
                $runningByCustomer[$customerId] = $this->getOpeningBalance(
                    $customerId,
                    $entry->transaction_date->format('Y-m-d'),
                    $entry->id,
                );
            }

            $runningByCustomer[$customerId] = bcsub(
                bcadd($runningByCustomer[$customerId], (string) $entry->debit, 2),
                (string) $entry->credit,
                2,
            );

            $entry->running_balance = $runningByCustomer[$customerId];
        }

        return $entries;
    }

    public function getRecentEntries(int $customerId, int $limit = 10): Collection
    {
        $entries = CustomerLedgerEntry::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $runningBalance = $this->getBalance($customerId);

        foreach ($entries as $entry) {
            $entry->running_balance = $runningBalance;

            $runningBalance = bcsub(
                bcadd($runningBalance, (string) $entry->credit, 2),
                (string) $entry->debit,
                2,
            );
        }

        return $entries;
    }
}
