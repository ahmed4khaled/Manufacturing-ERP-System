@extends('layouts.dashboard')

@section('title', 'تسجيل مصروف')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">تسجيل مصروف سريع</h1>
            <p class="text-muted mb-0">أدخل العملية فقط؛ النظام ينشئ القيد المتوازن تلقائيًا.</p>
        </div>
        <a href="{{ route('accounting.expenses') }}" class="btn btn-outline-secondary">سجل المصروفات</a>
    </div>

    @if ($partners->isEmpty())
        <div class="alert alert-warning">لا يوجد شخص/شريك نشط لاختياره كدافع. أضف الشركاء من شاشة حسابات الشركاء أولًا.</div>
    @endif
    @if ($categories->isEmpty())
        <div class="alert alert-warning">لا توجد تصنيفات مصروفات تشغيلية مربوطة بدليل الحسابات.</div>
    @endif

    <div class="card">
        <div class="card-body">
            <form method="POST" action="{{ route('accounting.expenses.store') }}" id="quick-expense-form" class="row g-3">
                @csrf
                <input type="hidden" name="submission_token" value="{{ old('submission_token', $submissionToken) }}">

                <div class="col-md-4">
                    <label class="form-label">التاريخ <span class="text-danger">*</span></label>
                    <input type="date" name="expense_date" value="{{ old('expense_date', now()->toDateString()) }}" class="form-control @error('expense_date') is-invalid @enderror" required>
                    @error('expense_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">نوع المصروف <span class="text-danger">*</span></label>
                    <select name="expense_account_id" class="form-select @error('expense_account_id') is-invalid @enderror" required>
                        <option value="">اختر التصنيف</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) old('expense_account_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                    @error('expense_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">أجور الإنتاج ومدفوعات الموردين غير متاحة هنا.</div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">المبلغ <span class="text-danger">*</span></label>
                    <input type="number" min="0.01" step="0.01" name="amount" value="{{ old('amount') }}" class="form-control @error('amount') is-invalid @enderror" required>
                    @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6">
                    <label class="form-label">دافع المصروف <span class="text-danger">*</span></label>
                    <select name="paid_by_partner_id" id="paid-by-partner" class="form-select @error('paid_by_partner_id') is-invalid @enderror" required>
                        <option value="">اختر الشخص</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected((string) old('paid_by_partner_id') === (string) $partner->id)>{{ $partner->name }}</option>
                        @endforeach
                    </select>
                    @error('paid_by_partner_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    <div class="form-text">دافع المصروف مستقل عن المستخدم الذي أدخل العملية.</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">مصدر الأموال <span class="text-danger">*</span></label>
                    <select name="funding_source" id="funding-source" class="form-select @error('funding_source') is-invalid @enderror" required>
                        @foreach (\App\Enums\ExpenseFundingSource::cases() as $source)
                            <option value="{{ $source->value }}" @selected(old('funding_source', 'company') === $source->value)>{{ $source->label() }}</option>
                        @endforeach
                    </select>
                    @error('funding_source')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-md-6" id="company-account-field">
                    <label class="form-label">حساب الدفع <span class="text-danger">*</span></label>
                    <select name="payment_account_id" id="payment-account" class="form-select @error('payment_account_id') is-invalid @enderror">
                        <option value="">اختر الخزينة أو البنك</option>
                        @foreach ($paymentAccounts as $account)
                            <option value="{{ $account->id }}" @selected((string) old('payment_account_id') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                        @endforeach
                    </select>
                    @error('payment_account_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-6 d-none" id="personal-account-field">
                    <label class="form-label">الشخص المستحق</label>
                    <div class="form-control bg-light" id="personal-account-name">اختر دافع المصروف</div>
                    <div class="form-text">سيُدائن حسابه الجاري، ولن تتأثر خزينة الشركة الآن.</div>
                </div>

                <div class="col-md-8">
                    <label class="form-label">البيان <span class="text-danger">*</span></label>
                    <textarea name="description" rows="2" class="form-control @error('description') is-invalid @enderror" required>{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="col-md-4">
                    <label class="form-label">المرجع</label>
                    <input name="reference" value="{{ old('reference') }}" class="form-control @error('reference') is-invalid @enderror">
                    @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="col-12">
                    <button class="btn btn-primary" @disabled($partners->isEmpty() || $categories->isEmpty())>
                        <i class="bi bi-check2-circle"></i> تسجيل المصروف
                    </button>
                </div>
            </form>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const funding = document.getElementById('funding-source');
            const companyField = document.getElementById('company-account-field');
            const personalField = document.getElementById('personal-account-field');
            const paymentAccount = document.getElementById('payment-account');
            const paidBy = document.getElementById('paid-by-partner');
            const personalName = document.getElementById('personal-account-name');

            const sync = () => {
                const personal = funding.value === 'personal';
                companyField.classList.toggle('d-none', personal);
                personalField.classList.toggle('d-none', !personal);
                paymentAccount.disabled = personal;
                paymentAccount.required = !personal;
                personalName.textContent = paidBy.selectedOptions[0]?.text || 'اختر دافع المصروف';
            };

            funding.addEventListener('change', sync);
            paidBy.addEventListener('change', sync);
            sync();
        })();
    </script>
@endpush
