@extends('layouts.dashboard')
@section('title', 'Create Goods Receipt')
@section('content')
<div class="page-header">
    <h1 class="h3 mb-1">Create Goods Receipt</h1>
    <p class="text-muted">Record accepted and rejected material quantities against an approved PO.</p>
</div>

<form method="POST" action="{{ route('goods-receipts.store') }}">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Purchase Order *</label>
                    <select name="purchase_order_id" id="po" class="form-select" required>
                        <option value="">Select order</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}">
                                {{ $order->purchase_order_number }} — {{ $order->supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Received At *</label>
                    <input 
                        type="datetime-local" 
                        name="received_at" 
                        class="form-control" 
                        value="{{ now()->format('Y-m-d\TH:i') }}" 
                        required
                    >
                </div>
                <div class="col-12">
                    <label class="form-label">Notes</label>
                    <textarea name="notes" class="form-control"></textarea>
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Material</th>
                            <th>Ordered</th>
                            <th>Received</th>
                            <th>Accepted</th>
                            <th>Rejected</th>
                            <th>Unit Cost</th>
                        </tr>
                    </thead>
                    <tbody id="items"></tbody>
                </table>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">Save Receipt Draft</button>
</form>

@push('scripts')
<script>
    // Prepare PO data: organize items by purchase order ID
    const poData = {!! json_encode(
        $orders->mapWithKeys(function($order) {
            return [
                $order->id => $order->items->map(function($item) {
                    return [
                        'id' => $item->id,
                        'label' => $item->material->code . ' — ' . $item->material->name,
                        'unit' => $item->material->unit->label(),
                        'qty' => $item->quantity,
                        'cost' => $item->unit_price,
                    ];
                })->all(),
            ];
        })->all()
    ) !!};

    // Handle PO selection and populate items table
    document.getElementById('po').addEventListener('change', function(e) {
        const poId = e.target.value;
        const items = poData[poId] || [];
        const tbody = document.getElementById('items');
        
        let html = '';

        items.forEach((item, index) => {
            html += `
                <tr>
                    <td>
                        ${item.label}
                        <small class="text-muted">(${item.unit})</small>
                        <input 
                            type="hidden" 
                            name="items[${index}][purchase_order_item_id]" 
                            value="${item.id}"
                        >
                    </td>
                    <td>${item.qty}</td>
                    <td>
                        <input 
                            name="items[${index}][received_quantity]" 
                            type="number" 
                            step=".001" 
                            min=".001" 
                            class="form-control" 
                            required
                        >
                    </td>
                    <td>
                        <input 
                            name="items[${index}][accepted_quantity]" 
                            type="number" 
                            step=".001" 
                            min="0" 
                            value="0" 
                            class="form-control" 
                            required
                        >
                    </td>
                    <td>
                        <input 
                            name="items[${index}][rejected_quantity]" 
                            type="number" 
                            step=".001" 
                            min="0" 
                            value="0" 
                            class="form-control" 
                            required
                        >
                    </td>
                    <td>
                        <input 
                            name="items[${index}][unit_cost]" 
                            type="number" 
                            step=".01" 
                            min="0" 
                            value="${item.cost}" 
                            class="form-control" 
                            required
                        >
                    </td>
                </tr>
            `;
        });

        tbody.innerHTML = html;
    });
</script>
@endpush

@endsection
