<?php

namespace App\Services\Accounting;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Partner;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

class QuickExpenseAccountService
{
    /** @var list<string> */
    private const PROTECTED_EXPENSE_SUBTYPES = [
        'cost_of_goods_sold',
        'wages_expense',
        'inventory_adjustment',
    ];

    /** @return Collection<int, Account> */
    public function categories(): Collection
    {
        $accountIds = AccountMapping::query()
            ->whereHas('account', fn ($query) => $query
                ->where('type', 'expense')
                ->where('is_active', true)
                ->where('allow_posting', true)
                ->whereNotIn('subtype', self::PROTECTED_EXPENSE_SUBTYPES))
            ->pluck('account_id');

        return Account::query()
            ->whereIn('id', $accountIds)
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();
    }

    /** @return Collection<int, Account> */
    public function paymentAccounts(): Collection
    {
        return Account::posting()
            ->where('type', 'asset')
            ->whereIn('subtype', ['cash', 'bank'])
            ->whereDoesntHave('children')
            ->orderBy('code')
            ->get();
    }

    public function expenseAccount(int $accountId): Account
    {
        $account = $this->categories()->firstWhere('id', $accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'expense_account_id' => 'تصنيف المصروف غير متاح للمصروفات السريعة. لا يمكن تسجيل أجور الإنتاج أو تكلفة المبيعات أو تسويات المخزون هنا.',
            ]);
        }

        return $account;
    }

    public function companyPaymentAccount(int $accountId): Account
    {
        $account = $this->paymentAccounts()->firstWhere('id', $accountId);

        if (! $account) {
            throw ValidationException::withMessages([
                'payment_account_id' => 'يجب اختيار حساب خزينة أو بنك نشط وقابل للترحيل.',
            ]);
        }

        return $account;
    }

    public function partnerCurrentAccount(Partner $partner): Account
    {
        $account = $partner->current_account_id
            ? Account::query()->find($partner->current_account_id)
            : AccountMapping::query()->with('account')->where('key', 'partner_current')->first()?->account;

        if (! $account
            || ! in_array($account->type, ['liability', 'equity'], true)
            || ! $account->is_active
            || ! $account->allow_posting
            || $account->children()->exists()) {
            throw ValidationException::withMessages([
                'paid_by_partner_id' => 'لا يوجد حساب جارٍ صالح ومربوط بهذا الشخص.',
            ]);
        }

        return $account;
    }
}
