@extends('layouts.dashboard')

@section('title', $expense->expense_number)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">{{ $expense->expense_number }}</h1>
            <span class="badge {{ $expense->status === \App\Models\Expense::STATUS_POSTED ? 'bg-success' : 'bg-secondary' }}">
                {{ $expense->status === \App\Models\Expense::STATUS_POSTED ? 'مرحّل' : 'معكوس' }}
            </span>
        </div>
        <a href="{{ route('accounting.expenses') }}" class="btn btn-outline-secondary">سجل المصروفات</a>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-4">
                @foreach ([
                    'التاريخ' => $expense->expense_date->format('Y-m-d'),
                    'التصنيف' => $expense->expenseAccount->name,
                    'المبلغ' => number_format((float) $expense->amount, 2),
                    'دافع المصروف' => $expense->paidByPartner?->name ?? '—',
                    'أدخل بواسطة' => $expense->creator?->name ?? '—',
                    'مصدر الأموال' => $expense->funding_source?->label() ?? 'خزينة / بنك الشركة',
                    ($expense->funding_source === \App\Enums\ExpenseFundingSource::PERSONAL ? 'حساب الشخص' : 'حساب الدفع') => $expense->paymentAccount->name,
                    'المرجع' => $expense->reference ?: '—',
                ] as $label => $value)
                    <div class="col-md-3"><div class="text-muted small">{{ $label }}</div><strong>{{ $value }}</strong></div>
                @endforeach
                <div class="col-12"><div class="text-muted small">البيان</div><strong>{{ $expense->description }}</strong></div>
            </div>
        </div>
    </div>

    @if ($expense->journalEntry)
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between">
                <strong>القيد المحاسبي</strong>
                <a href="{{ route('accounting.journals', ['entry_id' => $expense->journalEntry->id]) }}">{{ $expense->journalEntry->entry_number }}</a>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>الحساب</th><th class="text-end">مدين</th><th class="text-end">دائن</th></tr></thead>
                    <tbody>
                        @foreach ($expense->journalEntry->lines as $line)
                            <tr>
                                <td>{{ $line->account->code }} - {{ $line->account->name }}</td>
                                <td class="text-end">{{ number_format((float) $line->debit, 2) }}</td>
                                <td class="text-end">{{ number_format((float) $line->credit, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($expense->reversalJournalEntry)
        <div class="alert alert-secondary">تم عكس المصروف بالقيد {{ $expense->reversalJournalEntry->entry_number }} في {{ $expense->reversalJournalEntry->entry_date->format('Y-m-d') }}.</div>
    @endif

    @canany(['expenses.reverse', 'accounting.reverse'])
        @if ($expense->status === \App\Models\Expense::STATUS_POSTED)
            <div class="card border-danger">
                <div class="card-body">
                    <h2 class="h5">عكس المصروف</h2>
                    <p class="text-muted">لا يمكن تعديل المصروف المرحّل. التصحيح يتم بعكسه ثم إنشاء مصروف صحيح.</p>
                    <form method="POST" action="{{ route('accounting.expenses.reverse', $expense) }}" class="d-flex gap-2">
                        @csrf
                        <input type="date" name="reversal_date" value="{{ now()->toDateString() }}" class="form-control w-auto" required>
                        <button class="btn btn-outline-danger">إنشاء قيد عكس</button>
                    </form>
                </div>
            </div>
        @endif
    @endcanany
@endsection
