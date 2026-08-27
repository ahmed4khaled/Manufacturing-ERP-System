@extends('statistics.layout')

@section('statistics-title', 'إحصائيات العملاء')
@section('statistics-description', 'ترتيب العملاء حسب المبيعات والكميات والفواتير والأرصدة والتحصيلات.')

@section('statistics-content')
<div class="row g-3 mb-3">
    @foreach([['أعلى عميل مبيعات', $statistics['customers']['top_sales']->first(), 'sales'], ['أعلى كمية شراء', $statistics['customers']['top_quantity']->first(), 'quantity'], ['أكثر عدد فواتير', $statistics['customers']['top_invoices']->first(), 'invoices'], ['أعلى تحصيلات', $statistics['customers']['top_payments']->first(), 'payments']] as [$label, $row, $field])
        <div class="col-sm-6 col-xl-3"><div class="card h-100"><div class="card-body rank-summary"><small class="text-muted">{{ $label }}</small><div class="fw-bold mt-2">{{ $row?->name ?? '—' }}</div><div>{{ $row ? (in_array($field, ['sales', 'payments']) ? $money($row->{$field}) : $number($row->{$field}, $field === 'invoices' ? 0 : 3)) : '—' }}</div></div></div></div>
    @endforeach
</div>
<div class="card"><div class="card-header bg-white d-flex justify-content-between"><strong>ترتيب العملاء</strong><span>إجمالي الرصيد: {{ $money($statistics['customers']['total_outstanding']) }}</span></div><div class="table-responsive"><table class="table table-hover mb-0 stats-table"><thead><tr><th>#</th><th>العميل</th><th>المبيعات</th><th>التحصيلات</th><th>الرصيد الحالي</th><th>الفواتير</th><th>الكمية</th></tr></thead><tbody>@forelse($statistics['customers']['ranking'] as $row)<tr><td>{{ $loop->iteration }}</td><td><a href="{{ route('customers.show', $row->id) }}">{{ $row->name }}</a></td><td>{{ $money($row->sales) }}</td><td>{{ $money($row->payments) }}</td><td class="{{ $row->outstanding > 0 ? 'text-danger' : '' }}">{{ $money($row->outstanding) }}</td><td>{{ $row->invoices }}</td><td>{{ $number($row->quantity) }}</td></tr>@empty<tr><td colspan="7" class="text-center text-muted py-4">لا توجد بيانات عملاء للفترة.</td></tr>@endforelse</tbody></table></div></div>
<div class="row g-3 mt-3">
    @foreach([['أعلى الأرصدة المستحقة', $statistics['customers']['top_outstanding'], 'outstanding'], ['أعلى العملاء سدادًا', $statistics['customers']['top_payments'], 'payments']] as [$title, $rows, $field])
        <div class="col-lg-6"><div class="card h-100"><div class="card-header bg-white"><strong>{{ $title }}</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>العميل</th><th>القيمة</th></tr></thead><tbody>@forelse($rows as $row)<tr><td>{{ $row->name }}</td><td>{{ $money($row->{$field}) }}</td></tr>@empty<tr><td colspan="2" class="text-center text-muted">لا توجد بيانات.</td></tr>@endforelse</tbody></table></div></div></div>
    @endforeach
</div>
@endsection
