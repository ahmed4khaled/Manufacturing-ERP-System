<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\User;
use App\Services\Accounting\AccountingReportService;
use Database\Seeders\AccountingSeeder;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Permission;

class AuditAccounting extends Command
{
    protected $signature = 'accounting:audit';

    protected $description = 'Run read-only accounting integrity and configuration checks';

    public function handle(AccountingReportService $reports): int
    {
        $unbalanced = JournalEntry::query()->with('lines')->where('status', 'posted')->get()->filter(
            fn (JournalEntry $entry) => $entry->lines->count() < 2
                || bccomp((string) $entry->lines->sum('debit'), (string) $entry->lines->sum('credit'), 2) !== 0,
        )->count();
        $trial = $reports->trialBalance(null, null);
        $balanceSheet = $reports->balanceSheet(now()->toDateString());
        $superAdmin = User::query()->where('email', User::SUPER_ADMIN_EMAIL)->first();

        $this->table(['Check', 'Value'], [
            ['Accounts / mappings', Account::query()->count().' / '.AccountMapping::query()->count()],
            ['Accounting permissions', Permission::query()->where('name', 'like', 'accounting.%')->count()],
            ['Super admin has all', $superAdmin?->hasAllPermissions(AccountingSeeder::PERMISSIONS) ? 'yes' : 'no'],
            ['Posted entries / lines', JournalEntry::query()->where('status', 'posted')->count().' / '.JournalEntryLine::query()->count()],
            ['Total debit / credit', JournalEntryLine::query()->sum('debit').' / '.JournalEntryLine::query()->sum('credit')],
            ['Unbalanced posted entries', $unbalanced],
            ['Trial balance valid', $trial['balanced'] ? 'yes' : 'no'],
            ['Balance sheet valid', $balanceSheet['balanced'] ? 'yes' : 'no'],
        ]);

        return $unbalanced === 0 && $trial['balanced'] && $balanceSheet['balanced'] ? self::SUCCESS : self::FAILURE;
    }
}
