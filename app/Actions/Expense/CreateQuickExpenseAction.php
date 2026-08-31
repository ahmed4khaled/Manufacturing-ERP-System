<?php

namespace App\Actions\Expense;

use App\Enums\ExpenseFundingSource;
use App\Models\Expense;
use App\Models\Partner;
use App\Services\Accounting\DocumentPostingService;
use App\Services\Accounting\QuickExpenseAccountService;
use App\Services\CodeGeneratorService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateQuickExpenseAction
{
    public function __construct(
        private readonly QuickExpenseAccountService $accounts,
        private readonly DocumentPostingService $posting,
        private readonly CodeGeneratorService $codes,
    ) {}

    public function handle(array $data, ?int $createdBy): Expense
    {
        return DB::transaction(function () use ($data, $createdBy): Expense {
            $existing = Expense::query()->where('submission_token', $data['submission_token'])->first();
            if ($existing) {
                return $existing;
            }

            $expenseAccount = $this->accounts->expenseAccount((int) $data['expense_account_id']);
            $partner = Partner::query()->where('is_active', true)->find($data['paid_by_partner_id']);
            if (! $partner) {
                throw ValidationException::withMessages(['paid_by_partner_id' => 'دافع المصروف المحدد غير نشط أو غير موجود.']);
            }

            $fundingSource = ExpenseFundingSource::from($data['funding_source']);
            $paymentAccount = $fundingSource === ExpenseFundingSource::COMPANY
                ? $this->accounts->companyPaymentAccount((int) $data['payment_account_id'])
                : $this->accounts->partnerCurrentAccount($partner);

            try {
                $expense = Expense::query()->create([
                    'expense_number' => $this->codes->next('EXP'),
                    'expense_date' => $data['expense_date'],
                    'expense_account_id' => $expenseAccount->id,
                    'payment_account_id' => $paymentAccount->id,
                    'amount' => $data['amount'],
                    'paid_by_partner_id' => $partner->id,
                    'funding_source' => $fundingSource,
                    'description' => $data['description'],
                    'reference' => $data['reference'] ?? null,
                    'submission_token' => $data['submission_token'],
                    'created_by' => $createdBy,
                ]);
            } catch (QueryException $exception) {
                $existing = Expense::query()->where('submission_token', $data['submission_token'])->first();
                if ($existing) {
                    return $existing;
                }

                throw $exception;
            }

            $this->posting->expense($expense);

            return $expense->fresh([
                'expenseAccount', 'paymentAccount', 'paidByPartner', 'creator', 'journalEntry.lines.account',
            ]);
        });
    }
}
