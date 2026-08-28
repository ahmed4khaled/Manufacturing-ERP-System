@extends('layouts.dashboard')

@section('title', 'طباعة ملصقات الإنتاج')

@section('content')
    <div class="page-header"><h1 class="h3 mb-1">طباعة ملصقات مخرجات الإنتاج</h1><p class="text-muted mb-0">حدد المخرجات والكميات؛ الطباعة لا تغيّر المخزون أو أمر الإنتاج.</p></div>
    <form method="POST" action="{{ route('production-labels.print') }}" target="_blank">
        @csrf
        @error('outputs')<div class="alert alert-danger">{{ $message }}</div>@enderror
        <div class="card"><div class="table-responsive"><table class="table table-hover align-middle mb-0">
            <thead><tr><th></th><th>أمر الإنتاج</th><th>المتغير</th><th>الباركود</th><th>تاريخ المخرج</th><th class="text-end">كمية المخرج</th><th style="width:160px">ملصقات</th></tr></thead>
            <tbody>@forelse($outputs as $output) @php($maximum = (int) floor((float) $output->quantity))
                <tr><td><input class="form-check-input output-check" type="checkbox" data-output="{{ $output->id }}" @disabled($maximum < 1 || blank($output->productVariant?->barcode))></td>
                    <td><a href="{{ route('production-orders.show', $output->productionOrder) }}">{{ $output->productionOrder?->order_number }}</a></td>
                    <td>{{ $output->productVariant?->product?->name }}<small class="d-block text-muted">{{ $output->productVariant?->color?->name }} / {{ $output->productVariant?->size?->name }} — {{ $output->productVariant?->sku }}</small></td>
                    <td><code>{{ $output->productVariant?->barcode ?? 'غير متاح' }}</code></td><td>{{ $output->output_date?->format('Y-m-d') }}</td>
                    <td class="text-end">{{ number_format($output->quantity, 3) }}</td><td><input class="form-control output-quantity" data-output="{{ $output->id }}" type="number" min="1" max="{{ $maximum }}" value="{{ $maximum }}" disabled></td></tr>
            @empty<tr><td colspan="7" class="text-center text-muted py-5">لا توجد مخرجات لأوامر إنتاج مكتملة.</td></tr>@endforelse</tbody>
        </table></div><div class="card-footer d-flex gap-2"><button name="mode" value="preview" class="btn btn-outline-primary">معاينة</button><button name="mode" value="print" class="btn btn-primary">طباعة الملصقات</button></div></div>
    </form>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.output-check').forEach(checkbox => checkbox.addEventListener('change', () => {
    const quantity = document.querySelector(`.output-quantity[data-output="${checkbox.dataset.output}"]`);
    quantity.disabled = !checkbox.checked;
    quantity.name = checkbox.checked ? `outputs[${checkbox.dataset.output}]` : '';
}));
</script>
@endpush
