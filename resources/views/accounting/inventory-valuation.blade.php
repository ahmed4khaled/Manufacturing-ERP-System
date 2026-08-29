@extends('layouts.dashboard')
@section('title','تقييم المخزون')
@section('content')
@include('accounting._header',['title'=>'تقييم المخزون FIFO'])
@foreach([['خامات',$materials,'material'],['منتجات تامة',$products,'productVariant']] as [$label,$layers,$relation])<div class="card mb-4"><div class="card-header">{{ $label }}</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>الصنف</th><th>تاريخ الطبقة</th><th>الكمية المتبقية</th><th>تكلفة الوحدة</th><th>القيمة</th></tr></thead><tbody>@foreach($layers as $layer)<tr><td>{{ $layer->{$relation}?->name ?? $layer->{$relation}?->sku }}</td><td>{{ $layer->layer_date }}</td><td>{{ $layer->remaining_quantity }}</td><td>{{ number_format((float)$layer->unit_cost,4) }}</td><td>{{ number_format((float)$layer->remaining_quantity*(float)$layer->unit_cost,2) }}</td></tr>@endforeach</tbody></table></div></div>@endforeach
@endsection
