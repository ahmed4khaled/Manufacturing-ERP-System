@extends('layouts.dashboard')
@section('title','Create Purchase Return')
@section('content')
<div class="page-header"><h1 class="h3 mb-1">Create Purchase Return</h1><p class="text-muted mb-0">Select a confirmed goods receipt and return material quantities that are still available for return.</p></div>
<form method="POST" action="{{ route('purchase-returns.store') }}">@csrf
    <div class="card"><div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Supplier *</label>
                <select name="supplier_id" id="supplier" class="form-select" required>
                    <option value="">Select supplier</option>
                    @foreach($receipts->groupBy('supplier_id') as $supplierId => $supplierReceipts)
                        <option value="{{ $supplierId }}">{{ $supplierReceipts->first()->supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Goods Receipt *</label>
                <select name="goods_receipt_id" id="receipt" class="form-select" required>
                    <option value="">Select receipt</option>
                    @foreach($receipts as $receipt)
                        <option value="{{ $receipt->id }}" data-supplier="{{ $receipt->supplier_id }}">{{ $receipt->receipt_number }} — {{ $receipt->supplier->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Return Date *</label>
                <input type="date" name="return_date" class="form-control" value="{{ now()->toDateString() }}" required>
            </div>
            <div class="col-md-6">
                <label class="form-label">Reason *</label>
                <input type="text" name="reason" class="form-control" required>
            </div>
            <div class="col-12">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2"></textarea>
            </div>
        </div>

        <div class="table-responsive mt-4">
            <table class="table">
                <thead>
                    <tr>
                        <th>Material</th>
                        <th>Accepted</th>
                        <th>Available to Return</th>
                        <th>Return Qty</th>
                        <th>Unit Cost</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody id="items"></tbody>
            </table>
        </div>
    </div></div>
    <button class="btn btn-primary mt-3">Save Return Draft</button>
</form>

@push('scripts')
<script>
const receiptData = @json($receipts->mapWithKeys(fn($receipt) => [$receipt->id => $receipt->items->map(fn($item) => [
    'id' => $item->id,
    'material_id' => $item->material_id,
    'label' => $item->material->code . ' — ' . $item->material->name,
    'unit' => $item->material->unit->label(),
    'accepted' => (string) $item->accepted_quantity,
    'available' => (string) ($item->available_to_return ?? $item->accepted_quantity),
    'unit_cost' => (string) $item->unit_cost,
])]]));
const supplierSelect = document.getElementById('supplier');
const receiptSelect = document.getElementById('receipt');

function renderReceiptItems(receiptId) {
    const rows = receiptData[receiptId] || [];
    let html = '';
    rows.forEach((item, index) => {
        if (parseFloat(item.available) <= 0) return;
        html += `
            <tr>
                <td>${item.label} <small class="text-muted">(${item.unit})</small><input type="hidden" name="items[${index}][material_id]" value="${item.material_id}"></td>
                <td>${item.accepted}</td>
                <td>${item.available}</td>
                <td><input type="number" name="items[${index}][quantity]" step="0.001" min="0.001" max="${item.available}" class="form-control" required></td>
                <td><input type="number" name="items[${index}][unit_cost]" step="0.01" min="0" value="${item.unit_cost}" class="form-control" required></td>
                <td><input type="text" name="items[${index}][reason]" class="form-control"></td>
            </tr>
        `;
    });
    document.getElementById('items').innerHTML = html;
}

receiptSelect.addEventListener('change', function () {
    renderReceiptItems(this.value);
    const selected = this.options[this.selectedIndex];
    if (selected) {
        supplierSelect.value = selected.dataset.supplier || '';
    }
});

supplierSelect.addEventListener('change', function () {
    const value = this.value;
    Array.from(receiptSelect.options).forEach(option => {
        option.hidden = value && option.dataset.supplier && option.dataset.supplier != value;
    });
    if (value) {
        const firstMatch = Array.from(receiptSelect.options).find(option => option.dataset.supplier == value);
        if (firstMatch) {
            receiptSelect.value = firstMatch.value;
            renderReceiptItems(firstMatch.value);
        }
    }
});
</script>
@endpush
@endsection
