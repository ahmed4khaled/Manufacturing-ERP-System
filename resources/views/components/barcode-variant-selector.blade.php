@props(['target'])

<div class="mb-3">
    <label for="{{ $target }}_barcode" class="form-label">مسح باركود المتغير</label>
    <div class="input-group"><input id="{{ $target }}_barcode" type="text" autocomplete="off" class="form-control" placeholder="امسح الباركود ثم اضغط Enter"><button id="{{ $target }}_barcode_button" type="button" class="btn btn-dark"><i class="bi bi-upc-scan me-1"></i> تحديد</button></div>
    <div id="{{ $target }}_barcode_feedback" class="form-text">المسح يحدد المتغير فقط؛ لن تتغير الكمية قبل إرسال النموذج.</div>
</div>
@push('scripts')
<script>
(() => {
    const input = document.getElementById(@json($target.'_barcode'));
    const button = document.getElementById(@json($target.'_barcode_button'));
    const feedback = document.getElementById(@json($target.'_barcode_feedback'));
    const select = document.getElementById(@json($target));
    const lookupUrl = @json(route('barcodes.lookup'));
    async function scan() {
        const barcode = input.value.trim();
        if (!barcode) return;
        button.disabled = true;
        try {
            const response = await fetch(`${lookupUrl}?context=inventory&barcode=${encodeURIComponent(barcode)}`, { headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || data.errors?.barcode?.[0] || 'الباركود غير صالح.');
            const option = Array.from(select.options).find(item => String(item.value) === String(data.product_variant_id));
            if (!option) throw new Error('المتغير غير متاح في هذا النموذج.');
            select.value = data.product_variant_id;
            select.dispatchEvent(new Event('change'));
            feedback.textContent = `${data.product_name} — ${data.color} / ${data.size} · المخزون الحالي: ${data.available_stock}`;
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
