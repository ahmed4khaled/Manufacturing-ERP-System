@extends('layouts.dashboard')
@section('title', $batch->batch_number)
@section('content')
<div class="page-header d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div><h1 class="h3 mb-1">{{ $batch->batch_number }} — {{ $batch->reference }}</h1><p class="text-muted mb-0">تاريخ التشغيل: {{ $batch->cutover_date->format('Y-m-d') }} | ما قبله تاريخي للقراءة فقط.</p></div>
    <div class="d-flex gap-2"><a href="{{ route('cutover.index') }}" class="btn btn-outline-secondary">كل الدفعات</a><a href="{{ route('cutover.reconciliation', $batch) }}" class="btn btn-outline-primary">المطابقة</a></div>
</div>

@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<div class="alert {{ $report['ready'] ? 'alert-success' : 'alert-warning' }} d-flex justify-content-between align-items-center">
    <div><strong>{{ $report['ready'] ? 'READY FOR GO-LIVE' : 'NOT READY' }}</strong><div>العملاء والموردون: تم ترحيلهم سابقًا — مستبعدون بالكامل من هذا الموديول.</div></div>
    <span class="badge bg-{{ $batch->status === 'posted' ? 'success' : ($batch->status === 'reversed' ? 'secondary' : 'warning') }}">{{ $batch->status }}</span>
</div>

