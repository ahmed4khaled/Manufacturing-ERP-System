@props([
    'salesReturn' => null,
    'invoice',
    'eligibleItems',
    'formAction',
    'formMethod' => 'POST',
    'submitLabel' => 'حفظ مسودة المرتجع',
])

@php
    $existingQuantities = $salesReturn?->items?->pluck('quantity', 'sales_invoice_item_id') ?? collect();
    $oldItems = collect(old('items', []))->keyBy('sales_invoice_item_id');
@endphp

<form method="POST" action="{{ $formAction }}">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <input type="hidden" name="sales_invoice_id" value="{{ $invoice->id }}">

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">بنود الفاتورة القابلة للإرجاع</h5>
                    <span class="text-muted small">الفاتورة {{ $invoice->invoice_number }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 border-bottom bg-light">
                        <label for="returnBarcodeInput" class="form-label fw-semibold">مسح باركود من بنود الفاتورة</label>
                        <div class="input-group"><input id="returnBarcodeInput" type="text" autocomplete="off" class="form-control" placeholder="امسح الباركود ثم اضغط Enter"><button id="returnBarcodeBtn" type="button" class="btn btn-dark"><i class="bi bi-upc-scan me-1"></i> تحديد</button></div>
                        <div id="returnBarcodeFeedback" class="form-text">المسح يحدد البند فقط ولا ينشئ أو يعتمد المرتجع.</div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الصنف</th>
                                    <th>المتغير</th>
                                    <th class="text-end">المباع</th>
                                    <th class="text-end">المتاح للإرجاع</th>
                                    <th style="width: 180px">الكمية المرتجعة</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($eligibleItems as $index => $item)
                                    @php
                                        $oldLine = $oldItems->get($item->id);
                                        $quantity = $oldLine['quantity'] ?? $existingQuantities->get($item->id, 0);
                                    @endphp
                                    <tr data-product-variant-id="{{ $item->product_variant_id }}">
                                        <td>
                                            <div class="fw-semibold">{{ $item->product_name }}</div>
                                            <code>{{ $item->sku }}</code>
                                        </td>
                                        <td>{{ $item->color_name ?? '—' }} / {{ $item->size_name ?? '—' }}</td>
                                        <td class="text-end">{{ number_format($item->quantity, 3) }}</td>
                                        <td class="text-end fw-semibold">{{ number_format($item->eligible_return_quantity, 3) }}</td>
                                        <td>
                                            <input type="hidden" name="items[{{ $index }}][sales_invoice_item_id]" value="{{ $item->id }}">
                                            <input
                                                type="number"
                                                name="items[{{ $index }}][quantity]"
                                                value="{{ $quantity }}"
                                                min="0"
                                                max="{{ $item->eligible_return_quantity }}"
                                                step="0.001"
                                                class="form-control @error("items.$index.quantity") is-invalid @enderror"
                                            >
                                            @error("items.$index.quantity")
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @error('items')
                        <div class="alert alert-danger m-3 mb-0">{{ $message }}</div>
                    @enderror
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">بيانات المرتجع</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">العميل</label>
                        <input class="form-control" value="{{ $invoice->customer->code }} — {{ $invoice->customer->name }}" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">تاريخ المرتجع <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" value="{{ old('return_date', $salesReturn?->return_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" class="form-control @error('return_date') is-invalid @enderror" required>
                        @error('return_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-3">
                        <label class="form-label">سبب المرتجع <span class="text-danger">*</span></label>
                        <textarea name="reason" rows="3" class="form-control @error('reason') is-invalid @enderror" required>{{ old('reason', $salesReturn?->reason) }}</textarea>
                        @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="mb-0">
                        <label class="form-label">ملاحظات</label>
                        <textarea name="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $salesReturn?->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary">{{ $submitLabel }}</button>
        <a href="{{ $salesReturn ? route('sales-returns.show', $salesReturn) : route('sales-returns.index') }}" class="btn btn-outline-secondary">إلغاء</a>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const input = document.getElementById('returnBarcodeInput');
    const button = document.getElementById('returnBarcodeBtn');
    const feedback = document.getElementById('returnBarcodeFeedback');
    const lookupUrl = @json(route('barcodes.lookup'));
    async function scan() {
        const barcode = input.value.trim();
        if (!barcode) return;
        button.disabled = true;
        try {
            const response = await fetch(`${lookupUrl}?context=sales_return&barcode=${encodeURIComponent(barcode)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || data.errors?.barcode?.[0] || 'الباركود غير صالح.');
            const row = document.querySelector(`tr[data-product-variant-id="${data.product_variant_id}"]`);
            if (!row) throw new Error('هذا المتغير غير موجود ضمن البنود المتاحة للإرجاع في الفاتورة.');
            const quantity = row.querySelector('input[type="number"]');
            const maximum = Number(quantity.max);
            quantity.value = Math.min(maximum, Number(quantity.value || 0) + 1);
            row.scrollIntoView({ behavior: 'smooth', block: 'center' });
            feedback.textContent = `${data.product_name} — ${data.color} / ${data.size} · الكمية المرتجعة: ${quantity.value}`;
            feedback.className = 'form-text text-success';
            input.value = '';
        } catch (error) {
            feedback.textContent = error.message;
            feedback.className = 'form-text text-danger';
        } finally { button.disabled = false; input.focus(); }
    }
    button?.addEventListener('click', scan);
    input?.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); scan(); } });
})();
</script>
@endpush
