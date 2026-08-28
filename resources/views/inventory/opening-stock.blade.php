@extends('layouts.dashboard')
@section('title', 'الرصيد الافتتاحي - '.config('app.name'))
@section('content')
<div class="page-header"><h1 class="h3 mb-1">الرصيد الافتتاحي</h1><p class="text-muted mb-0">سجّل الرصيد المتوفر كحركة مخزون افتتاحية.</p></div>
<form method="POST" action="{{ route('inventory.opening-stock.store') }}">@csrf
<div class="card"><div class="card-body"><x-barcode-variant-selector target="product_variant_id" /><div class="row g-3">
<div class="col-md-6"><label class="form-label">المتغير <span class="text-danger">*</span></label><select id="product_variant_id" name="product_variant_id" class="form-select @error('product_variant_id') is-invalid @enderror" required><option value="">اختر المتغير</option>@foreach($variants as $variant)<option value="{{ $variant->id }}" @selected(old('product_variant_id') == $variant->id)>{{ $variant->sku }} - {{ $variant->product?->name }} / {{ $variant->color?->name }} / {{ $variant->size?->name }}</option>@endforeach</select>@error('product_variant_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-3"><label class="form-label">الكمية <span class="text-danger">*</span></label><input type="number" min="0.001" step="0.001" name="quantity" value="{{ old('quantity') }}" class="form-control @error('quantity') is-invalid @enderror" required>@error('quantity')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-md-3"><label class="form-label">التاريخ <span class="text-danger">*</span></label><input type="date" name="movement_date" value="{{ old('movement_date', now()->format('Y-m-d')) }}" class="form-control @error('movement_date') is-invalid @enderror" required>@error('movement_date')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
<div class="col-12"><label class="form-label">ملاحظات</label><textarea name="notes" rows="3" class="form-control">{{ old('notes') }}</textarea></div>
</div></div></div><div class="mt-3"><button class="btn btn-primary">تسجيل رصيد افتتاحي</button><a href="{{ route('inventory.index') }}" class="btn btn-outline-secondary">إلغاء</a></div></form>
@endsection
