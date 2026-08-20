@extends('layouts.dashboard')
@section('title',$purchaseReturn->return_number)
@section('content')

<div class="page-header d-flex justify-content-between align-items-start gap-3">
    <div>
        <h1 class="h3 mb-1">{{ $purchaseReturn->return_number }}</h1>
        <div class="text-muted mb-2">
            <strong>المورد:</strong> {{ $purchaseReturn->supplier->name }}<br>
            <strong>تاريخ المرتجع:</strong> {{ $purchaseReturn->return_date->format('Y-m-d') }}
        </div>
        
        {{-- Status Badge --}}
        @if($purchaseReturn->status === 'draft')
            <span class="badge bg-warning text-dark">
                <i class="bi bi-clock-history"></i> مسودة
            </span>
        @elseif($purchaseReturn->status === 'confirmed')
            <span class="badge bg-success">
                <i class="bi bi-check-circle"></i> مؤكد
            </span>
        @endif
    </div>
    
    {{-- Actions Card --}}
    @if($purchaseReturn->status === 'draft')
        <div>
            <form method="POST" action="{{ route('purchase-returns.confirm', $purchaseReturn) }}" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-success" onclick="return confirm('هل تريد التأكيد وترحيل حركات المخزون؟')">
                    <i class="bi bi-check-circle"></i> تأكيد وترحيل إلى المخزون
                </button>
            </form>
        </div>
    @endif
</div>

{{-- Details Card --}}
<div class="card mt-4">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <div class="mb-3">
                    <strong class="d-block text-muted small">المرجع</strong>
                    <span>{{ $purchaseReturn->goodsReceipt?->receipt_number ?? 'N/A' }}</span>
                    <small class="text-muted"> (إذن استلام)</small>
                </div>
                <div class="mb-3">
                    <strong class="d-block text-muted small">الحالة</strong>
                    @if($purchaseReturn->status === 'draft')
                        <span class="badge bg-warning text-dark">مسودة - غير مرحّلة</span>
                    @elseif($purchaseReturn->status === 'confirmed')
                        <span class="badge bg-success">مؤكد - تم الترحيل</span>
                    @endif
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <strong class="d-block text-muted small">السبب</strong>
                    {{ $purchaseReturn->reason }}
                </div>
                @if($purchaseReturn->notes)
                    <div class="mb-3">
                        <strong class="d-block text-muted small">ملاحظات</strong>
                        {{ $purchaseReturn->notes }}
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Return Items Table --}}
<div class="card mt-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="bi bi-box-seam"></i> بنود المرتجع
        </h5>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th style="width: 35%;">الخامة</th>
                        <th style="width: 10%;" class="text-end">الوحدة</th>
                        <th style="width: 15%;" class="text-end">كمية المرتجع</th>
                        <th style="width: 15%;" class="text-end">تكلفة الوحدة</th>
                        <th style="width: 15%;" class="text-end">إجمالي البند</th>
                        <th style="width: 10%;">السبب</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                        $totalAmount = 0;
                    @endphp
                    @foreach($purchaseReturn->items as $item)
                        @php
                            $lineTotal = bcmul($item->quantity, $item->unit_cost, 2);
                            $totalAmount = bcadd($totalAmount, $lineTotal, 2);
                        @endphp
                        <tr>
                            <td>
                                <div class="fw-bold">{{ $item->material->code }}</div>
                                <div class="small text-muted">{{ $item->material->name }}</div>
                            </td>
                            <td class="text-end">{{ $item->material->unit->label() }}</td>
                            <td class="text-end fw-bold">{{ number_format($item->quantity, 3) }}</td>
                            <td class="text-end">{{ number_format($item->unit_cost, 2) }}</td>
                            <td class="text-end">{{ number_format($lineTotal, 2) }}</td>
                            <td>
                                @if($item->reason)
                                    <small>{{ $item->reason }}</small>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="table-light fw-bold">
                    <tr>
                        <td colspan="4" class="text-end">إجمالي المرتجع:</td>
                        <td class="text-end">{{ number_format($totalAmount, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

{{-- Back Button --}}
<div class="mt-4">
    <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> العودة إلى المرتجعات
    </a>
</div>

@endsection
