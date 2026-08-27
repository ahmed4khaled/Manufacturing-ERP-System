@props([
    'paymentReceipt' => null,
    'customers' => [],
    'formAction' => '',
    'formMethod' => 'POST',
    'submitLabel' => 'حفظ السند',
])

@php
    $initialAllocations = collect(old('allocations', $paymentReceipt?->allocations?->map(fn ($allocation) => [
        'sales_invoice_id' => $allocation->sales_invoice_id,
        'amount' => (string) $allocation->amount,
    ])->all() ?? []))->keyBy('sales_invoice_id');
@endphp

<form method="POST" action="{{ $formAction }}" id="paymentReceiptForm">
    @csrf
    @if ($formMethod !== 'POST') @method($formMethod) @endif

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">تفاصيل سند القبض</h5></div>
                <div class="card-body"><div class="row g-3">
                    <div class="col-md-6">
                        <label for="customer_id" class="form-label">العميل <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                            <option value="">اختر العميل</option>
                            @foreach ($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id', $paymentReceipt?->customer_id) == $customer->id)>{{ $customer->code }} — {{ $customer->name }}</option>@endforeach
                        </select>
                        @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="payment_date" class="form-label">تاريخ السداد <span class="text-danger">*</span></label>
                        <input type="date" name="payment_date" id="payment_date" class="form-control @error('payment_date') is-invalid @enderror" value="{{ old('payment_date', $paymentReceipt?->payment_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                        @error('payment_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="payment_type" class="form-label">نوع السداد <span class="text-danger">*</span></label>
                        <select name="payment_type" id="payment_type" class="form-select @error('payment_type') is-invalid @enderror" required>
                            <option value="invoice" @selected(old('payment_type', $paymentReceipt?->payment_type ?? 'invoice') === 'invoice')>سداد فواتير</option>
                            <option value="advance" @selected(old('payment_type', $paymentReceipt?->payment_type) === 'advance')>دفعة مقدمة غير موزعة</option>
                        </select>
                        @error('payment_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="amount" class="form-label">إجمالي مبلغ السند (ج.م) <span class="text-danger">*</span></label>
                        <input type="number" name="amount" id="amount" step="0.01" min="0.01" class="form-control @error('amount') is-invalid @enderror" value="{{ old('amount', $paymentReceipt?->amount) }}" required>
                        @error('amount')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="payment_method" class="form-label">طريقة الدفع <span class="text-danger">*</span></label>
                        <select name="payment_method" id="payment_method" class="form-select @error('payment_method') is-invalid @enderror" required>
                            <option value="">اختر طريقة الدفع</option>
                            @foreach (\App\Enums\PaymentMethod::cases() as $method)<option value="{{ $method->value }}" @selected(old('payment_method', $paymentReceipt?->payment_method?->value) === $method->value)>{{ $method->label() }}</option>@endforeach
                        </select>
                        @error('payment_method')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <label for="reference_number" class="form-label">رقم المرجع</label>
                        <input type="text" name="reference_number" id="reference_number" class="form-control @error('reference_number') is-invalid @enderror" value="{{ old('reference_number', $paymentReceipt?->reference_number) }}">
                        @error('reference_number')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-12">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $paymentReceipt?->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div></div>
            </div>

            <div class="card mt-4" id="allocationCard">
                <div class="card-header bg-white d-flex justify-content-between align-items-center gap-2">
                    <div><h5 class="card-title mb-0">توزيع الدفعة على الفواتير</h5><small class="text-muted">الفواتير مرتبة من الأقدم إلى الأحدث.</small></div>
                    <button type="button" class="btn btn-outline-primary" id="autoAllocate">توزيع تلقائي</button>
                </div>
                <div class="card-body p-0">
                    @error('allocations')<div class="alert alert-danger m-3">{{ $message }}</div>@enderror
                    <div id="invoiceLoading" class="text-center text-muted p-4 d-none">جارٍ تحميل الفواتير...</div>
                    <div id="invoiceEmpty" class="text-center text-muted p-4 d-none">لا توجد فواتير مستحقة لهذا العميل.</div>
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
                <div class="d-flex justify-content-between mb-2"><span>إجمالي السند</span><strong id="receiptTotal">0.00</strong></div>
                <div class="d-flex justify-content-between mb-2"><span>إجمالي التوزيع</span><strong id="allocatedTotal" class="text-success">0.00</strong></div><hr>
                <div class="d-flex justify-content-between"><span>غير موزع</span><strong id="unallocatedTotal" class="text-danger">0.00</strong></div>
            </div>
            <div class="card-footer bg-white d-grid gap-2"><button type="submit" class="btn btn-primary">{{ $submitLabel }}</button><a href="{{ route('payment-receipts.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
        </div></div>
    </div>
</form>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const customer = document.getElementById('customer_id');
    const paymentType = document.getElementById('payment_type');
    const amount = document.getElementById('amount');
    const card = document.getElementById('allocationCard');
    const rows = document.getElementById('allocationRows');
    const loading = document.getElementById('invoiceLoading');
    const empty = document.getElementById('invoiceEmpty');
    const initial = @json($initialAllocations);
    const excludeReceiptId = @json($paymentReceipt?->id);
    let invoices = [];
    const money = value => Number(value || 0).toLocaleString('ar-EG', {minimumFractionDigits: 2, maximumFractionDigits: 2});

    function totals() {
        const receipt = Number(amount.value || 0);
        let allocated = 0;
        rows.querySelectorAll('.allocation-amount:not(:disabled)').forEach(input => allocated += Number(input.value || 0));
        document.getElementById('receiptTotal').textContent = money(receipt);
        document.getElementById('allocatedTotal').textContent = money(allocated);
        const unallocated = receipt - allocated;
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
        totals();
    }

    function render() {
        rows.innerHTML = '';
        invoices.forEach((invoice, index) => {
            const saved = initial[invoice.id] || null;
            const tr = document.createElement('tr');
            tr.dataset.outstanding = invoice.remaining_amount;
            tr.innerHTML = `<td><input type="checkbox" class="form-check-input allocation-check"></td><td><a href="/sales-invoices/${invoice.id}" target="_blank">${invoice.invoice_number}</a></td><td>${invoice.invoice_date}</td><td class="text-end">${money(invoice.total_amount)}</td><td class="text-end">${money(invoice.paid_amount)}</td><td class="text-end fw-semibold">${money(invoice.remaining_amount)}</td><td><input type="hidden" class="invoice-id" name="allocations[${index}][sales_invoice_id]" value="${invoice.id}" disabled><input type="number" class="form-control allocation-amount" name="allocations[${index}][amount]" step="0.01" min="0.01" max="${invoice.remaining_amount}" disabled></td>`;
            rows.appendChild(tr);
            const checkbox = tr.querySelector('.allocation-check');
            const allocation = tr.querySelector('.allocation-amount');
            checkbox.addEventListener('change', () => setSelected(tr, checkbox.checked));
            allocation.addEventListener('input', totals);
            if (saved) { setSelected(tr, true); allocation.value = saved.amount; }
        });
        empty.classList.toggle('d-none', invoices.length !== 0);
        totals();
    }

    async function loadInvoices() {
        rows.innerHTML = ''; invoices = [];
        if (!customer.value || paymentType.value !== 'invoice') return totals();
        loading.classList.remove('d-none'); empty.classList.add('d-none');
        const query = excludeReceiptId ? `?exclude_receipt_id=${excludeReceiptId}` : '';
        try {
            const response = await fetch(`/payment-receipts/customer/${customer.value}/invoices${query}`, {headers: {'Accept': 'application/json'}});
            if (!response.ok) throw new Error('load failed');
            invoices = (await response.json()).invoices || []; render();
        } catch (error) {
            empty.textContent = 'تعذر تحميل الفواتير. أعد المحاولة.'; empty.classList.remove('d-none');
        } finally { loading.classList.add('d-none'); }
    }

    function toggleType() {
        const invoicePayment = paymentType.value === 'invoice';
        card.classList.toggle('d-none', !invoicePayment);
        if (invoicePayment) loadInvoices(); else { rows.innerHTML = ''; totals(); }
    }

    document.getElementById('autoAllocate').addEventListener('click', () => {
        let remaining = Number(amount.value || 0);
        rows.querySelectorAll('tr').forEach(row => {
            const allocation = row.querySelector('.allocation-amount');
            const value = Math.min(remaining, Number(row.dataset.outstanding));
            setSelected(row, value > 0);
            if (value > 0) allocation.value = value.toFixed(2);
            remaining -= value;
        }); totals();
    });
    customer.addEventListener('change', loadInvoices);
    paymentType.addEventListener('change', toggleType);
    amount.addEventListener('input', totals);
    toggleType();
});
</script>
@endpush
