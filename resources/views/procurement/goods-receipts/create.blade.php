@extends('layouts.dashboard')
@section('title', 'إنشاء إذن استلام')
@section('content')
<div class="page-header">
    <h1 class="h3 mb-1">إنشاء إذن استلام</h1>
    <p class="text-muted">سجّل كميات الخامات المقبولة والمرفوضة مقابل أمر شراء معتمد.</p>
</div>

<form method="POST" action="{{ route('goods-receipts.store') }}">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">أمر الشراء *</label>
                    <select name="purchase_order_id" id="po" class="form-select" required>
                        <option value="">اختر أمر الشراء</option>
                        @foreach($orders as $order)
                            <option value="{{ $order->id }}">
                                {{ $order->purchase_order_number }} — {{ $order->supplier->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">تاريخ الاستلام *</label>
                    <input 
                        type="datetime-local" 
                        name="received_at" 
                        class="form-control" 
                        value="{{ now()->format('Y-m-d\TH:i') }}" 
                        required
                    >
                </div>
                <div class="col-12">
                    <label class="form-label">ملاحظات</label>
                    <textarea name="notes" class="form-control"></textarea>
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table">
                    <thead>
                        <tr>
                            <th>الخامة</th>
                            <th>المطلوب</th>
                            <th>المستلم</th>
                            <th>المقبول</th>
                            <th>المرفوض</th>
                            <th>تكلفة الوحدة</th>
                        </tr>
                    </thead>
                    <tbody id="items"></tbody>
                </table>
            </div>
        </div>
    </div>

    <button type="submit" class="btn btn-primary mt-3">حفظ السند كمسودة</button>
</form>

@push('scripts')
<script>
    const poData = {!! json_encode(
        $orders->mapWithKeys(function($order) {

            return [
                $order->id => $order->items->map(function($item) {

                    $acceptedPreviously = $item->receiptItems
                        ->filter(function($receiptItem) {
                            return $receiptItem->receipt
                                && $receiptItem->receipt->status === 'confirmed';
                        })
                        ->sum('accepted_quantity');

                    $ordered = (float) $item->quantity;

                    $remaining = max(
                        0,
                        $ordered - (float) $acceptedPreviously
                    );

                    return [
                        'id' => $item->id,
                        'label' => $item->material->code . ' — ' . $item->material->name,
                        'unit' => $item->material->unit->label(),

                        'ordered' => $ordered,

                        'acceptedPreviously' => (float) $acceptedPreviously,

                        'remaining' => $remaining,

                        'cost' => (float) $item->unit_price,
                    ];

                })->filter(function($item) {

                    // Show only items that still need accepted quantity
                    return $item['remaining'] > 0;

                })->values()->all(),
            ];

        })->all()
    ) !!};

    const poSelect = document.getElementById('po');
    const tbody = document.getElementById('items');

    function renderItems(poId) {

        const items = poData[poId] || [];

        tbody.innerHTML = '';

        if (items.length === 0) {

            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted py-4">
                        تم قبول جميع الكميات المطلوبة.
                    </td>
                </tr>
            `;

            return;
        }

        let html = '';

        items.forEach((item, index) => {

            html += `
                <tr>

                    <td>
                        ${item.label}
                        <small class="text-muted">
                            (${item.unit})
                        </small>

                        <input
                            type="hidden"
                            name="items[${index}][purchase_order_item_id]"
                            value="${item.id}"
                        >
                    </td>

                    <td>
                        ${item.ordered.toFixed(3)}
                    </td>

                    <td>
                        <input
                            name="items[${index}][received_quantity]"
                            type="number"
                            step=".001"
                            min="0"
                            max="${item.remaining}"
                            value="${item.remaining}"
                            class="form-control received-input"
                            required
                        >
                    </td>

                    <td>
                        <input
                            name="items[${index}][accepted_quantity]"
                            type="number"
                            step=".001"
                            min="0"
                            max="${item.remaining}"
                            value="${item.remaining}"
                            class="form-control accepted-input"
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
                            class="form-control rejected-input"
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

        setupQuantityValidation();
    }

    function setupQuantityValidation() {

        const rows = tbody.querySelectorAll('tr');

        rows.forEach(row => {

            const received = row.querySelector('.received-input');
            const accepted = row.querySelector('.accepted-input');
            const rejected = row.querySelector('.rejected-input');

            if (!received || !accepted || !rejected) {
                return;
            }

            function sync() {

                const receivedValue = parseFloat(received.value) || 0;
                const acceptedValue = parseFloat(accepted.value) || 0;
                const rejectedValue = parseFloat(rejected.value) || 0;

                const total = acceptedValue + rejectedValue;

                if (total > receivedValue) {

                    rejected.setCustomValidity(
                        'لا يمكن أن يتجاوز مجموع المقبول والمرفوض الكمية المستلمة.'
                    );

                } else if (total < receivedValue) {

                    rejected.setCustomValidity(
                        'يجب أن يساوي مجموع المقبول والمرفوض الكمية المستلمة.'
                    );

                } else {

                    rejected.setCustomValidity('');
                }
            }

            received.addEventListener('input', sync);
            accepted.addEventListener('input', sync);
            rejected.addEventListener('input', sync);

            sync();
        });
    }

    poSelect.addEventListener('change', function () {

        renderItems(this.value);

    });

    // If coming from Purchase Order page
    const urlParams = new URLSearchParams(window.location.search);
    const selectedPo = urlParams.get('purchase_order_id');

    if (selectedPo) {

        poSelect.value = selectedPo;

        renderItems(selectedPo);
    }
</script>
@endpush

@endsection