@if($batch->status === 'draft')
@can('cutover.create')
<div class="card mb-4"><div class="card-body">
    <div class="d-flex justify-content-between"><h2 class="h5">إضافة رصيد افتتاحي</h2>@can('cutover.import')<a href="{{ route('cutover.import', $batch) }}" class="btn btn-sm btn-outline-success">استيراد CSV ومعاينة</a>@endcan</div>
    <form method="POST" action="{{ route('cutover.balances.store', $batch) }}" class="row g-3" id="opening-form">@csrf
        <div class="col-md-3"><label class="form-label">النوع *</label><select name="type" id="opening-type" class="form-select" required>
            <option value="cash_bank">خزينة / بنك</option><option value="material">خامة FIFO</option><option value="finished_goods">منتج تام FIFO</option><option value="wip">إنتاج تحت التشغيل</option><option value="worker_payable">استحقاق عامل</option><option value="partner">رصيد شريك</option><option value="other">حساب عام آخر</option>
        </select></div>
        <div class="col-md-3 opening-field" data-types="cash_bank"><label class="form-label">الخزينة / البنك</label><select name="account_id" class="form-select"><option value="">—</option>@foreach($cashAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="other"><label class="form-label">الحساب العام</label><select name="account_id" class="form-select"><option value="">—</option>@foreach($otherAccounts as $account)<option value="{{ $account->id }}">{{ $account->code }} - {{ $account->name }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="material"><label class="form-label">الخامة</label><select name="material_id" class="form-select"><option value="">—</option>@foreach($materials as $material)<option value="{{ $material->id }}">{{ $material->code }} - {{ $material->name }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="finished_goods"><label class="form-label">متغير المنتج</label><select name="product_variant_id" class="form-select"><option value="">—</option>@foreach($variants as $variant)<option value="{{ $variant->id }}">{{ $variant->sku }} - {{ $variant->product?->name }} / {{ $variant->color?->name }} / {{ $variant->size?->name }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="wip worker_payable"><label class="form-label">أمر الإنتاج</label><select name="production_order_id" class="form-select"><option value="">بدون مرجع (للعامل فقط)</option>@foreach($productionOrders as $order)<option value="{{ $order->id }}">{{ $order->order_number }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="worker_payable"><label class="form-label">العامل</label><select name="worker_id" class="form-select"><option value="">—</option>@foreach($workers as $worker)<option value="{{ $worker->id }}">{{ $worker->code }} - {{ $worker->name }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="partner"><label class="form-label">الشريك</label><select name="partner_id" class="form-select"><option value="">—</option>@foreach($partners as $partner)<option value="{{ $partner->id }}">{{ $partner->code }} - {{ $partner->name }}</option>@endforeach</select></div>
        <div class="col-md-3 opening-field" data-types="partner"><label class="form-label">نوع رصيد الشريك</label><select name="partner_balance_type" class="form-select"><option value="current">جاري</option><option value="capital">رأس مال</option><option value="withdrawals">مسحوبات</option></select></div>
        <div class="col-md-2 opening-field" data-types="material finished_goods"><label class="form-label">الكمية</label><input type="number" step="0.001" min="0.001" name="quantity" class="form-control"></div>
        <div class="col-md-2 opening-field" data-types="material finished_goods"><label class="form-label">تكلفة الوحدة</label><input type="number" step="0.0001" min="0" name="unit_cost" class="form-control"><div class="form-text">اتركها فارغة فقط كـUnvalued.</div></div>
        <div class="col-md-2 opening-field" data-types="cash_bank wip worker_payable partner other"><label class="form-label">المبلغ</label><input type="number" step="0.01" name="amount" class="form-control"><div class="form-text">السالب مسموح للشركاء والحسابات العامة.</div></div>
        <div class="col-md-3"><label class="form-label">مرجع السطر *</label><input name="layer_reference" class="form-control" required placeholder="OPEN-001"></div>
        <div class="col-md-5"><label class="form-label">الوصف</label><input name="description" class="form-control"></div>
        <div class="col-md-2 d-flex align-items-end"><button class="btn btn-primary w-100">إضافة للمسودة</button></div>
    </form>
</div></div>
@endcan
@endif

<div class="card mb-4"><div class="card-header"><strong>تفاصيل الأرصدة ({{ $batch->balances->count() }})</strong></div><div class="table-responsive"><table class="table table-sm mb-0">
    <thead><tr><th>النوع</th><th>المرجع والتفصيل</th><th>الكمية</th><th>تكلفة الوحدة</th><th>القيمة</th><th>الحساب</th><th></th></tr></thead>
    <tbody>
    @forelse($batch->balances as $line)
    <tr>
        <td>{{ $line->type }}</td><td><strong>{{ $line->layer_reference }}</strong><div class="small text-muted">{{ $line->material?->name ?? $line->productVariant?->sku ?? $line->productionOrder?->order_number ?? $line->worker?->name ?? $line->partner?->name ?? $line->description }}</div></td>
        <td>{{ $line->quantity === null ? '—' : number_format((float)$line->quantity,3) }}</td><td>{{ $line->unit_cost === null && in_array($line->type,['material','finished_goods']) ? 'Unvalued' : ($line->unit_cost === null ? '—' : number_format((float)$line->unit_cost,4)) }}</td>
        <td>{{ number_format((float)$line->amount,2) }}</td><td>{{ $line->account?->code }} - {{ $line->account?->name }}</td>
        <td>
            @if($batch->status === 'draft')
                @can('cutover.create')
                    <form method="POST" action="{{ route('cutover.balances.destroy', [$batch, $line]) }}">
                        @csrf
                        @method('DELETE')
                        <button class="btn btn-sm btn-outline-danger">حذف</button>
                    </form>
                @endcan
            @endif
        </td>
    </tr>
    @empty
    <tr><td colspan="7" class="text-center text-muted py-4">لم تُضف أرصدة بعد.</td></tr>
    @endforelse
    </tbody>
</table></div></div>

<div class="row g-3 mb-4">
@foreach($report['sections'] as $section)
<div class="col-md-4"><div class="card h-100"><div class="card-body"><div class="d-flex justify-content-between"><strong>{{ $section['label'] }}</strong><span class="badge bg-{{ $section['matched'] ? 'success' : 'danger' }}">{{ $section['matched'] ? 'مطابق' : 'Mismatch' }}</span></div><div class="small text-muted mt-2">تفصيل: {{ number_format((float)$section['expected'],2) }} | GL: {{ number_format((float)$section['actual'],2) }} | الفرق: {{ number_format((float)$section['difference'],2) }}</div></div></div></div>
@endforeach
</div>

<div class="d-flex flex-wrap gap-2">
@if($batch->status === 'draft')
    @can('cutover.post')
        <form method="POST" action="{{ route('cutover.post', $batch) }}" onsubmit="return confirm('سيتم إنشاء المخزون وFIFO والقيد المحاسبي ولن يمكن تعديل الدفعة. متابعة؟')">
            @csrf
            <button class="btn btn-success">اعتماد وترحيل الـCutover</button>
        </form>
    @endcan
@endif
@if($batch->status === 'posted')
    @can('cutover.reverse')
        <form method="POST" action="{{ route('cutover.reverse', $batch) }}" class="d-flex gap-2" onsubmit="return confirm('العكس مسموح فقط إذا لم تُستهلك الطبقات أو تُدفع الاستحقاقات. متابعة؟')">
            @csrf
            <input type="date" name="reversal_date" value="{{ now()->toDateString() }}" min="{{ $batch->cutover_date->toDateString() }}" class="form-control" required>
            <button class="btn btn-outline-danger">عكس الدفعة</button>
        </form>
    @endcan
@endif
</div>
<div class="alert alert-light mt-4 mb-0"><strong>قاعدة العمل:</strong> الأرصدة الافتتاحية تمثل الحالة المالية والمخزنية المتحقق منها عند تاريخ الانتقال، وليست معاملات تاريخية. بعد الـGo-Live تدخل كل المعاملات الجديدة من موديولات ERP التشغيلية.</div>
@endsection
@push('scripts')<script>
(() => { const select=document.getElementById('opening-type'); const refresh=()=>document.querySelectorAll('.opening-field').forEach(el=>{const show=el.dataset.types.split(' ').includes(select.value);el.classList.toggle('d-none',!show);el.querySelectorAll('input,select').forEach(x=>x.disabled=!show);}); select?.addEventListener('change',refresh); refresh(); })();
</script>@endpush
