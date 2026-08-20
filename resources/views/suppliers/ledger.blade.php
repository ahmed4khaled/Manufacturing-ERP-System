@extends('layouts.dashboard')

@section('title', 'كشف حساب المورد')

@section('content')

<div class="page-header d-flex justify-content-between align-items-start">
    <div>
        <h1 class="h3 mb-1">كشف حساب المورد</h1>
        <p class="text-muted mb-0">
            {{ $supplier->name }}
        </p>
    </div>

    <a href="{{ route('suppliers.show', $supplier) }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i>
        العودة إلى المورد
    </a>
</div>

<div class="row g-3 mt-2">

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">المورد</div>
                <div class="fw-bold fs-5">
                    {{ $supplier->name }}
                </div>

                @if($supplier->code)
                    <div class="small text-muted mt-1">
                        {{ $supplier->code }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">إجمالي الحركات</div>
                <div class="fw-bold fs-4">
                    {{ $transactions->count() }}
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <div class="text-muted small">الرصيد الحالي</div>

                <div class="fw-bold fs-4
                    {{ $currentBalance > 0 ? 'text-danger' : 'text-success' }}">
                    {{ number_format($currentBalance, 2) }}
                </div>

                <div class="small text-muted">
                    {{ $currentBalance > 0 ? 'مبلغ مستحق' : 'مسدد' }}
                </div>
            </div>
        </div>
    </div>

</div>

<div class="card mt-4">

    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-journal-text"></i>
            حركات الحساب
        </h5>
    </div>

    <div class="card-body p-0">

        <div class="table-responsive">

            <table class="table table-hover align-middle mb-0">

                <thead class="table-light">
                    <tr>
                        <th>التاريخ</th>
                        <th>المرجع</th>
                        <th>النوع</th>
                        <th>الوصف</th>
                        <th class="text-end">مدين</th>
                        <th class="text-end">دائن</th>
                        <th class="text-end">الرصيد</th>
                    </tr>
                </thead>

                <tbody>

                @forelse($transactions as $transaction)

                    <tr>

                        <td>
                            {{ $transaction['date']->format('Y-m-d') }}
                        </td>

                        <td class="fw-semibold">
                            {{ $transaction['reference'] }}
                        </td>

                        <td>

                            @if($transaction['type'] === 'Invoice')

                                <span class="badge bg-danger">
                                    الفاتورة
                                </span>

                            @elseif($transaction['type'] === 'Payment')

                                <span class="badge bg-success">
                                    السداد
                                </span>

                            @else

                                <span class="badge bg-warning text-dark">
                                    مرتجع مشتريات
                                </span>

                            @endif

                        </td>

                        <td>
                            {{ $transaction['description'] }}
                        </td>

                        <td class="text-end">
                            @if($transaction['debit'] > 0)
                                {{ number_format($transaction['debit'], 2) }}
                            @else
                                —
                            @endif
                        </td>

                        <td class="text-end">
                            @if($transaction['credit'] > 0)
                                {{ number_format($transaction['credit'], 2) }}
                            @else
                                —
                            @endif
                        </td>

                        <td class="text-end fw-bold">
                            {{ number_format($transaction['balance'], 2) }}
                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="7" class="text-center text-muted py-5">
                            لا توجد حركات حساب حتى الآن.
                        </td>
                    </tr>

                @endforelse

                </tbody>

                @if($transactions->isNotEmpty())

                    <tfoot class="table-light fw-bold">

                        <tr>

                            <td colspan="4" class="text-end">
                                الرصيد الحالي
                            </td>

                            <td colspan="3" class="text-end">
                                {{ number_format($currentBalance, 2) }}
                            </td>

                        </tr>

                    </tfoot>

                @endif

            </table>

        </div>

    </div>

</div>

@endsection