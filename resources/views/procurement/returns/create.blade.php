@extends('layouts.dashboard')
@section('title','Create Purchase Return')
@section('content')
<div class="page-header">
    <div>
        <h1 class="h3 mb-1">Create Purchase Return</h1>
        <p class="text-muted mb-0">Select a confirmed goods receipt and return material quantities that are still available for return.</p>
    </div>
</div>

<form method="POST" action="{{ route('purchase-returns.store') }}" class="mt-4">
    @csrf
    
    {{-- Return Header Section --}}
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Supplier *</label>
                    <select name="supplier_id" id="supplier" class="form-select @error('supplier_id') is-invalid @enderror" required>
                        <option value="">Select supplier</option>
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
                    <label class="form-label fw-bold">Goods Receipt *</label>
                    <select name="goods_receipt_id" id="receipt" class="form-select @error('goods_receipt_id') is-invalid @enderror" required>
                        <option value="">Select receipt</option>
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
                    <label class="form-label fw-bold">Return Date *</label>
                    <input type="date" name="return_date" class="form-control @error('return_date') is-invalid @enderror" value="{{ old('return_date', now()->toDateString()) }}" required>
                    @error('return_date')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold">Reason *</label>
                    <input type="text" name="reason" class="form-control @error('reason') is-invalid @enderror" value="{{ old('reason') }}" placeholder="e.g., Quality issue, Damaged in transit" required>
                    @error('reason')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>
                
                <div class="col-12">
                    <label class="form-label fw-bold">Notes</label>
                    <textarea name="notes" class="form-control @error('notes') is-invalid @enderror" rows="2" placeholder="Additional notes about this return">{{ old('notes') }}</textarea>
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
            <h5 class="mb-0">Return Items</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 25%;">Material</th>
                            <th style="width: 12%;" class="text-end">Accepted Qty</th>
                            <th style="width: 12%;" class="text-end">Already Returned</th>
                            <th style="width: 12%;" class="text-end">Available to Return</th>
                            <th style="width: 12%;" class="text-end">Return Qty *</th>
                            <th style="width: 12%;" class="text-end">Unit Cost</th>
                            <th style="width: 15%;">Reason</th>
                        </tr>
                    </thead>
                    <tbody id="items">
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">
                                <small>Select a goods receipt to view returnable materials</small>
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
            <i class="bi bi-check-circle"></i> Save Return Draft
        </button>
        <a href="{{ route('purchase-returns.index') }}" class="btn btn-secondary">Cancel</a>
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
            <small>No materials available for return in this receipt</small>
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
                <input type="text" name="items[${index}][reason]" class="form-control" placeholder="Reason">
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
                <small>No confirmed receipts for this supplier</small>
            </td></tr>`;
        }
    } else {
        itemsTable.innerHTML = `<tr><td colspan="7" class="text-center text-muted py-4">
            <small>Select a goods receipt to view returnable materials</small>
        </td></tr>`;
    }
});
</script>
@endpush
@endsection
