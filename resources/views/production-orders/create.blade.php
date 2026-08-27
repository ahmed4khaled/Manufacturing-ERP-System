@extends('layouts.dashboard')

@section('title', 'أمر إنتاج جديد')
@section('breadcrumb')
    <ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('production-orders.index') }}">أوامر الإنتاج</a></li><li class="breadcrumb-item active">جديد</li></ol>
@endsection

@section('content')
<style>
    .wizard-step { display: none; }
    .wizard-step.active { display: block; }
    .step-pill.active { background: #0d6efd !important; color: #fff !important; }
    .allocation-operation { border: 1px solid #dee2e6; border-radius: 6px; overflow: hidden; }
    .allocation-assignment + .allocation-assignment { border-top: 1px solid #e9ecef; }
</style>

<div class="d-flex gap-2 mb-3 flex-wrap">
    @foreach (['بيانات الإنتاج', 'متطلبات الخامات', 'العمال والعمليات', 'تخصيص الخامات'] as $i => $label)
        <span class="badge bg-light text-dark border p-2 step-pill {{ $i === 0 ? 'active' : '' }}" data-step="{{ $i }}">{{ $i + 1 }}. {{ $label }}</span>
    @endforeach
</div>

@if ($errors->any())
    <div class="alert alert-danger"><strong>تعذر إنشاء أمر الإنتاج:</strong><ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
@if ($boms->isEmpty())
    <div class="alert alert-warning">لا توجد قائمة BOM نشطة. <a href="{{ route('boms.create') }}">أنشئ وفعّل BOM أولًا.</a></div>
@endif

<form method="POST" action="{{ route('production-orders.store') }}" id="production-form">
    @csrf
    <div class="card">
        <div class="card-body">
            <section class="wizard-step active" data-step="0">
                <h5 class="mb-3">بيانات الإنتاج</h5>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">المنتج *</label><select id="product_id" name="product_id" class="form-select" required><option value="">اختر</option>@foreach ($boms->unique('product_id') as $bom)<option value="{{ $bom->product_id }}">{{ $bom->product?->name ?? '-' }}</option>@endforeach</select></div>
                    <div class="col-md-4"><label class="form-label">المتغير *</label><select id="variant_id" name="product_variant_id" class="form-select" required><option value="">اختر</option></select></div>
                    <div class="col-md-4"><label class="form-label">BOM *</label><select id="bom_id" name="bom_id" class="form-select" required><option value="">اختر</option></select></div>
                    <div class="col-md-4"><label class="form-label">الكمية المخططة *</label><input id="planned_quantity" name="planned_quantity" type="number" min="0.001" step="0.001" class="form-control" value="{{ old('planned_quantity') }}" required></div>
                    <div class="col-md-4"><label class="form-label">بدء مخطط *</label><input name="planned_start_date" type="date" class="form-control" value="{{ old('planned_start_date', now()->toDateString()) }}" required></div>
                    <div class="col-md-4"><label class="form-label">إكمال مخطط</label><input name="planned_completion_date" type="date" class="form-control" value="{{ old('planned_completion_date') }}"></div>
                    <div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" class="form-control">{{ old('notes') }}</textarea></div>
                </div>
            </section>

            <section class="wizard-step" data-step="1">
                <h5>متطلبات الخامات</h5>
                <div class="table-responsive"><table class="table table-bordered"><thead><tr><th>الخامة</th><th>المطلوب</th><th>المتاح</th><th>الحالة</th></tr></thead><tbody id="requirements-body"><tr><td colspan="4" class="text-center text-muted">حدد BOM والكمية أولًا.</td></tr></tbody></table></div>
            </section>

            <section class="wizard-step" data-step="2">
                <h5>العمال والعمليات</h5>
                <div id="operations-assignments"></div>
            </section>

            <section class="wizard-step" data-step="3">
                <h5 class="mb-3">تخصيص وعهدة الخامات</h5>
                <div id="allocation-container"></div>
                <div class="mt-4">
                    <h6>ملخص تخصيص الخامات</h6>
                    <div class="table-responsive"><table class="table table-bordered align-middle mb-0"><thead><tr><th>الخامة</th><th>المطلوب</th><th>المخصص</th><th>المتبقي</th><th>الحالة</th></tr></thead><tbody id="allocation-summary"></tbody></table></div>
                </div>
            </section>
        </div>
        <div class="card-footer d-flex justify-content-between">
            <button type="button" id="prev-step" class="btn btn-outline-secondary" disabled>السابق</button>
            <div><button type="button" id="next-step" class="btn btn-primary">التالي</button><button type="submit" id="submit-order" class="btn btn-success d-none" disabled>إنشاء أمر الإنتاج</button></div>
        </div>
    </div>
</form>
@endsection

@push('scripts')
<script>
const boms = {{ Illuminate\Support\Js::from($bomWizardData) }};
const workers = {{ Illuminate\Support\Js::from($workerWizardData) }};
let step = 0;
let assignments = [];
let selectedBom = null;
let allocationValues = {};
const product = document.getElementById('product_id');
const variant = document.getElementById('variant_id');
const bomSelect = document.getElementById('bom_id');
const plannedQuantity = document.getElementById('planned_quantity');
const submitButton = document.getElementById('submit-order');
const escapeHtml = value => String(value ?? '').replace(/[&<>'"]/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[character]));
const option = (value, label) => `<option value="${value}">${escapeHtml(label)}</option>`;
const workerOptions = selected => '<option value="">اختر العامل</option>' + workers.map(worker => `<option value="${worker.id}" ${String(worker.id) === String(selected) ? 'selected' : ''}>${escapeHtml(worker.name)} (${escapeHtml(worker.code)})</option>`).join('');
const decimal = value => Number(value || 0).toFixed(3);

function refreshVariants() {
    const list = [...new Map(boms.filter(bom => String(bom.product_id) === product.value).map(bom => [bom.variant_id, bom])).values()];
    variant.innerHTML = '<option value="">اختر</option>' + list.map(bom => option(bom.variant_id, bom.variant)).join('');
    bomSelect.innerHTML = '<option value="">اختر</option>';
    selectedBom = null;
    assignments = [];
    allocationValues = {};
    renderAll();
}
function refreshBoms() {
    const list = boms.filter(bom => String(bom.variant_id) === variant.value);
    bomSelect.innerHTML = '<option value="">اختر</option>' + list.map(bom => option(bom.id, `${bom.number} / v${bom.version}`)).join('');
    selectedBom = null;
    assignments = [];
    allocationValues = {};
    renderAll();
}
function chooseBom() {
    selectedBom = boms.find(bom => String(bom.id) === bomSelect.value) || null;
    assignments = [];
    allocationValues = {};
    (selectedBom?.operations || []).forEach(operation => assignments.push({key: crypto.randomUUID(), operation_id: operation.id, worker_id: '', quantity: '', notes: ''}));
    renderAll();
}
function requirements() {
    const amount = Number(plannedQuantity.value || 0);
    return (selectedBom?.items || []).map(item => ({...item, required: Number(((item.quantity / selectedBom.basis) * amount).toFixed(3))}));
}
function operationRequirements(operationId) {
    const amount = Number(plannedQuantity.value || 0);
    return (selectedBom?.items || []).flatMap(item => {
        let distributed = 0;
        let operationRequired = null;
        const totalRequired = Number(((item.quantity / selectedBom.basis) * amount).toFixed(3));
        item.operations.forEach((mapping, index) => {
            const quantity = index === item.operations.length - 1 ? Number((totalRequired - distributed).toFixed(3)) : Number(((mapping.quantity / selectedBom.basis) * amount).toFixed(3));
            distributed = Number((distributed + quantity).toFixed(3));
            if (String(mapping.bom_operation_id) === String(operationId)) operationRequired = quantity;
        });
        return operationRequired === null ? [] : [{...item, operation_required: operationRequired}];
    });
}
function renderRequirements() {
    const rows = requirements();
    document.getElementById('requirements-body').innerHTML = rows.length ? rows.map(item => `<tr><td>${escapeHtml(item.name)}</td><td>${decimal(item.required)} ${escapeHtml(item.unit)}</td><td>${decimal(item.available)}</td><td><span class="badge bg-${item.available >= item.required ? 'success' : 'danger'}">${item.available >= item.required ? 'متاح' : 'غير كافٍ'}</span></td></tr>`).join('') : '<tr><td colspan="4" class="text-center text-muted">حدد BOM والكمية أولًا.</td></tr>';
}
function addAssignment(operationId) {
    assignments.push({key: crypto.randomUUID(), operation_id: operationId, worker_id: '', quantity: '', notes: ''});
    allocationValues = {};
    renderAssignments();
}
function removeAssignment(key) {
    assignments = assignments.filter(assignment => assignment.key !== key);
    allocationValues = {};
    renderAssignments();
}
function setAssignment(key, field, value) {
    const assignment = assignments.find(item => item.key === key);
    if (assignment) assignment[field] = value;
    if (field === 'quantity') allocationValues = {};
}
function renderAssignments() {
    const target = document.getElementById('operations-assignments');
    if (! selectedBom) {
        target.innerHTML = '<div class="alert alert-light">حدد BOM أولًا.</div>';
        return;
    }
    target.innerHTML = selectedBom.operations.map(operation => {
        const rows = assignments.filter(assignment => assignment.operation_id === operation.id);
        const total = rows.reduce((sum, assignment) => sum + Number(assignment.quantity || 0), 0);
        return `<div class="card mb-3"><div class="card-header bg-white d-flex justify-content-between align-items-center"><div><strong>${operation.sequence}. ${escapeHtml(operation.name)}</strong><small class="d-block text-muted">تكلفة العباية: ${Number(operation.unit_cost || 0).toFixed(2)} ج.م</small></div><div>المسند: <b>${decimal(total)}</b> / ${decimal(plannedQuantity.value)} <button type="button" class="btn btn-sm btn-outline-primary ms-2" onclick="addAssignment(${operation.id})">عامل إضافي</button></div></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>العامل</th><th>الكمية</th><th>ملاحظات</th><th></th></tr></thead><tbody>${rows.map(assignment => `<tr><td><select class="form-select" onchange="setAssignment('${assignment.key}','worker_id',this.value)">${workerOptions(assignment.worker_id)}</select></td><td><input class="form-control" type="number" step="0.001" min="0.001" value="${escapeHtml(assignment.quantity)}" onchange="setAssignment('${assignment.key}','quantity',this.value);renderAssignments()"></td><td><input class="form-control" value="${escapeHtml(assignment.notes)}" onchange="setAssignment('${assignment.key}','notes',this.value)"></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeAssignment('${assignment.key}')">حذف</button></td></tr>`).join('')}</tbody></table></div></div>`;
    }).join('');
}
function suggestedAllocations(operationId, item) {
    const operationAssignments = assignments.filter(assignment => assignment.operation_id === operationId);
    let assigned = 0;
    return operationAssignments.map((assignment, index) => {
        const value = index === operationAssignments.length - 1 ? Number((item.operation_required - assigned).toFixed(3)) : Number((item.operation_required * (Number(assignment.quantity || 0) / Number(plannedQuantity.value || 1))).toFixed(3));
        assigned = Number((assigned + value).toFixed(3));
        return [assignment.key, Math.max(0, value)];
    });
}
function prepareAllocationValues() {
    selectedBom.operations.forEach(operation => operationRequirements(operation.id).forEach(item => {
        suggestedAllocations(operation.id, item).forEach(([assignmentKey, value]) => {
            const key = `${assignmentKey}-${item.id}`;
            if (allocationValues[key] === undefined) allocationValues[key] = decimal(value);
        });
    }));
}
function hiddenAssignment(assignment, index) {
    return `<input type="hidden" name="assignments[${index}][bom_operation_id]" value="${assignment.operation_id}"><input type="hidden" name="assignments[${index}][worker_id]" value="${escapeHtml(assignment.worker_id)}"><input type="hidden" name="assignments[${index}][assigned_quantity]" value="${escapeHtml(assignment.quantity)}"><input type="hidden" name="assignments[${index}][notes]" value="${escapeHtml(assignment.notes)}">`;
}
function allocationStatus(allocated, required) {
    if (Math.abs(allocated - required) < 0.0005) return ['Complete', 'success'];
    if (allocated > required) return ['Over Allocated', 'danger'];
    return ['Partially Allocated', 'warning'];
}
function renderAllocations() {
    const target = document.getElementById('allocation-container');
    if (! selectedBom) {
        target.innerHTML = '<div class="alert alert-light">حدد BOM أولًا.</div>';
        return;
    }
    prepareAllocationValues();
    let assignmentIndex = 0;
    target.innerHTML = selectedBom.operations.map(operation => {
        const operationAssignments = assignments.filter(assignment => assignment.operation_id === operation.id);
        const materials = operationRequirements(operation.id);
        const content = operationAssignments.map(assignment => {
            const worker = workers.find(item => String(item.id) === String(assignment.worker_id));
            const index = assignmentIndex++;
            return `<div class="allocation-assignment p-3"><div class="d-flex justify-content-between align-items-center mb-3"><div><strong>${escapeHtml(worker?.name || 'لم يحدد العامل')}</strong><small class="d-block text-muted">الكمية المسندة: ${decimal(assignment.quantity)}</small></div></div>${hiddenAssignment(assignment, index)}${materials.length ? `<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>الخامة</th><th>مطلوب للعملية</th><th>المخصص للعامل</th><th>المتبقي للعملية</th></tr></thead><tbody>${materials.map(item => { const key = `${assignment.key}-${item.id}`; return `<tr><td>${escapeHtml(item.name)}</td><td>${decimal(item.operation_required)} ${escapeHtml(item.unit)}</td><td><input data-allocation-input data-item-id="${item.id}" data-operation-id="${operation.id}" data-required="${item.operation_required}" name="assignments[${index}][materials][${item.id}][allocated_quantity]" class="form-control form-control-sm" type="number" min="0" step="0.001" value="${allocationValues[key]}" oninput="allocationValues['${key}']=this.value;updateAllocationStatus()"></td><td data-operation-remaining="${operation.id}-${item.id}"></td></tr>`; }).join('')}</tbody></table></div>` : '<div class="text-muted">لا توجد خامات مخصصة لهذه العملية.</div>'}</div>`;
        }).join('');
        return `<div class="allocation-operation mb-3"><div class="bg-light px-3 py-2 d-flex justify-content-between align-items-center"><div><strong>${operation.sequence}. ${escapeHtml(operation.name)}</strong><small class="d-block text-muted">${operationAssignments.length} تكليف</small></div><span class="badge operation-status" data-operation-status="${operation.id}">${materials.length ? '' : 'No Materials'}</span></div>${content}</div>`;
    }).join('');
    updateAllocationStatus();
}
function updateAllocationStatus() {
    const inputs = [...document.querySelectorAll('[data-allocation-input]')];
    const operationTotals = {};
    const materialTotals = {};
    inputs.forEach(input => {
        const value = Number(input.value || 0);
        const operationKey = `${input.dataset.operationId}-${input.dataset.itemId}`;
        operationTotals[operationKey] = (operationTotals[operationKey] || 0) + value;
        materialTotals[input.dataset.itemId] = (materialTotals[input.dataset.itemId] || 0) + value;
    });
    let valid = Boolean(selectedBom) && assignments.every(assignment => assignment.worker_id && Number(assignment.quantity) > 0);
    selectedBom?.operations.forEach(operation => {
        const assignedQuantity = assignments.filter(assignment => assignment.operation_id === operation.id).reduce((sum, assignment) => sum + Number(assignment.quantity || 0), 0);
        valid = valid && Math.abs(assignedQuantity - Number(plannedQuantity.value || 0)) < 0.0005;
        const materials = operationRequirements(operation.id);
        let operationValid = true;
        let over = false;
        materials.forEach(item => {
            const allocated = operationTotals[`${operation.id}-${item.id}`] || 0;
            document.querySelectorAll(`[data-operation-remaining="${operation.id}-${item.id}"]`).forEach(cell => cell.textContent = decimal(item.operation_required - allocated));
            if (Math.abs(allocated - item.operation_required) >= 0.0005) operationValid = false;
            if (allocated > item.operation_required + 0.0005) over = true;
        });
        const badge = document.querySelector(`[data-operation-status="${operation.id}"]`);
        if (! materials.length) {
            badge.className = 'badge operation-status bg-secondary';
            badge.textContent = 'No Materials';
        } else {
            const status = over ? ['Over Allocated', 'danger'] : operationValid ? ['Complete', 'success'] : ['Partially Allocated', 'warning'];
            badge.className = `badge operation-status bg-${status[1]}`;
            badge.textContent = status[0];
            valid = valid && operationValid;
        }
    });
    const rows = requirements();
    document.getElementById('allocation-summary').innerHTML = rows.map(item => {
        const allocated = Number((materialTotals[item.id] || 0).toFixed(3));
        const remaining = Number((item.required - allocated).toFixed(3));
        const status = allocationStatus(allocated, item.required);
        valid = valid && status[0] === 'Complete';
        return `<tr><td>${escapeHtml(item.name)}</td><td>${decimal(item.required)}</td><td>${decimal(allocated)}</td><td>${decimal(remaining)}</td><td><span class="badge bg-${status[1]}">${status[0]}</span></td></tr>`;
    }).join('');
    submitButton.disabled = ! valid || {{ $boms->isEmpty() ? 'true' : 'false' }};
}
function renderAll() {
    renderRequirements();
    renderAssignments();
}
function showStep(nextStep) {
    step = nextStep;
    document.querySelectorAll('.wizard-step').forEach(element => element.classList.toggle('active', Number(element.dataset.step) === step));
    document.querySelectorAll('.step-pill').forEach(element => element.classList.toggle('active', Number(element.dataset.step) === step));
    document.getElementById('prev-step').disabled = step === 0;
    document.getElementById('next-step').classList.toggle('d-none', step === 3);
    submitButton.classList.toggle('d-none', step !== 3);
    if (step === 3) renderAllocations();
}
product.addEventListener('change', refreshVariants);
variant.addEventListener('change', refreshBoms);
bomSelect.addEventListener('change', chooseBom);
plannedQuantity.addEventListener('input', () => { allocationValues = {}; renderAll(); });
document.getElementById('next-step').addEventListener('click', () => showStep(Math.min(3, step + 1)));
document.getElementById('prev-step').addEventListener('click', () => showStep(Math.max(0, step - 1)));
</script>
@endpush
