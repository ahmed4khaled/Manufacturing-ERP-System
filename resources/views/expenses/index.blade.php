@extends('layouts.dashboard')

@section('title', 'سجل المصروفات')

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">المصروفات السريعة</h1>
            <p class="text-muted mb-0">سجل المصروفات التشغيلية المرتبطة تلقائيًا بالقيود المحاسبية.</p>
        </div>
        @canany(['expenses.create', 'accounting.post'])
            <a href="{{ route('accounting.expenses.create') }}" class="btn btn-primary">
                <i class="bi bi-plus-lg"></i> تسجيل مصروف
            </a>
        @endcanany
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">من تاريخ</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">إلى تاريخ</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-md-2">
                    <label class="form-label">التصنيف</label>
                    <select name="expense_account_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}" @selected((string) request('expense_account_id') === (string) $category->id)>{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">دافع المصروف</label>
                    <select name="paid_by_partner_id" class="form-select">
                        <option value="">الكل</option>
                        @foreach ($partners as $partner)
                            <option value="{{ $partner->id }}" @selected((string) request('paid_by_partner_id') === (string) $partner->id)>{{ $partner->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">مصدر الأموال</label>
                    <select name="funding_source" class="form-select">
                        <option value="">الكل</option>
                        @foreach (\App\Enums\ExpenseFundingSource::cases() as $source)
                            <option value="{{ $source->value }}" @selected(request('funding_source') === $source->value)>{{ $source->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">الحالة</label>
                    <select name="status" class="form-select">
                        <option value="">الكل</option>
                        <option value="posted" @selected(request('status') === 'posted')>مرحّل</option>
                        <option value="reversed" @selected(request('status') === 'reversed')>معكوس</option>
                    </select>
                </div>
                <div class="col-12 d-flex justify-content-between align-items-center">
                    <div>
                        <button class="btn btn-outline-primary">تطبيق الفلاتر</button>
                        <a href="{{ route('accounting.expenses') }}" class="btn btn-link">مسح</a>
                    </div>
                    <strong>إجمالي المصروفات المرحلة: {{ number_format((float) $total, 2) }}</strong>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>رقم المصروف</th>
                        <th>التاريخ</th>
                        <th>النوع</th>
                        <th>البيان</th>
                        <th>المبلغ</th>
                        <th>دافع المصروف</th>
                        <th>مصدر الأموال</th>
                        <th>الحالة</th>
                        <th>القيد</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr>
                            <td><a href="{{ route('accounting.expenses.show', $expense) }}">{{ $expense->expense_number }}</a></td>
                            <td>{{ $expense->expense_date->format('Y-m-d') }}</td>
                            <td>{{ $expense->expenseAccount->name }}</td>
                            <td>{{ Str::limit($expense->description, 45) }}</td>
                            <td>{{ number_format((float) $expense->amount, 2) }}</td>
                            <td>{{ $expense->paidByPartner?->name ?? '—' }}</td>
                            <td>{{ $expense->funding_source?->label() ?? 'خزينة / بنك الشركة' }}</td>
                            <td>
                                <span class="badge {{ $expense->status === \App\Models\Expense::STATUS_POSTED ? 'bg-success' : 'bg-secondary' }}">
                                    {{ $expense->status === \App\Models\Expense::STATUS_POSTED ? 'مرحّل' : 'معكوس' }}
                                </span>
                            </td>
                            <td>{{ $expense->journalEntry?->entry_number ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="text-center text-muted py-5">لا توجد مصروفات مطابقة.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($expenses->hasPages())
            <div class="card-footer">{{ $expenses->links('pagination::bootstrap-5') }}</div>
        @endif
    </div>
@endsection
