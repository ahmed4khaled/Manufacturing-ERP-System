@extends('layouts.dashboard')

@section('title', 'أذون استلام الخامات')

@section('content')
    <div class="page-header d-flex justify-content-between align-items-center">
        <div><h1 class="h3 mb-1">أذون استلام الخامات</h1><p class="text-muted mb-0">الاستلامات المباشرة والمخططة من الموردين</p></div>
        <a href="{{ route('goods-receipts.create') }}" class="btn btn-outline-primary">استلام من أمر شراء</a>
    </div>
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>السند</th><th>المصدر</th><th>المورد</th><th>تاريخ الاستلام</th><th>الحالة</th><th></th></tr></thead>
                <tbody>
                    @forelse ($receipts as $receipt)
                        <tr>
                            <td class="fw-semibold"><a href="{{ route('goods-receipts.show', $receipt) }}">{{ $receipt->receipt_number }}</a></td>
                            <td>
                                @if ($receipt->supplierInvoice)
                                    <span class="badge bg-primary">شراء مباشر</span>
                                    <a href="{{ route('supplier-invoices.show', $receipt->supplierInvoice) }}">{{ $receipt->supplierInvoice->invoice_number }}</a>
                                @elseif ($receipt->purchaseOrder)
                                    <span class="badge bg-light text-dark border">أمر شراء</span>
                                    <a href="{{ route('purchase-orders.show', $receipt->purchaseOrder) }}">{{ $receipt->purchaseOrder->purchase_order_number }}</a>
                                @else
                                    <span class="text-muted">مصدر تاريخي</span>
                                @endif
                            </td>
                            <td>{{ $receipt->supplier->name }}</td>
                            <td>{{ $receipt->received_at->format('Y-m-d H:i') }}</td>
                            <td><span class="badge bg-{{ $receipt->status === 'confirmed' ? 'success' : 'secondary' }}">{{ \App\Support\ArabicLabels::status($receipt->status) }}</span></td>
                            <td class="text-end">
                                @if ($receipt->status === 'draft')
                                    <form method="POST" action="{{ route('goods-receipts.confirm', $receipt) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success" onclick="return confirm('هل تريد تأكيد الاستلام وإضافة الكميات المقبولة إلى المخزون؟')">تأكيد</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted py-5">لا توجد أذون استلام حتى الآن.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-body">{{ $receipts->links('pagination::bootstrap-5') }}</div>
    </div>
@endsection
