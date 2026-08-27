@php
    $fieldConfig = [
        'product' => ['name' => 'product_id', 'label' => 'المنتج', 'options' => 'products'],
        'worker' => ['name' => 'worker_id', 'label' => 'العامل', 'options' => 'workers'],
        'customer' => ['name' => 'customer_id', 'label' => 'العميل', 'options' => 'customers'],
        'supplier' => ['name' => 'supplier_id', 'label' => 'المورد', 'options' => 'suppliers'],
        'material' => ['name' => 'material_id', 'label' => 'الخامة', 'options' => 'materials'],
        'operation' => ['name' => 'operation_id', 'label' => 'العملية', 'options' => 'operations'],
    ];
@endphp

<div class="card filter-card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route($routeName) }}" class="row g-3 align-items-end">
            <div class="col-sm-6 col-xl-2">
                <label class="form-label" for="stats-preset">الفترة</label>
                <select name="preset" id="stats-preset" class="form-select">
                    @foreach(['today' => 'اليوم', 'week' => 'هذا الأسبوع', 'month' => 'هذا الشهر', 'last_3_months' => 'آخر 3 أشهر', 'year' => 'هذه السنة', 'custom' => 'مخصص'] as $value => $label)
                        <option value="{{ $value }}" @selected($filters['preset'] === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-sm-6 col-xl-2 custom-date">
                <label class="form-label" for="stats-date-from">من</label>
                <input id="stats-date-from" type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] }}">
            </div>
            <div class="col-sm-6 col-xl-2 custom-date">
                <label class="form-label" for="stats-date-to">إلى</label>
                <input id="stats-date-to" type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] }}">
            </div>
            @foreach($filterFields as $field)
                @php($config = $fieldConfig[$field])
                <div class="col-sm-6 col-xl-2">
                    <label class="form-label" for="stats-{{ $config['name'] }}">{{ $config['label'] }}</label>
                    <select id="stats-{{ $config['name'] }}" name="{{ $config['name'] }}" class="form-select">
                        <option value="">الكل</option>
                        @foreach($filterOptions[$config['options']] as $item)
                            <option value="{{ $item->id }}" @selected($filters[$config['name']] === $item->id)>{{ $item->name }}</option>
                        @endforeach
                    </select>
                </div>
            @endforeach
            <div class="col-sm-6 col-xl-3 d-flex gap-2">
                <button class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> تطبيق</button>
                <a href="{{ route($routeName) }}" class="btn btn-outline-secondary" title="إعادة ضبط الفلاتر"><i class="bi bi-arrow-counterclockwise"></i></a>
            </div>
        </form>
    </div>
</div>
