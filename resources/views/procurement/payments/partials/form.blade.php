@props(['supplierPayment' => null, 'suppliers' => [], 'orders' => [], 'formAction' => '', 'formMethod' => 'POST', 'submitLabel' => 'حفظ الدفعة'])

@php
    $initialAllocations = collect(old('allocations', $supplierPayment?->allocations?->map(fn ($allocation) => [
        'supplier_invoice_id' => $allocation->supplier_invoice_id,
        'amount' => (string) $allocation->amount,
    ])->all() ?? []))->keyBy('supplier_invoice_id');
    $selectedSupplier = old('supplier_id', $supplierPayment?->supplier_id ?? request('supplier_id'));
    $selectedInvoice = (int) request('supplier_invoice_id', 0);
    $initialAmount = old('amount', $supplierPayment?->amount ?? request('amount'));
@endphp

<form method="POST" action="{{ $formAction }}" id="supplierPaymentForm">
    @csrf
    @if ($formMethod !== 'POST') @method($formMethod) @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">تفاصيل دفعة المورد</h5></div>
                <div class="card-body"><div class="row g-3">
                    <div class="col-md-6">
                        <label for="supplier_id" class="form-label">المورد <span class="text-danger">*</span></label>
                        <select name="supplier_id" id="supplier_id" class="form-select @error('supplier_id') is-invalid @enderror" required>
                            <option value="">اختر المورد</option>
                            @foreach ($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected($selectedSupplier == $supplier->id)>{{ $supplier->code }} — {{ $supplier->name }}</option>@endforeach
                        </select>
                        @error('supplier_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="payment_date" class="form-label">تاريخ السداد <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', $supplierPayment?->payment_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                        @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="payment_type" class="form-label">نوع السداد <span class="text-danger">*</span></label>
                        <select name="payment_type" id="payment_type" class="form-select @error('payment_type') is-invalid @enderror" required>
                            <option value="invoice" @selected(old('payment_type', $supplierPayment?->payment_type ?? 'invoice') === 'invoice')>سداد فواتير</option>
                            <option value="advance" @selected(old('payment_type', $supplierPayment?->payment_type) === 'advance')>دفعة مقدمة غير موزعة</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="amount" class="form-label">إجمالي مبلغ الدفعة (ج.م) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ $initialAmount }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="payment_method" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                            <option value="">اختر طريقة الدفع</option>
                            @foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}" @selected(old('payment_method', $supplierPayment?->payment_method) === $method->value)>{{ $method->label() }}</option>@endforeach
                        </select>
                        @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="reference" class="form-label">رقم المرجع</label>
                        <input type="text" name="reference" id="reference" class="form-control @error('reference') is-invalid @enderror" value="{{ old('reference', $supplierPayment?->reference) }}">
                        @error('reference')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12 d-none" id="purchaseOrderWrapper">
                        <label for="purchase_order_id" class="form-label">أمر شراء مرتبط <span class="text-muted">(اختياري)</span></label>
                        <select name="purchase_order_id" id="purchase_order_id" class="form-select @error('purchase_order_id') is-invalid @enderror">
                            <option value="">دفعة مقدمة عامة للمورد</option>
                            @foreach ($orders as $order)<option value="{{ $order->id }}" data-supplier="{{ $order->supplier_id }}" @selected(old('purchase_order_id', $supplierPayment?->purchase_order_id) == $order->id)>{{ $order->purchase_order_number }} — {{ number_format((float) $order->total, 2) }} ج.م</option>@endforeach
                        </select>
                        @error('purchase_order_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $supplierPayment?->notes) }}</textarea>
                    </div>
                </div></div>
            </div>

            <div class="card mt-4" id="allocationCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                    <div><h5 class="card-title mb-0">توزيع الدفعة على فواتير المورد</h5><small class="text-muted">الفواتير المستحقة مرتبة من الأقدم إلى الأحدث.</small></div>
                    <button type="button" class="btn btn-outline-primary" id="autoAllocate">توزيع تلقائي</button>
                </div>
                <div class="card-body p-0">
                    @error('allocations')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror
                    <div id="invoiceLoading" class="text-center text-muted p-4 d-none">جارٍ تحميل الفواتير...</div>
                    <div id="invoiceEmpty" class="text-center text-muted p-4 d-none">لا توجد فواتير مستحقة لهذا المورد.</div>
                    <div class="table-responsive"><table class="table align-middle mb-0">
                        <thead class="table-light"><tr><th></th><th>الفاتورة</th><th>التاريخ</th><th class="text-end">الإجمالي</th><th class="text-end">المدفوع سابقًا</th><th class="text-end">المتبقي</th><th style="min-width:150px">التوزيع</th></tr></thead>
                        <tbody id="allocationRows"></tbody>
                    </table></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4"><div class="card sticky-top" style="top: 1rem">
            <div class="card-header bg-white"><h5 class="card-title mb-0">ملخص التوزيع</h5></div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2"><span>إجمالي الدفعة</span><strong id="paymentTotal">0.00</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>إجمالي التوزيع</span><strong id="allocatedTotal" class="text-success">0.00</strong></div><hr>
                <div class="d-flex justify-content-between"><span>غير موزع</span><strong id="unallocatedTotal" class="text-danger">0.00</strong></div>
            </div>
            <div class="card-footer bg-white d-grid gap-2"><button type="submit" class="btn btn-primary">{{ $submitLabel }}</button><a href="{{ route('supplier-payments.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
        </div></div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const supplier = document.getElementById('supplier_id');
    const paymentType = document.getElementById('payment_type');
    const amount = document.getElementById('amount');
    const card = document.getElementById('allocationCard');
    const rows = document.getElementById('allocationRows');
    const loading = document.getElementById('invoiceLoading');
    const empty = document.getElementById('invoiceEmpty');
    const orderWrapper = document.getElementById('purchaseOrderWrapper');
    const order = document.getElementById('purchase_order_id');
    const initial = @json($initialAllocations);
    const preferredInvoiceId = @json($selectedInvoice);
    const excludePaymentId = @json($supplierPayment?->id);
    let invoices = [];
    const money = value => Number(value || 0).toLocaleString('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[char]));

    function totals() {
        const total = Number(amount.value || 0);
        let allocated = 0;
        rows.querySelectorAll('.allocation-amount:not(:disabled)').forEach(input => allocated += Number(input.value || 0));
        document.getElementById('paymentTotal').textContent = money(total);
        document.getElementById('allocatedTotal').textContent = money(allocated);
        const unallocated = paymentType.value === 'advance' ? 0 : total - allocated;
        const output = document.getElementById('unallocatedTotal');
        output.textContent = money(unallocated);
        output.className = Math.abs(unallocated) < 0.005 ? 'text-success' : 'text-danger';
    }
    function setSelected(row, selected) {
        row.querySelector('.allocation-check').checked = selected;
        row.querySelector('.invoice-id').disabled = !selected;
        const allocation = row.querySelector('.allocation-amount');
        allocation.disabled = !selected;
        if (!selected) allocation.value = '';
    }
    function render() {
        rows.innerHTML = '';
        invoices.forEach((invoice, index) => {
            const saved = initial[invoice.id] || null;
            const preferred = !saved && Number(invoice.id) === Number(preferredInvoiceId);
            const tr = document.createElement('tr');
            tr.dataset.outstanding = invoice.remaining_amount;
            tr.innerHTML = `<td><input type="checkbox" class="form-check-input allocation-check"></td><td><a href="/supplier-invoices/${invoice.id}" target="_blank">${escapeHtml(invoice.invoice_number)}</a></td><td>${escapeHtml(invoice.invoice_date)}</td><td class="text-end">${money(invoice.total_amount)}</td><td class="text-end">${money(invoice.paid_amount)}</td><td class="text-end fw-semibold">${money(invoice.remaining_amount)}</td><td><input type="hidden" class="invoice-id" name="allocations[${index}][supplier_invoice_id]" value="${invoice.id}" disabled><input type="number" class="form-control allocation-amount" name="allocations[${index}][amount]" step="0.01" min="0.01" max="${invoice.remaining_amount}" disabled></td>`;
            rows.appendChild(tr);
            const checkbox = tr.querySelector('.allocation-check');
            const allocation = tr.querySelector('.allocation-amount');
            checkbox.addEventListener('change', () => { setSelected(tr, checkbox.checked); totals(); });
            allocation.addEventListener('input', totals);
            if (saved || preferred) {
                setSelected(tr, true);
                allocation.value = saved?.amount || amount.value || Number(invoice.remaining_amount).toFixed(2);
                if (preferred && !amount.value) amount.value = allocation.value;
            }
        });
        empty.classList.toggle('d-none', invoices.length !== 0);
        totals();
    }
    async function loadInvoices() {
        rows.innerHTML = ''; invoices = [];
        if (!supplier.value || paymentType.value !== 'invoice') return totals();
        loading.classList.remove('d-none'); empty.classList.add('d-none');
        const query = excludePaymentId ? `?exclude_payment_id=${excludePaymentId}` : '';
        try {
            const response = await fetch(`/supplier-payments/supplier/${supplier.value}/invoices${query}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('load failed');
            invoices = (await response.json()).invoices || [];
            render();
        } catch (error) {
            empty.textContent = 'تعذر تحميل الفواتير. أعد المحاولة.';
            empty.classList.remove('d-none');
        } finally { loading.classList.add('d-none'); }
    }
    function filterOrders() {
        Array.from(order.options).forEach(option => {
            if (!option.value) return;
            option.hidden = option.dataset.supplier !== supplier.value;
            if (option.hidden && option.selected) order.value = '';
        });
    }
    function toggleType() {
        const invoicePayment = paymentType.value === 'invoice';
        card.classList.toggle('d-none', !invoicePayment);
        orderWrapper.classList.toggle('d-none', invoicePayment);
        if (invoicePayment) { order.value = ''; loadInvoices(); } else { rows.innerHTML = ''; totals(); }
    }
    document.getElementById('autoAllocate').addEventListener('click', () => {
        let remaining = Number(amount.value || 0);
        rows.querySelectorAll('tr').forEach(row => {
            const allocation = row.querySelector('.allocation-amount');
            const value = Math.min(remaining, Number(row.dataset.outstanding));
            setSelected(row, value > 0);
            if (value > 0) allocation.value = value.toFixed(2);
            remaining -= value;
        });
        totals();
    });
    supplier.addEventListener('change', () => { filterOrders(); loadInvoices(); });
    paymentType.addEventListener('change', toggleType);
    amount.addEventListener('input', totals);
    filterOrders(); toggleType();
});
</script>
@endpush
