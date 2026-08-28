@extends('layouts.dashboard')

@section('title', 'طبقة تكلفة خامة #'.$materialCostLayer->id)

@section('breadcrumb')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('material-inventory.cost-layers.index') }}">طبقات تكلفة الخامات</a></li>
        <li class="breadcrumb-item active">#{{ $materialCostLayer->id }}</li>
    </ol>
@endsection

@section('content')
    @php
        $status = $materialCostLayer->unit_cost === null ? ['غير مُقيّمة', 'warning'] : ((float) $materialCostLayer->remaining_quantity > 0 ? ['متاحة', 'success'] : ['مستنفدة', 'secondary']);
    @endphp
    <div class="page-header d-flex justify-content-between align-items-start">
        <div><h1 class="h3 mb-1">طبقة خامة #{{ $materialCostLayer->id }}</h1><p class="text-muted mb-0">{{ $materialCostLayer->material?->code }} — {{ $materialCostLayer->material?->name }}</p></div>
        <span class="badge bg-{{ $status[1] }} fs-6">{{ $status[0] }}</span>
    </div>

    <div class="card mb-4">
        <div class="card-header bg-white"><strong>بيانات الطبقة</strong></div>
        <div class="card-body"><div class="row g-3">
            <div class="col-md-3"><small class="text-muted d-block">الكمية الأصلية</small><strong>{{ number_format($materialCostLayer->original_quantity, 3) }}</strong></div>
            <div class="col-md-3"><small class="text-muted d-block">الكمية المتبقية</small><strong>{{ number_format($materialCostLayer->remaining_quantity, 3) }}</strong></div>
            <div class="col-md-3"><small class="text-muted d-block">تكلفة الوحدة</small><strong>{{ $materialCostLayer->unit_cost !== null ? number_format($materialCostLayer->unit_cost, 4).' ج.م' : 'غير مُقيّمة' }}</strong></div>
            <div class="col-md-3"><small class="text-muted d-block">إجمالي التكلفة الأصلية</small><strong>{{ $materialCostLayer->total_original_cost !== null ? number_format($materialCostLayer->total_original_cost, 2).' ج.م' : 'غير مُقيّمة' }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">إذن الاستلام</small>@if($materialCostLayer->goodsReceipt)<a href="{{ route('goods-receipts.show', $materialCostLayer->goodsReceipt) }}">{{ $materialCostLayer->goodsReceipt->receipt_number }}</a>@else<strong>رصيد قديم / تسوية</strong>@endif</div>
            <div class="col-md-4"><small class="text-muted d-block">حركة المخزون</small><strong>{{ $materialCostLayer->stockMovement ? '#'.$materialCostLayer->stockMovement->id.' — '.($materialCostLayer->stockMovement->reference_number ?? 'بدون مرجع') : '—' }}</strong></div>
            <div class="col-md-4"><small class="text-muted d-block">تاريخ الطبقة</small><strong>{{ $materialCostLayer->layer_date?->format('Y-m-d') }}</strong></div>
        </div></div>
    </div>

    <div class="card">
        <div class="card-header bg-white"><strong>تخصيصات الإنتاج التي استهلكت الطبقة</strong></div>
        <div class="table-responsive"><table class="table align-middle mb-0">
            <thead><tr><th>أمر الإنتاج</th><th>الخامة / العملية</th><th>الاتجاه</th><th class="text-end">الكمية</th><th class="text-end">تكلفة الوحدة</th><th class="text-end">التكلفة</th><th>الحالة</th></tr></thead>
            <tbody>
                @forelse($allocations as $allocation)
                    @php($transaction = $transactions->get($allocation->reference_id))
                    <tr>
                        <td>@if($transaction?->productionOrder)<a href="{{ route('production-orders.show', $transaction->productionOrder) }}">{{ $transaction->productionOrder->order_number }}</a>@else—@endif</td>
                        <td>{{ $transaction?->material?->name ?? $materialCostLayer->material?->name }}<div class="small text-muted">{{ $transaction?->workAssignment?->operation?->operation_name ?? '—' }}</div></td>
                        <td><span class="badge bg-{{ $allocation->direction === 'out' ? 'danger' : 'success' }}">{{ $allocation->direction === 'out' ? 'صرف' : 'مرتجع' }}</span></td>
                        <td class="text-end">{{ number_format($allocation->quantity, 3) }}</td>
                        <td class="text-end">{{ $allocation->unit_cost !== null ? number_format($allocation->unit_cost, 4).' ج.م' : '—' }}</td>
                        <td class="text-end">{{ $allocation->total_cost !== null ? number_format($allocation->total_cost, 2).' ج.م' : '—' }}</td>
                        <td>{{ $allocation->reversed_at ? 'معكوس' : 'مسجل' }}</td>
                    </tr>
                @empty<tr><td colspan="7" class="text-center text-muted py-4">لم تُستهلك هذه الطبقة في الإنتاج.</td></tr>@endforelse
            </tbody>
        </table></div>
    </div>
@endsection
