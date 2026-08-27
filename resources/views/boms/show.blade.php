@extends('layouts.dashboard')
@section('title', $bom->bom_number)
@section('breadcrumb')<ol class="breadcrumb mb-0"><li class="breadcrumb-item"><a href="{{ route('boms.index') }}">BOM</a></li><li class="breadcrumb-item active">{{ $bom->bom_number }}</li></ol>@endsection
@section('content')
<div class="d-flex justify-content-between mb-3">
    <div><h4>{{ $bom->bom_number }} <small class="text-muted">v{{ $bom->version }}</small> <span class="badge bg-{{ $bom->status->badgeClass() }}">{{ $bom->status->label() }}</span></h4><div>{{ $bom->product->name }} - {{ $bom->productVariant->color?->name }} / {{ $bom->productVariant->size?->name }}</div></div>
    <div><a href="{{ route('boms.edit', $bom) }}" class="btn btn-outline-primary">تعديل / إصدار جديد</a>@if ($bom->status === \App\Enums\BomStatus::DRAFT)<form method="POST" class="d-inline" action="{{ route('boms.activate', $bom) }}">@csrf<button class="btn btn-success">تفعيل</button></form>@endif</div>
</div>
<div class="row g-3">
    <div class="col-lg-8"><div class="card"><div class="card-header bg-white">الخامات لكل {{ number_format($bom->basis_quantity, 3) }}</div><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>الكود</th><th>الخامة</th><th>الكمية</th><th>توزيع العمليات</th><th>الوحدة</th></tr></thead><tbody>@foreach ($bom->items as $item)<tr><td>{{ $item->material_code }}</td><td>{{ $item->material_name }}</td><td>{{ number_format($item->quantity, 3) }}</td><td>@foreach ($item->operationAllocations as $allocation)<span class="badge bg-light text-dark border me-1">{{ $allocation->bomOperation->operation_name }}: {{ number_format($allocation->quantity, 3) }}</span>@endforeach</td><td>{{ \App\Enums\MaterialUnit::tryFrom($item->unit)?->label() ?? $item->unit }}</td></tr>@endforeach</tbody></table></div></div></div>
    <div class="col-lg-4"><div class="card"><div class="card-header bg-white">العمليات وتكلفتها لكل عباية</div><ol class="list-group list-group-numbered list-group-flush">@foreach ($bom->operations as $operation)<li class="list-group-item d-flex justify-content-between"><div>{{ $operation->operation_name }}@if ($operation->notes)<small class="d-block text-muted">{{ $operation->notes }}</small>@endif</div><strong>{{ number_format($operation->unit_cost, 2) }} ج.م</strong></li>@endforeach</ol><div class="card-footer d-flex justify-content-between"><span>إجمالي التشغيل</span><strong>{{ number_format($bom->operations->sum('unit_cost'), 2) }} ج.م / عباية</strong></div></div></div>
</div>
@endsection
