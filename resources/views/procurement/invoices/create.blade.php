@extends('layouts.dashboard')

@section('title', 'إنشاء فاتورة مورد')

@section('content')
    <div class="page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h1 class="h3 mb-1">فاتورة مورد جديدة</h1>
            <p class="text-muted mb-0">الشراء المباشر هو المسار الافتراضي، ويمكنك الاحتفاظ بمسار أمر الشراء والاستلام المنفصل.</p>
        </div>
        <a href="{{ route('supplier-invoices.index') }}" class="btn btn-outline-secondary">العودة للفواتير</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('supplier-invoices.store') }}" id="invoiceForm">
        @csrf
        <input type="hidden" name="submission_token" value="{{ old('submission_token', (string) \Illuminate\Support\Str::uuid()) }}">

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">بيانات الفاتورة</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">المورد *</label>
                        <select name="supplier_id" id="supplier" class="form-select" required>
                            <option value="">اختر المورد</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}" @selected((string) old('supplier_id') === (string) $supplier->id)>
                                    {{ $supplier->code }} — {{ $supplier->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ الفاتورة *</label>
                        <input type="date" name="invoice_date" value="{{ old('invoice_date', now()->toDateString()) }}" class="form-control" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">تاريخ الاستحقاق</label>
                        <input type="date" name="due_date" value="{{ old('due_date') }}" class="form-control">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">رقم فاتورة المورد / المرجع</label>
                        <input name="supplier_reference" value="{{ old('supplier_reference') }}" class="form-control" placeholder="مثال: SUP-000125">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">ملاحظات</label>
                        <input name="notes" value="{{ old('notes') }}" class="form-control" placeholder="اختياري">
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header bg-white fw-semibold">طريقة الاستلام</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="border rounded p-3 d-flex gap-3 h-100 cursor-pointer">
                            <input type="radio" name="receipt_mode" value="immediate" class="form-check-input mt-1" @checked(old('receipt_mode', 'immediate') === 'immediate')>
                            <span>
                                <strong class="d-block">استلام فوري</strong>
                                <small class="text-muted">إنشاء إذن الاستلام وإضافة الخامات إلى المخزون وFIFO تلقائيًا.</small>
                            </span>
                        </label>
                    </div>
                    <div class="col-md-6">
                        <label class="border rounded p-3 d-flex gap-3 h-100 cursor-pointer">
                            <input type="radio" name="receipt_mode" value="later" class="form-check-input mt-1" @checked(old('receipt_mode') === 'later')>
                            <span>
                                <strong class="d-block">الاستلام لاحقًا</strong>
                                <small class="text-muted">حفظ الفاتورة الآن واستلام بنودها لاحقًا، أو ربطها بإذن استلام قائم.</small>
                            </span>
                        </label>
                    </div>
                </div>

                <div id="immediateNotice" class="alert alert-primary mt-3 mb-0">
                    <i class="bi bi-lightning-charge-fill me-1"></i>
                    سيتم إنشاء إذن استلام مؤكد وإضافة الخامات للمخزون تلقائيًا عند إنشاء الفاتورة.
                </div>
            </div>
        </div>

        <div class="card mb-4 d-none" id="plannedWorkflow">
            <div class="card-header bg-white fw-semibold">ربط بمستندات الشراء المخطط — اختياري</div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label">أمر الشراء</label>
                        <select name="purchase_order_id" id="purchase_order" class="form-select">
                            <option value="">غير مرتبط</option>
                            @foreach ($orders as $order)
                                <option value="{{ $order->id }}" data-supplier="{{ $order->supplier_id }}" @selected((string) old('purchase_order_id') === (string) $order->id)>
                                    {{ $order->purchase_order_number }} — {{ $order->supplier->name }} — {{ $order->status }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">إذن استلام مؤكد</label>
                        <select name="goods_receipt_id" id="goods_receipt" class="form-select">
                            <option value="">غير مرتبط — استلام مباشر لاحقًا</option>
                            @foreach ($receipts as $receipt)
                                <option value="{{ $receipt->id }}" data-supplier="{{ $receipt->supplier_id }}" data-purchase-order="{{ $receipt->purchase_order_id }}" @selected((string) old('goods_receipt_id') === (string) $receipt->id)>
                                    {{ $receipt->receipt_number }} — {{ $receipt->supplier->name }} — {{ $receipt->received_at->format('Y-m-d') }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-4" id="directItemsCard">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <div>
                    <strong>الخامات</strong>
                    <small class="text-muted d-block">سعر الوحدة هو التكلفة الفعلية التي ستنتقل كما هي إلى إذن الاستلام وطبقة FIFO.</small>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="addItem"><i class="bi bi-plus-lg me-1"></i>إضافة خامة</button>
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th style="min-width:260px">الخامة</th><th>الوحدة</th><th>الكمية</th><th>سعر الوحدة</th><th>الإجمالي</th><th></th></tr></thead>
                    <tbody id="directItems"></tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4 d-none" id="receiptPreviewCard">
            <div class="card-header bg-white fw-semibold">بنود إذن الاستلام المحدد</div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead><tr><th>الخامة</th><th>الكمية المقبولة</th><th>تكلفة الوحدة</th><th>الإجمالي</th></tr></thead>
                    <tbody id="receiptPreview"></tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-md-3"><label class="form-label">الإجمالي الفرعي</label><input type="number" name="subtotal" id="subtotal" class="form-control fw-bold" value="{{ old('subtotal', '0.00') }}" readonly></div>
                    <div class="col-md-3"><label class="form-label">الخصم</label><input type="number" name="discount" id="discount" class="form-control" min="0" step=".01" value="{{ old('discount', '0.00') }}"></div>
                    <div class="col-md-3"><label class="form-label">الضريبة</label><input type="number" name="tax" id="tax" class="form-control" min="0" step=".01" value="{{ old('tax', '0.00') }}"></div>
                    <div class="col-md-3"><label class="form-label">إجمالي الفاتورة</label><input id="total" class="form-control fw-bold text-primary" value="0.00" readonly></div>
                    <div class="col-12"><small id="costRule" class="text-muted">في الشراء المباشر يجب إدخال أي خصم أو ضريبة ضمن سعر الوحدة الفعلي لضمان تطابق المخزون وFIFO والمحاسبة.</small></div>
                </div>
                <div class="d-flex gap-2 mt-4">
                    <button type="submit" class="btn btn-primary" id="submitButton"><i class="bi bi-check2-circle me-1"></i><span>إنشاء الفاتورة والاستلام الآن</span></button>
                    <a href="{{ route('supplier-invoices.index') }}" class="btn btn-outline-secondary">إلغاء</a>
                </div>
            </div>
        </div>
    </form>

    <template id="itemRowTemplate">
        <tr>
            <td>
                <select name="items[__INDEX__][material_id]" class="form-select material-select" required>
                    <option value="">اختر الخامة</option>
                    @foreach ($materials as $material)
                        <option value="{{ $material->id }}" data-unit="{{ $material->unit->label() }}">{{ $material->code }} — {{ $material->name }}</option>
                    @endforeach
                </select>
            </td>
            <td class="material-unit text-muted">—</td>
            <td><input type="number" name="items[__INDEX__][quantity]" class="form-control quantity" min="0.001" step="0.001" required></td>
            <td><input type="number" name="items[__INDEX__][unit_cost]" class="form-control unit-cost" min="0" step="0.01" required></td>
            <td class="line-total fw-semibold">0.00</td>
            <td><button type="button" class="btn btn-sm btn-outline-danger remove-item" title="حذف"><i class="bi bi-trash"></i></button></td>
        </tr>
    </template>
@endsection

@php
    $receiptJsonData = $receipts->mapWithKeys(function ($receipt) {
        return [$receipt->id => [
            'supplier_id' => $receipt->supplier_id,
            'purchase_order_id' => $receipt->purchase_order_id,
            'items' => $receipt->items->map(function ($item) {
                return [
                    'material' => trim(($item->material?->code ?? '').' — '.($item->material?->name ?? '')),
                    'quantity' => (float) $item->accepted_quantity,
                    'unit_cost' => (float) $item->unit_cost,
                ];
            })->values()->all(),
        ]];
    })->all();
    $oldItemData = collect(old('items', []))
        ->filter(fn ($item) => filled($item['material_id'] ?? null))
        ->values()
        ->all();
@endphp

@push('scripts')
<script>
(() => {
    const receiptData = @json($receiptJsonData);
    const oldItems = @json($oldItemData);
    const supplier = document.getElementById('supplier');
    const purchaseOrder = document.getElementById('purchase_order');
    const goodsReceipt = document.getElementById('goods_receipt');
    const itemsBody = document.getElementById('directItems');
    const template = document.getElementById('itemRowTemplate');
    const subtotal = document.getElementById('subtotal');
    const discount = document.getElementById('discount');
    const tax = document.getElementById('tax');
    const total = document.getElementById('total');
    let rowIndex = 0;

    const mode = () => document.querySelector('input[name="receipt_mode"]:checked')?.value ?? 'immediate';
    const escapeHtml = value => {
        const element = document.createElement('span');
        element.textContent = value ?? '';
        return element.innerHTML;
    };
    const filterOptions = (select, predicate) => [...select.options].forEach(option => {
        option.hidden = option.value !== '' && ! predicate(option);
    });

    function addRow(values = {}) {
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = template.innerHTML.replaceAll('__INDEX__', rowIndex++);
        const row = wrapper.firstElementChild;
        itemsBody.appendChild(row);
        row.querySelector('.material-select').value = values.material_id ?? '';
        row.querySelector('.quantity').value = values.quantity ?? '';
        row.querySelector('.unit-cost').value = values.unit_cost ?? '';
        updateRow(row);
    }

    function updateRow(row) {
        const selected = row.querySelector('.material-select').selectedOptions[0];
        row.querySelector('.material-unit').textContent = selected?.dataset.unit ?? '—';
        const quantity = parseFloat(row.querySelector('.quantity').value) || 0;
        const cost = parseFloat(row.querySelector('.unit-cost').value) || 0;
        row.querySelector('.line-total').textContent = (quantity * cost).toFixed(2);
        calculateTotals();
    }

    function calculateTotals() {
        let value = 0;
        if (goodsReceipt.value && receiptData[goodsReceipt.value]) {
            receiptData[goodsReceipt.value].items.forEach(item => value += item.quantity * item.unit_cost);
        } else {
            itemsBody.querySelectorAll('tr').forEach(row => {
                value += (parseFloat(row.querySelector('.quantity').value) || 0) * (parseFloat(row.querySelector('.unit-cost').value) || 0);
            });
        }
        subtotal.value = value.toFixed(2);
        total.value = Math.max(0, value - (parseFloat(discount.value) || 0) + (parseFloat(tax.value) || 0)).toFixed(2);
    }

    function renderReceipt() {
        const receipt = receiptData[goodsReceipt.value];
        const preview = document.getElementById('receiptPreview');
        preview.innerHTML = '';
        document.getElementById('receiptPreviewCard').classList.toggle('d-none', ! receipt);
        document.getElementById('directItemsCard').classList.toggle('d-none', !! receipt);
        itemsBody.querySelectorAll('input, select').forEach(input => input.disabled = !! receipt);
        discount.disabled = ! receipt;
        tax.disabled = ! receipt;
        document.getElementById('costRule').classList.toggle('d-none', !! receipt);

        if (receipt) {
            receipt.items.forEach(item => {
                preview.insertAdjacentHTML('beforeend', `<tr><td>${escapeHtml(item.material)}</td><td>${item.quantity.toFixed(3)}</td><td>${item.unit_cost.toFixed(2)}</td><td>${(item.quantity * item.unit_cost).toFixed(2)}</td></tr>`);
            });
        } else {
            discount.value = '0.00';
            tax.value = '0.00';
        }
        calculateTotals();
    }

    function updateMode() {
        const immediate = mode() === 'immediate';
        document.getElementById('immediateNotice').classList.toggle('d-none', ! immediate);
        document.getElementById('plannedWorkflow').classList.toggle('d-none', immediate);
        purchaseOrder.disabled = immediate;
        goodsReceipt.disabled = immediate;
        if (immediate) {
            purchaseOrder.value = '';
            goodsReceipt.value = '';
        }
        document.querySelector('#submitButton span').textContent = immediate ? 'إنشاء الفاتورة والاستلام الآن' : 'إنشاء الفاتورة';
        renderReceipt();
    }

    document.getElementById('addItem').addEventListener('click', () => addRow());
    itemsBody.addEventListener('input', event => updateRow(event.target.closest('tr')));
    itemsBody.addEventListener('change', event => updateRow(event.target.closest('tr')));
    itemsBody.addEventListener('click', event => {
        const button = event.target.closest('.remove-item');
        if (! button) return;
        button.closest('tr').remove();
        if (! itemsBody.children.length) addRow();
        calculateTotals();
    });
    document.querySelectorAll('input[name="receipt_mode"]').forEach(input => input.addEventListener('change', updateMode));
    supplier.addEventListener('change', () => {
        filterOptions(purchaseOrder, option => ! supplier.value || option.dataset.supplier === supplier.value);
        filterOptions(goodsReceipt, option => ! supplier.value || option.dataset.supplier === supplier.value);
        if (purchaseOrder.selectedOptions[0]?.hidden) purchaseOrder.value = '';
        if (goodsReceipt.selectedOptions[0]?.hidden) goodsReceipt.value = '';
        renderReceipt();
    });
    purchaseOrder.addEventListener('change', () => {
        filterOptions(goodsReceipt, option => (! supplier.value || option.dataset.supplier === supplier.value) && (! purchaseOrder.value || option.dataset.purchaseOrder === purchaseOrder.value));
        if (goodsReceipt.selectedOptions[0]?.hidden) goodsReceipt.value = '';
        renderReceipt();
    });
    goodsReceipt.addEventListener('change', renderReceipt);
    discount.addEventListener('input', calculateTotals);
    tax.addEventListener('input', calculateTotals);

    (oldItems.length ? oldItems : [{}]).forEach(addRow);
    supplier.dispatchEvent(new Event('change'));
    updateMode();
})();
</script>
@endpush
