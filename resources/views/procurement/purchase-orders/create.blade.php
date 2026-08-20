@extends('layouts.dashboard')
@section('title', 'إنشاء أمر شراء')
@section('content')
    <div class="page-header">
        <h1 class="h3 mb-1">إنشاء أمر شراء</h1>
        <p class="text-muted mb-0">أضف الخامات وتكاليف المورد.</p>
    </div>
    <form method="POST" action="{{ route('purchase-orders.store') }}">@csrf <div class="card">
            <div class="card-body">
                <div class="row g-3 mb-4">
                    <div class="col-md-6"><label class="form-label">المورد *</label><select name="supplier_id"
                            class="form-select" required>
                            <option value="">اختر المورد</option>
                            @foreach ($suppliers as $supplier)
                                <option value="{{ $supplier->id }}">{{ $supplier->code }} — {{ $supplier->name }}</option>
                            @endforeach
                        </select></div>
                    <div class="col-md-3"><label class="form-label">تاريخ الطلب *</label><input type="date"
                            name="order_date" value="{{ old('order_date', now()->toDateString()) }}" class="form-control"
                            required></div>
                    <div class="col-md-3"><label class="form-label">التسليم المتوقع</label><input type="date"
                            name="expected_delivery_date" class="form-control"></div>
                </div>
                <div class="table-responsive">
                    <table class="table" id="items">
                        <thead>
                            <tr>
                                <th>الخامة</th>
                                <th width="120">الكمية</th>
                                <th width="150">سعر الوحدة</th>
                                <th width="120">الخصم</th>
                                <th width="120">الضريبة</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div><button type="button" class="btn btn-outline-primary btn-sm" id="addItem"><i
                        class="bi bi-plus-lg"></i> إضافة بند</button>
                <div class="row g-3 mt-3">
                    <div class="col-md-3"><label class="form-label">خصم الطلب</label><input name="discount"
                            type="number" min="0" step=".01" value="0" class="form-control"></div>
                    <div class="col-md-3"><label class="form-label">ضريبة الطلب</label><input name="tax" type="number"
                            min="0" step=".01" value="0" class="form-control"></div>
                    <div class="col-12"><label class="form-label">ملاحظات</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div class="mt-3"><button class="btn btn-primary">حفظ كمسودة</button><a
                href="{{ route('purchase-orders.index') }}" class="btn btn-outline-secondary">إلغاء</a></div>
    </form>@push('scripts')
<script>
    const tbody = document.querySelector('#items tbody');
    const addItemButton = document.getElementById('addItem');

    const opts = `
        <option value="">اختر الخامة</option>
        @foreach ($materials as $m)
            <option value="{{ $m->id }}">
                {{ $m->code }} — {{ addslashes($m->name) }} ({{ $m->unit->label() }})
            </option>
        @endforeach
    `;

    let itemIndex = 0;

    function addItem() {
        const row = document.createElement('tr');

        row.innerHTML = `
            <td>
                <select name="items[${itemIndex}][material_id]"
                        class="form-select material-select"
                        required>
                    ${opts}
                </select>
            </td>

            <td>
                <input type="number"
                       name="items[${itemIndex}][quantity]"
                       min="0.001"
                       step="0.001"
                       value="1"
                       class="form-control"
                       required>
            </td>

            <td>
                <input type="number"
                       name="items[${itemIndex}][unit_price]"
                       min="0"
                       step="0.01"
                       value="0"
                       class="form-control"
                       required>
            </td>

            <td>
                <input type="number"
                       name="items[${itemIndex}][discount]"
                       min="0"
                       step="0.01"
                       value="0"
                       class="form-control">
            </td>

            <td>
                <input type="number"
                       name="items[${itemIndex}][tax]"
                       min="0"
                       step="0.01"
                       value="0"
                       class="form-control">
            </td>

            <td>
                <button type="button"
                        class="btn btn-outline-danger remove-item">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);

        itemIndex++;
    }

    addItemButton.addEventListener('click', addItem);

    // Prevent duplicate materials
    tbody.addEventListener('change', function (event) {
        if (!event.target.classList.contains('material-select')) {
            return;
        }

        const currentSelect = event.target;
        const selectedValue = currentSelect.value;

        if (!selectedValue) {
            return;
        }

        const allSelects = tbody.querySelectorAll('.material-select');

        let duplicate = false;

        allSelects.forEach(select => {
            if (select !== currentSelect && select.value === selectedValue) {
                duplicate = true;
            }
        });

        if (duplicate) {
            alert('تمت إضافة هذه الخامة بالفعل إلى أمر الشراء.');

            currentSelect.value = '';
        }
    });

    // Remove item
    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('.remove-item');

        if (!button) {
            return;
        }

        button.closest('tr').remove();
    });

    // Add first item automatically
    addItem();
</script>
@endpush
@endsection
