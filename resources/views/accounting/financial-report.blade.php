@extends('layouts.dashboard')
@section('title',$title)
@section('content')
@include('accounting._header',['title'=>$title])
@if($kind==='profit-loss')<div class="row g-3">@foreach(['netSales'=>'صافي المبيعات','cogs'=>'تكلفة المبيعات','grossProfit'=>'مجمل الربح','operatingExpenses'=>'مصروفات التشغيل','netProfit'=>'صافي الربح'] as $key=>$label)<div class="col-md"><div class="card h-100"><div class="card-body"><small class="text-muted">{{ $label }}</small><div class="h4 mt-2">{{ number_format((float)$report[$key],2) }}</div></div></div></div>@endforeach</div>
@else @unless($report['balanced'])<div class="alert alert-danger">الميزانية غير متوازنة ولا يجوز اعتمادها.</div>@endunless<div class="row g-3">@foreach(['assetTotal'=>'الأصول','liabilityTotal'=>'الالتزامات','equityTotal'=>'حقوق الملكية (شاملة نتيجة الفترة)'] as $key=>$label)<div class="col-md-4"><div class="card"><div class="card-body"><small>{{ $label }}</small><div class="h3">{{ number_format((float)$report[$key],2) }}</div></div></div></div>@endforeach</div>@endif
@endsection
