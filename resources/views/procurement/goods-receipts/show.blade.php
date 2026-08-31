@extends('layouts.dashboard')

@section('title', $goodsReceipt->receipt_number)

@section('content')
    <div class="page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <div class="d-flex align-items-center gap-2"><h1 class="h3 mb-1">{{ $goodsReceipt->receipt_number }}</h1>@if ($goodsReceipt->supplierInvoice)<span class="badge bg-primary">استلام مباشر</span>@endif</div>
            <p class="text-muted mb-0">
                {{ $goodsReceipt->supplier->name }} ·
                @if ($goodsReceipt->supplierInvoice)
                    فاتورة <a href="{{ route('supplier-invoices.show', $goodsReceipt->supplierInvoice) }}">{{ $goodsReceipt->supplierInvoice->invoice_number }}</a>
                @elseif ($goodsReceipt->purchaseOrder)
                    أمر شراء <a href="{{ route('purchase-orders.show', $goodsReceipt->purchaseOrder) }}">{{ $goodsReceipt->purchaseOrder->purchase_order_number }}</a>
                @else
                    مستند تاريخي
                @endif
            </p>
        </div>
        @if ($goodsReceipt->status === 'draft')
            <form method="POST" action="{{ route('goods-receipts.confirm', $goodsReceipt) }}">
                @csrf
                <button class="btn btn-success">تأكيد وترحيل مخزون الخامات</button>
            </form>
        @endif
    </div>
    @if ($goodsReceipt->supplierInvoice)
        <div class="alert alert-light border"><i class="bi bi-link-45deg me-1"></i>تم إنشاء هذا الإذن من فاتورة المورد، وتكلفة كل بند مطابقة لسعر الوحدة في الفاتورة.</div>
    @endif
    <div class="card">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead><tr><th>الخامة</th><th>الوحدة</th><th>المطلوب</th><th>المستلم</th><th>المقبول</th><th>المرفوض</th><th>التكلفة</th></tr></thead>
                <tbody>
                    @foreach ($goodsReceipt->items as $item)
                        <tr><td>{{ $item->material->code }} — {{ $item->material->name }}</td><td>{{ $item->material->unit->label() }}</td><td>{{ $item->ordered_quantity }}</td><td>{{ $item->received_quantity }}</td><td>{{ $item->accepted_quantity }}</td><td>{{ $item->rejected_quantity }}</td><td>{{ number_format((float) $item->unit_cost, 2) }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
