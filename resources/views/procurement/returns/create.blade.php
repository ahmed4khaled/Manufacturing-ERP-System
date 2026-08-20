@extends('layouts.dashboard')
@section('title','إنشاء مرتجع مشتريات')
@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">إنشاء مرتجع مشتريات</h1>
        <p class="text-muted mb-0">اختر إذن استلام مؤكدًا وحدد كميات الخامات المتاحة للمرتجع.</p>
    </div>
</div>

<form method="POST" action="{{ route('purchase-returns.store') }}" class="mt-4">
    @csrf
    
    {{-- Return Header Section --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">المورد *</label>
                    <select name="supplier_id" id="supplier" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">اختر المورد</option>
                        @foreach($receipts->groupBy('supplier_id') as $supplierId => $supplierReceipts)
                            <option value="{{ $supplierId }}" @selected(old('supplier_id') == $supplierId)>
                                {{ $supplierReceipts->first()->supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('supplier_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">إذن الاستلام *</label>
                    <select name="goods_receipt_id" id="receipt" class="form-select @error('goods_receipt_id') is-invalid @enderror" required>
                        <option value="">اختر إذن الاستلام</option>
                        @foreach($receipts as $receipt)
                            <option value="{{ $receipt->id }}" data-supplier="{{ $receipt->supplier_id }}" @selected(old('goods_receipt_id') == $receipt->id)>
                                {{ $receipt->receipt_number }} — {{ $receipt->supplier->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('goods_receipt_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">تاريخ المرتجع *</label>
                    <input type="date" name="return_date" class="form-control @error('return_date') is-invalid @enderror" value="{{ old('return_date', now()->toDateString()) }}" required>
                    @error('return_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">السبب *</label>
                    <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" placeholder="مثال: مشكلة جودة أو تلف أثناء النقل" required>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-12">
                    <label class="form-label fw-bold">ملاحظات</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="ملاحظات إضافية عن هذا المرتجع">{{ old('notes') }}</textarea>
                    @error('notes')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>
    </div>

    {{-- Return Items Section --}}
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">بنود المرتجع</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 25%;">الخامة</th>
                            <th style="width: 12%;" class="text-end">الكمية المقبولة</th>
                            <th style="width: 12%;" class="text-end">المرتجع سابقًا</th>
                            <th style="width: 12%;" class="text-end">المتاح للمرتجع</th>
                            <th style="width: 12%;" class="text-end">كمية المرتجع *</th>
                            <th style="width: 12%;" class="text-end">تكلفة الوحدة</th>
                            <th style="width: 15%;">السبب</th>
                        </tr>
                    </thead>
                    <tbody id="items">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <small>اختر إذن استلام لعرض الخامات القابلة للمرتجع</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            @error('items')
                <div class="alert alert-danger small mb-0">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-circle"></i> حفظ المرتجع كمسودة
        </button>
        <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary">إلغاء</a>
    </div>
</form>

@push('scripts')
<script>
const receiptData = @json($receiptData);

const supplierSelect = document.getElementById('supplier');
const receiptSelect = document.getElementById('receipt');
const itemsTable = document.getElementById('items');

function renderReceiptItems(receiptId) {
    const rows = receiptData[receiptId] || [];
    let html = '';
    
    if (rows.length === 0 || rows.every(item => parseFloat(item.available) <= 0)) {
        html = `<tr><td colspan="7" class="text-center text-muted py-4">
            <small>لا توجد خامات متاحة للمرتجع في إذن الاستلام هذا</small>
        </td></tr>`;
        itemsTable.innerHTML = html;
        return;
    }
    
    rows.forEach((item, index) => {
        if (parseFloat(item.available) <= 0) return;
        
        html += `<tr>
            <td>
                <div class="fw-bold">${item.code}</div>
                <div class="small text-muted">${item.label}</div>
                <small>(${item.unit})</small>
                <input type="hidden" name="items[${index}][material_id]" value="${item.material_id}">
            </td>
            <td class="text-end">${parseFloat(item.accepted).toFixed(3)}</td>
            <td class="text-end">${parseFloat(item.already_returned).toFixed(3)}</td>
            <td class="text-end fw-bold text-success">${parseFloat(item.available).toFixed(3)}</td>
            <td class="text-end">
                <input type="number" name="items[${index}][quantity]" step="0.001" min="0.001" max="${item.available}" 
                    class="form-control text-end" value="${item.available}" required>
            </td>
            <td class="text-end">
                <input type="number" name="items[${index}][unit_cost]" step="0.01" min="0" value="${item.unit_cost}" 
                    class="form-control text-end" required>
            </td>
            <td>
                <input type="text" name="items[${index}][reason]" class="form-control" placeholder="السبب">
            </td>
        </tr>`;
    });
    
    itemsTable.innerHTML = html;
}

receiptSelect.addEventListener('change', function() {
    renderReceiptItems(this.value);
    const selected = this.options[this.selectedIndex];
    if (selected && selected.dataset.supplier) {
        supplierSelect.value = selected.dataset.supplier;
    }
});

supplierSelect.addEventListener('change', function() {
    const value = this.value;
    Array.from(receiptSelect.options).forEach(option => {
        if (option.value === '') return;
        option.hidden = value && option.dataset.supplier && option.dataset.supplier != value;
    });
    if (value) {
        const firstMatch = Array.from(receiptSelect.options).find(
            option => option.value !== '' && option.dataset.supplier == value
        );
        if (firstMatch) {
            receiptSelect.value = firstMatch.value;
            renderReceiptItems(firstMatch.value);
        } else {
            itemsTable.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
                <small>لا توجد أذون استلام مؤكدة لهذا المورد</small>
            </td></tr>`;
        }
    } else {
        itemsTable.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
            <small>اختر إذن استلام لعرض الخامات القابلة للمرتجع</small>
        </td></tr>`;
    }
});
</script>
@endpush
@endsection
