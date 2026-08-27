@php
    $selectedItems = old('items', isset($bom) ? $bom->items->map(fn ($item) => [
        'material_id' => $item->material_id,
        'quantity' => $item->quantity,
        'operation_allocations' => $item->operationAllocations->map(fn ($allocation) => [
            'manufacturing_operation_id' => $allocation->bomOperation->manufacturing_operation_id,
            'quantity' => $allocation->quantity,
        ])->values()->all(),
    ])->all() : [[
        'material_id' => '',
        'quantity' => '',
        'operation_allocations' => [['manufacturing_operation_id' => '', 'quantity' => '']],
    ]]);
    $selectedOperations = old('operations', isset($bom) ? $bom->operations->map(fn ($operation) => [
        'manufacturing_operation_id' => $operation->manufacturing_operation_id,
        'unit_cost' => $operation->unit_cost,
        'notes' => $operation->notes,
    ])->all() : [['manufacturing_operation_id' => '', 'unit_cost' => '', 'notes' => '']]);
@endphp

@if ($errors->any())
    <div class="alert alert-danger"><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif

<div class="row g-3">
    <div class="col-md-4"><label class="form-label">المنتج *</label><select id="product_id" name="product_id" class="form-select" required><option value="">اختر</option>@foreach ($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id', $bom->product_id ?? '') === (string) $product->id)>{{ $product->name }} ({{ $product->code }})</option>@endforeach</select></div>
    <div class="col-md-5"><label class="form-label">المتغير *</label><select id="product_variant_id" name="product_variant_id" class="form-select" required><option value="">اختر</option>@foreach ($products as $product)@foreach ($product->variants as $variant)<option data-product="{{ $product->id }}" value="{{ $variant->id }}" @selected((string) old('product_variant_id', $bom->product_variant_id ?? '') === (string) $variant->id)>{{ $product->name }} - {{ $variant->color?->name }} / {{ $variant->size?->name }} ({{ $variant->sku }})</option>@endforeach @endforeach</select></div>
    <div class="col-md-3"><label class="form-label">كمية الأساس *</label><input type="number" step="0.001" min="0.001" name="basis_quantity" class="form-control" value="{{ old('basis_quantity', $bom->basis_quantity ?? '1.000') }}" required></div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center"><strong>تسلسل العمليات</strong><button type="button" id="add-operation" class="btn btn-sm btn-outline-primary">إضافة عملية</button></div>
    <div class="table-responsive"><table class="table mb-0"><thead><tr><th>#</th><th>العملية</th><th>ملاحظات</th><th></th></tr></thead><tbody id="operations-body">
        @foreach ($selectedOperations as $i => $operation)
            <tr><td class="sequence">{{ $i + 1 }}</td><td><select name="operations[{{ $i }}][manufacturing_operation_id]" class="form-select bom-operation-select" required><option value="">اختر</option>@foreach ($operations as $op)<option value="{{ $op->id }}" @selected((string) ($operation['manufacturing_operation_id'] ?? '') === (string) $op->id)>{{ $op->name }}</option>@endforeach</select></td><td><input name="operations[{{ $i }}][notes]" class="form-control" value="{{ $operation['notes'] ?? '' }}"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-row">حذف</button></td></tr>
        @endforeach
    </tbody></table></div>
</div>

<div class="card mt-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center"><div><strong>الخامات وتوزيعها على العمليات</strong><small class="d-block text-muted">يجب أن يساوي مجموع كميات العمليات كمية الخامة.</small></div><button type="button" id="add-material" class="btn btn-sm btn-outline-primary">إضافة خامة</button></div>
    <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>الخامة</th><th>الكمية للأساس</th><th style="min-width:340px">عمليات الاستهلاك</th><th></th></tr></thead><tbody id="materials-body">
        @foreach ($selectedItems as $i => $item)
            <tr data-material-index="{{ $i }}"><td><select name="items[{{ $i }}][material_id]" class="form-select" required><option value="">اختر</option>@foreach ($materials as $material)<option value="{{ $material->id }}" @selected((string) ($item['material_id'] ?? '') === (string) $material->id)>{{ $material->name }} ({{ $material->unit->label() }})</option>@endforeach</select></td><td><input name="items[{{ $i }}][quantity]" type="number" step="0.001" min="0.001" class="form-control" value="{{ $item['quantity'] ?? '' }}" required></td><td><div class="operation-allocations">
                @foreach (($item['operation_allocations'] ?? []) as $j => $allocation)
                    <div class="input-group input-group-sm mb-2 allocation-row"><select name="items[{{ $i }}][operation_allocations][{{ $j }}][manufacturing_operation_id]" class="form-select allocation-operation" data-selected="{{ $allocation['manufacturing_operation_id'] ?? '' }}" required></select><input name="items[{{ $i }}][operation_allocations][{{ $j }}][quantity]" type="number" step="0.001" min="0.001" class="form-control" value="{{ $allocation['quantity'] ?? '' }}" placeholder="الكمية" required><button type="button" class="btn btn-outline-danger remove-allocation">حذف</button></div>
                @endforeach
            </div><button type="button" class="btn btn-sm btn-outline-secondary add-allocation">إضافة عملية استهلاك</button></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-row">حذف</button></td></tr>
        @endforeach
    </tbody></table></div>
</div>

<div class="mt-3"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control">{{ old('notes', $bom->notes ?? '') }}</textarea></div>
<div class="mt-4"><button class="btn btn-primary">حفظ BOM</button> <a href="{{ route('boms.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>

@push('scripts')
<script>
const materialOptions = {{ Illuminate\Support\Js::from($materials->map(fn ($material) => ['id' => $material->id, 'label' => $material->name.' ('.$material->unit->label().')'])->values()) }};
const operationOptions = {{ Illuminate\Support\Js::from($operations->map(fn ($operation) => ['id' => $operation->id, 'label' => $operation->name, 'cost' => (float) $operation->default_unit_cost])->values()) }};
const selectedOperationCosts = {{ Illuminate\Support\Js::from(collect($selectedOperations)->mapWithKeys(fn ($operation) => [(string) ($operation['manufacturing_operation_id'] ?? '') => $operation['unit_cost'] ?? ''])) }};
let materialIndex = {{ count($selectedItems) }};
let operationIndex = {{ count($selectedOperations) }};
const optionTags = (items, selected = '') => '<option value="">اختر</option>' + items.map(item => `<option value="${item.id}" ${String(item.id) === String(selected) ? 'selected' : ''}>${item.label}</option>`).join('');
const selectedBomOperations = () => [...document.querySelectorAll('.bom-operation-select')].map(select => select.value).filter(Boolean);
function refreshAllocationOptions() {
    const selectedOperations = selectedBomOperations();
    document.querySelectorAll('.allocation-operation').forEach(select => {
        const selected = select.value || select.dataset.selected || '';
        select.innerHTML = optionTags(operationOptions.filter(operation => selectedOperations.includes(String(operation.id))), selected);
        select.dataset.selected = '';
    });
}
function ensureOperationCostInputs() {
    document.querySelectorAll('.bom-operation-select').forEach(select => {
        let input = select.parentElement.querySelector('.operation-unit-cost');
        if (! input) {
            const group = document.createElement('div');
            group.className = 'input-group input-group-sm mt-2 operation-cost-group';
            group.innerHTML = `<span class="input-group-text">تكلفة العباية</span><input type="number" min="0" step="0.01" class="form-control operation-unit-cost" name="${select.name.replace('[manufacturing_operation_id]', '[unit_cost]')}" required><span class="input-group-text">ج.م</span>`;
            select.parentElement.appendChild(group);
            input = group.querySelector('input');
        }
        const defaultCost = operationOptions.find(operation => String(operation.id) === String(select.value))?.cost ?? 0;
        if (input.value === '') input.value = selectedOperationCosts[String(select.value)] ?? Number(defaultCost).toFixed(2);
    });
}
function allocationRow(itemIndex, allocationIndex) {
    return `<div class="input-group input-group-sm mb-2 allocation-row"><select name="items[${itemIndex}][operation_allocations][${allocationIndex}][manufacturing_operation_id]" class="form-select allocation-operation" required></select><input name="items[${itemIndex}][operation_allocations][${allocationIndex}][quantity]" type="number" step="0.001" min="0.001" class="form-control" placeholder="الكمية" required><button type="button" class="btn btn-outline-danger remove-allocation">حذف</button></div>`;
}
document.getElementById('add-material').addEventListener('click', () => {
    document.getElementById('materials-body').insertAdjacentHTML('beforeend', `<tr data-material-index="${materialIndex}"><td><select name="items[${materialIndex}][material_id]" class="form-select" required>${optionTags(materialOptions)}</select></td><td><input name="items[${materialIndex}][quantity]" type="number" step="0.001" min="0.001" class="form-control" required></td><td><div class="operation-allocations">${allocationRow(materialIndex, 0)}</div><button type="button" class="btn btn-sm btn-outline-secondary add-allocation">إضافة عملية استهلاك</button></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-row">حذف</button></td></tr>`);
    materialIndex++;
    refreshAllocationOptions();
});
document.getElementById('add-operation').addEventListener('click', () => {
    document.getElementById('operations-body').insertAdjacentHTML('beforeend', `<tr><td class="sequence"></td><td><select name="operations[${operationIndex}][manufacturing_operation_id]" class="form-select bom-operation-select" required>${optionTags(operationOptions)}</select></td><td><input name="operations[${operationIndex}][notes]" class="form-control"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-row">حذف</button></td></tr>`);
    operationIndex++;
    renumberOperations();
    ensureOperationCostInputs();
});
document.addEventListener('change', event => {
    if (event.target.classList.contains('bom-operation-select')) {
        refreshAllocationOptions();
        const defaultCost = operationOptions.find(operation => String(operation.id) === String(event.target.value))?.cost ?? 0;
        event.target.parentElement.querySelector('.operation-unit-cost').value = Number(defaultCost).toFixed(2);
        ensureOperationCostInputs();
    }
});
document.addEventListener('click', event => {
    if (event.target.classList.contains('remove-row')) {
        event.target.closest('tr').remove();
        renumberOperations();
        refreshAllocationOptions();
    }
    if (event.target.classList.contains('remove-allocation')) event.target.closest('.allocation-row').remove();
    if (event.target.classList.contains('add-allocation')) {
        const row = event.target.closest('tr');
        const container = row.querySelector('.operation-allocations');
        container.insertAdjacentHTML('beforeend', allocationRow(row.dataset.materialIndex, container.querySelectorAll('.allocation-row').length));
        refreshAllocationOptions();
    }
});
function renumberOperations() {
    document.querySelectorAll('#operations-body .sequence').forEach((cell, index) => cell.textContent = index + 1);
}
const product = document.getElementById('product_id');
const variant = document.getElementById('product_variant_id');
function filterVariants() {
    [...variant.options].forEach((option, index) => { if (index) option.hidden = option.dataset.product !== product.value; });
    if (variant.selectedOptions[0]?.hidden) variant.value = '';
}
product.addEventListener('change', filterVariants);
filterVariants();
ensureOperationCostInputs();
refreshAllocationOptions();
</script>
@endpush
