@props([
    'salesInvoice' => null,
    'customers' => [],
    'products' => [],
    'existingProducts' => null,
    'formAction' => '',
    'formMethod' => 'POST',
    'submitLabel' => 'حفظ الفاتورة',
])

<form method="POST" action="{{ $formAction }}" id="invoiceForm">
    @csrf
    @if ($formMethod !== 'POST')
        @method($formMethod)
    @endif

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">تفاصيل الفاتورة</h5></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="customer_id" class="form-label">العميل <span class="text-danger">*</span></label>
                        <select name="customer_id" id="customer_id" class="form-select @error('customer_id') is-invalid @enderror" required>
                            <option value="">اختر العميل</option>
                            @foreach ($customers as $customer)
                                <option value="{{ $customer->id }}" @selected(old('customer_id', $salesInvoice?->customer_id) == $customer->id)>
                                    {{ $customer->code }} — {{ $customer->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('customer_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    <div class="mb-3">
                        <label for="invoice_date" class="form-label">تاريخ الفاتورة <span class="text-danger">*</span></label>
                        <input type="date" name="invoice_date" id="invoice_date" class="form-control @error('invoice_date') is-invalid @enderror"
                            value="{{ old('invoice_date', $salesInvoice?->invoice_date?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                        @error('invoice_date')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>

                    @if ($salesInvoice)
                        <div class="mb-3">
                            <label class="form-label">الحالة</label>
                            <p class="mb-0">
                                <span class="badge bg-{{ $salesInvoice->status->badgeClass() }}">{{ $salesInvoice->status->label() }}</span>
                            </p>
                            <div class="form-text">استخدم إجراءات التأكيد أو الإلغاء من صفحة الفاتورة لتغيير حالتها.</div>
                        </div>
                    @endif

                    <div class="mb-0">
                        <label for="notes" class="form-label">ملاحظات</label>
                        <textarea name="notes" id="notes" rows="3" class="form-control @error('notes') is-invalid @enderror">{{ old('notes', $salesInvoice?->notes) }}</textarea>
                        @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white"><h5 class="card-title mb-0">إضافة منتج (موديل)</h5></div>
                <div class="card-body">
                    @error('products')<div class="alert alert-danger">{{ $message }}</div>@enderror

                    <div class="row g-3 align-items-end mb-3">
                        <div class="col-md-8">
                            <label for="product_selector" class="form-label">اختر الموديل</label>
                            <select id="product_selector" class="form-select">
                                <option value="">اختر موديل منتج...</option>
                                @foreach ($products as $product)
                                    <option value="{{ $product->id }}" data-name="{{ $product->name }}" data-code="{{ $product->code }}">
                                        {{ $product->name }} ({{ $product->code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="button" id="loadVariantsBtn" class="btn btn-outline-primary w-100" disabled>
                                <i class="bi bi-grid me-1"></i> تحميل المتغيرات
                            </button>
                        </div>
                    </div>

                    <div id="variantMatrixSection" class="d-none">
                        <h6 class="mb-3" id="currentProductTitle"></h6>
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>اللون</th>
                                        <th>المقاس</th>
                                        <th class="text-end">سعر الوحدة</th>
                                        <th class="text-end">المخزون المتاح</th>
                                        <th style="width:120px">الكمية</th>
                                    </tr>
                                </thead>
                                <tbody id="variantMatrixBody"></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2">
                            <div>
                                <span class="text-muted">إجمالي المنتج:</span>
                                <strong id="currentProductQty">0</strong> قطع ·
                                <strong id="currentProductAmount">0.00</strong> ج.م
                            </div>
                            <button type="button" id="addProductBtn" class="btn btn-success">
                                <i class="bi bi-plus-circle me-1"></i> إضافة منتج
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">ملخص الفاتورة</h5>
                    <span class="badge bg-secondary" id="summaryProductCount">0 منتجات</span>
                </div>
                <div class="card-body p-0">
                    <div id="emptySummary" class="text-center text-muted py-4">
                        لم تتم إضافة منتجات بعد. اختر موديلًا ثم اضغط «إضافة منتج».
                    </div>
                    <div class="table-responsive d-none" id="summaryTableWrapper">
                        <table class="table mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>الموديل</th>
                                    <th class="text-end">الكمية</th>
                                    <th class="text-end">المبلغ</th>
                                    <th style="width:60px"></th>
                                </tr>
                            </thead>
                            <tbody id="summaryTableBody"></tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <th>المجموع الفرعي</th>
                                    <th class="text-end" id="grandTotalQty">0</th>
                                    <th class="text-end" id="grandTotalAmount">0.00 ج.م</th>
                                    <th></th>
                                </tr>
                                <tr>
                                    <th colspan="2"><label for="discount" class="mb-0">الخصم</label></th>
                                    <th>
                                        <div class="input-group input-group-sm">
                                            <input type="number" name="discount" id="discount" min="0" step="0.01"
                                                value="{{ old('discount', $salesInvoice?->discount ?? 0) }}"
                                                class="form-control text-end @error('discount') is-invalid @enderror">
                                            <span class="input-group-text">ج.م</span>
                                        </div>
                                        @error('discount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </th>
                                    <th></th>
                                </tr>
                                <tr class="table-primary">
                                    <th colspan="2">الإجمالي بعد الخصم</th>
                                    <th class="text-end" id="netTotalAmount">0.00 ج.م</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div id="hiddenProductsContainer"></div>

            <div class="d-flex gap-2 mt-4">
                <button type="submit" class="btn btn-primary btn-lg" id="saveInvoiceBtn" disabled>
                    <i class="bi bi-check-lg me-1"></i> {{ $submitLabel }}
                </button>
                <a href="{{ route('sales-invoices.index') }}" class="btn btn-outline-secondary btn-lg">إلغاء</a>
            </div>
        </div>
    </div>
</form>

@push('scripts')
<script>
(() => {
    const variantsUrl = @json(route('sales-invoices.product-variants', ['product' => '__PRODUCT__']));
    const existingProducts = @json($existingProducts ?? []);
    const addedProducts = [...existingProducts];

    let currentProduct = null;
    let currentVariants = [];

    const productSelector = document.getElementById('product_selector');
    const loadVariantsBtn = document.getElementById('loadVariantsBtn');
    const variantMatrixSection = document.getElementById('variantMatrixSection');
    const variantMatrixBody = document.getElementById('variantMatrixBody');
    const currentProductTitle = document.getElementById('currentProductTitle');
    const currentProductQty = document.getElementById('currentProductQty');
    const currentProductAmount = document.getElementById('currentProductAmount');
    const addProductBtn = document.getElementById('addProductBtn');
    const summaryTableWrapper = document.getElementById('summaryTableWrapper');
    const summaryTableBody = document.getElementById('summaryTableBody');
    const emptySummary = document.getElementById('emptySummary');
    const grandTotalQty = document.getElementById('grandTotalQty');
    const grandTotalAmount = document.getElementById('grandTotalAmount');
    const discountInput = document.getElementById('discount');
    const netTotalAmount = document.getElementById('netTotalAmount');
    const summaryProductCount = document.getElementById('summaryProductCount');
    const hiddenProductsContainer = document.getElementById('hiddenProductsContainer');
    const saveInvoiceBtn = document.getElementById('saveInvoiceBtn');
    const invoiceForm = document.getElementById('invoiceForm');

    discountInput?.addEventListener('input', () => updateNetTotal());

    productSelector?.addEventListener('change', () => {
        loadVariantsBtn.disabled = !productSelector.value;
    });

    loadVariantsBtn?.addEventListener('click', async () => {
        const productId = productSelector.value;
        if (!productId) return;

        if (addedProducts.some(p => String(p.product_id) === String(productId))) {
            alert('هذا الموديل مضاف بالفعل إلى الفاتورة. احذفه من الملخص لإعادة إضافته.');
            return;
        }

        loadVariantsBtn.disabled = true;
        loadVariantsBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> جارٍ التحميل...';

        try {
            const response = await fetch(variantsUrl.replace('__PRODUCT__', productId));
            if (!response.ok) throw new Error('فشل تحميل المتغيرات');
            const data = await response.json();
            currentProduct = data;
            currentVariants = data.variants.map(v => ({ ...v, quantity: 0 }));
            renderMatrix();
            variantMatrixSection.classList.remove('d-none');
        } catch (e) {
            alert('تعذر تحميل متغيرات هذا المنتج.');
        } finally {
            loadVariantsBtn.disabled = false;
            loadVariantsBtn.innerHTML = '<i class="bi bi-grid me-1"></i> تحميل المتغيرات';
        }
    });

    function renderMatrix() {
        currentProductTitle.textContent = `${currentProduct.product_name} (${currentProduct.product_code})`;
        variantMatrixBody.innerHTML = currentVariants.map((variant, index) => `
            <tr>
                <td>${variant.color}</td>
                <td>${variant.size}</td>
                <td class="text-end">${formatMoney(variant.unit_price)} ج.م</td>
                <td class="text-end ${variant.available_stock > 0 ? 'text-success' : 'text-danger'} fw-semibold">${Number(variant.available_stock).toLocaleString()}</td>
                <td>
                    <input type="number" min="0" step="0.001" class="form-control form-control-sm variant-qty"
                        data-index="${index}" value="${variant.quantity}">
                </td>
            </tr>
        `).join('');

        variantMatrixBody.querySelectorAll('.variant-qty').forEach(input => {
            input.addEventListener('input', (e) => {
                const idx = parseInt(e.target.dataset.index);
                currentVariants[idx].quantity = Math.max(0, parseInt(e.target.value) || 0);
                updateCurrentProductTotals();
            });
        });

        updateCurrentProductTotals();
    }

    function updateCurrentProductTotals() {
        let qty = 0;
        let amount = 0;
        currentVariants.forEach(v => {
            if (v.quantity > 0) {
                qty += v.quantity;
                amount += v.quantity * v.unit_price;
            }
        });
        currentProductQty.textContent = qty.toLocaleString();
        currentProductAmount.textContent = formatMoney(amount);
    }

    addProductBtn?.addEventListener('click', () => {
        if (!currentProduct) return;

        const variantsWithQty = currentVariants.filter(v => v.quantity > 0);
        if (variantsWithQty.length === 0) {
            alert('أدخل كمية لمتغير واحد على الأقل.');
            return;
        }

        const totalQty = variantsWithQty.reduce((s, v) => s + v.quantity, 0);
        const totalAmount = variantsWithQty.reduce((s, v) => s + (v.quantity * v.unit_price), 0);

        addedProducts.push({
            product_id: currentProduct.product_id,
            product_name: currentProduct.product_name,
            product_code: currentProduct.product_code,
            variants: variantsWithQty.map(v => ({
                product_variant_id: v.product_variant_id,
                color: v.color,
                size: v.size,
                unit_price: v.unit_price,
                quantity: v.quantity,
            })),
            total_quantity: totalQty,
            total_amount: totalAmount,
        });

        currentProduct = null;
        currentVariants = [];
        productSelector.value = '';
        loadVariantsBtn.disabled = true;
        variantMatrixSection.classList.add('d-none');
        renderSummary();
    });

    function renderSummary() {
        if (addedProducts.length === 0) {
            emptySummary.classList.remove('d-none');
            summaryTableWrapper.classList.add('d-none');
            saveInvoiceBtn.disabled = true;
            summaryProductCount.textContent = '0 منتجات';
            grandTotalAmount.textContent = formatMoney(0) + ' ج.م';
            updateNetTotal(0);
            return;
        }

        emptySummary.classList.add('d-none');
        summaryTableWrapper.classList.remove('d-none');
        saveInvoiceBtn.disabled = false;

        let grandQty = 0;
        let grandAmount = 0;

        summaryTableBody.innerHTML = addedProducts.map((product, index) => {
            grandQty += product.total_quantity;
            grandAmount += product.total_amount;
            return `
                <tr>
                    <td><strong>${product.product_name}</strong><br><small class="text-muted">${product.product_code}</small></td>
                    <td class="text-end">${product.total_quantity.toLocaleString()} قطعة</td>
                    <td class="text-end">${formatMoney(product.total_amount)} ج.م</td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-danger remove-product" data-index="${index}">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        summaryTableBody.querySelectorAll('.remove-product').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const idx = parseInt(e.currentTarget.dataset.index);
                addedProducts.splice(idx, 1);
                renderSummary();
            });
        });

        grandTotalQty.textContent = grandQty.toLocaleString();
        grandTotalAmount.textContent = formatMoney(grandAmount) + ' ج.م';
        updateNetTotal(grandAmount);
        summaryProductCount.textContent = `${addedProducts.length} منتج`;
    }

    function updateNetTotal(subtotal = null) {
        if (subtotal === null) {
            subtotal = addedProducts.reduce((sum, product) => sum + Number(product.total_amount || 0), 0);
        }

        const discount = Math.max(0, Number(discountInput?.value || 0));
        netTotalAmount.textContent = formatMoney(Math.max(0, subtotal - discount)) + ' ج.م';
        discountInput?.setCustomValidity(discount > subtotal ? 'قيمة الخصم لا يمكن أن تتجاوز المجموع الفرعي.' : '');
    }

    function buildHiddenInputs() {
        hiddenProductsContainer.innerHTML = '';
        addedProducts.forEach((product, pIndex) => {
            const productIdInput = document.createElement('input');
            productIdInput.type = 'hidden';
            productIdInput.name = `products[${pIndex}][product_id]`;
            productIdInput.value = product.product_id;
            hiddenProductsContainer.appendChild(productIdInput);

            product.variants.forEach((variant, vIndex) => {
                if (variant.quantity <= 0) return;

                const variantIdInput = document.createElement('input');
                variantIdInput.type = 'hidden';
                variantIdInput.name = `products[${pIndex}][variants][${vIndex}][product_variant_id]`;
                variantIdInput.value = variant.product_variant_id;
                hiddenProductsContainer.appendChild(variantIdInput);

                const qtyInput = document.createElement('input');
                qtyInput.type = 'hidden';
                qtyInput.name = `products[${pIndex}][variants][${vIndex}][quantity]`;
                qtyInput.value = variant.quantity;
                hiddenProductsContainer.appendChild(qtyInput);
            });
        });
    }

    invoiceForm?.addEventListener('submit', (e) => {
        if (addedProducts.length === 0) {
            e.preventDefault();
            alert('أضف منتجًا واحدًا على الأقل إلى الفاتورة.');
            return;
        }
        buildHiddenInputs();
    });

    function formatMoney(amount) {
        return Number(amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    renderSummary();
})();
</script>
@endpush
