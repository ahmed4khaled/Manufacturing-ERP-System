<?php

namespace App\Actions\Cutover;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\CutoverBatch;
use App\Models\CutoverOpeningBalance;
use App\Models\Partner;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AddOpeningBalanceAction
{
    public function handle(CutoverBatch $batch, array $input): CutoverOpeningBalance
    {
        return DB::transaction(function () use ($batch, $input): CutoverOpeningBalance {
            $batch = CutoverBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($batch->status !== CutoverBatch::STATUS_DRAFT) {
                throw ValidationException::withMessages(['cutover' => 'يمكن إضافة الأرصدة إلى دفعة مسودة فقط.']);
            }

            return $batch->balances()->create($this->prepare($batch, $input));
        });
    }

    public function prepare(CutoverBatch $batch, array $input): array
    {
        $type = (string) ($input['type'] ?? '');
        Validator::make($input, [
            'type' => ['required', Rule::in(CutoverOpeningBalance::TYPES)],
            'layer_reference' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $reference = trim((string) $input['layer_reference']);
        if ($batch->balances()->where('layer_reference', $reference)->exists()) {
            throw ValidationException::withMessages(['layer_reference' => 'المرجع مستخدم بالفعل داخل هذه الدفعة.']);
        }

        $data = [
            'type' => $type,
            'layer_reference' => $reference,
            'description' => filled($input['description'] ?? null) ? trim((string) $input['description']) : null,
            'amount' => '0.00',
        ];

        if (in_array($type, ['material', 'finished_goods'], true)) {
            $key = $type === 'material' ? 'material_id' : 'product_variant_id';
            $table = $type === 'material' ? 'materials' : 'product_variants';
            Validator::make($input, [
                $key => ['required', 'integer', "exists:{$table},id"],
                'quantity' => ['required', 'numeric', 'gt:0'],
                'unit_cost' => ['nullable', 'numeric', 'gte:0'],
            ])->validate();
            $data[$key] = (int) $input[$key];
            $data['quantity'] = bcadd((string) $input['quantity'], '0', 3);
            $data['unit_cost'] = filled($input['unit_cost'] ?? null) ? bcadd((string) $input['unit_cost'], '0', 4) : null;
            $data['amount'] = $data['unit_cost'] === null ? '0.00' : bcmul($data['quantity'], $data['unit_cost'], 2);
            $data['account_id'] = $this->mappedAccount($type === 'material' ? 'raw_material_inventory' : 'finished_goods_inventory')->id;

            return $data;
        }

        if ($type === 'cash_bank') {
            Validator::make($input, [
                'account_id' => ['required', 'integer', 'exists:accounts,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
            ])->validate();
            $account = $this->postingAccount((int) $input['account_id']);
            if (! in_array($account->subtype, ['cash', 'bank'], true)) {
                throw ValidationException::withMessages(['account_id' => 'اختر حساب خزينة أو بنك قابلًا للترحيل.']);
            }
            $data['account_id'] = $account->id;
            $data['amount'] = bcadd((string) $input['amount'], '0', 2);

            return $data;
        }

        if ($type === 'wip') {
            Validator::make($input, [
                'production_order_id' => ['required', 'integer', 'exists:production_orders,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
            ])->validate();
            if ($batch->balances()->where('type', 'wip')->where('production_order_id', $input['production_order_id'])->exists()) {
                throw ValidationException::withMessages(['production_order_id' => 'تم إدخال WIP افتتاحي لهذا الأمر داخل الدفعة.']);
            }
            $data['production_order_id'] = (int) $input['production_order_id'];
            $data['account_id'] = $this->mappedAccount('work_in_progress')->id;
            $data['amount'] = bcadd((string) $input['amount'], '0', 2);

            return $data;
        }

        if ($type === 'worker_payable') {
            Validator::make($input, [
                'worker_id' => ['required', 'integer', 'exists:workers,id'],
                'production_order_id' => ['nullable', 'integer', 'exists:production_orders,id'],
                'amount' => ['required', 'numeric', 'gt:0'],
            ])->validate();
            $data['worker_id'] = (int) $input['worker_id'];
            $data['production_order_id'] = filled($input['production_order_id'] ?? null) ? (int) $input['production_order_id'] : null;
            $data['account_id'] = $this->mappedAccount('production_wages_payable')->id;
            $data['amount'] = bcadd((string) $input['amount'], '0', 2);

            return $data;
        }

        if ($type === 'partner') {
            Validator::make($input, [
                'partner_id' => ['required', 'integer', 'exists:partners,id'],
                'partner_balance_type' => ['required', Rule::in(['capital', 'current', 'withdrawals'])],
                'amount' => ['required', 'numeric', 'not_in:0,0.00'],
            ])->validate();
            $partner = Partner::query()->findOrFail($input['partner_id']);
            $balanceType = (string) $input['partner_balance_type'];
            $accountId = match ($balanceType) {
                'capital' => $partner->capital_account_id ?: $this->mappedAccount('partner_capital')->id,
                'current' => $partner->current_account_id ?: $this->mappedAccount('partner_current')->id,
                default => $partner->withdrawals_account_id ?: $this->mappedAccount('partner_withdrawals')->id,
            };
            $data['partner_id'] = $partner->id;
            $data['partner_balance_type'] = $balanceType;
            $data['account_id'] = $this->postingAccount((int) $accountId)->id;
            $data['amount'] = bcadd((string) $input['amount'], '0', 2);

            return $data;
        }

        Validator::make($input, [
            'account_id' => ['required', 'integer', 'exists:accounts,id'],
            'amount' => ['required', 'numeric', 'not_in:0,0.00'],
        ])->validate();
        $account = $this->postingAccount((int) $input['account_id']);
        $reserved = AccountMapping::query()->whereIn('key', [
            'accounts_receivable', 'accounts_payable', 'cash', 'bank', 'raw_material_inventory',
            'finished_goods_inventory', 'work_in_progress', 'production_wages_payable',
            'partner_capital', 'partner_current', 'partner_withdrawals', 'opening_balance_clearing',
        ])->pluck('account_id')->all();
        if (in_array($account->id, $reserved, true)) {
            throw ValidationException::withMessages(['account_id' => 'هذا الحساب له شاشة افتتاحية متخصصة أو أنه حساب عملاء/موردين مستبعد من الـCutover.']);
        }
        $data['account_id'] = $account->id;
        $data['amount'] = bcadd((string) $input['amount'], '0', 2);

        return $data;
    }

    private function mappedAccount(string $key): Account
    {
        $account = AccountMapping::query()->with('account')->where('key', $key)->first()?->account;
        if (! $account) {
            throw ValidationException::withMessages(['account' => "الربط المحاسبي {$key} غير موجود."]);
        }

        return $this->postingAccount($account->id);
    }

    private function postingAccount(int $id): Account
    {
        $account = Account::query()->findOrFail($id);
        if (! $account->is_active || ! $account->allow_posting || $account->children()->exists()) {
            throw ValidationException::withMessages(['account_id' => 'الحساب غير نشط أو غير قابل للترحيل المباشر.']);
        }

        return $account;
    }
}
