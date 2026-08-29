@extends('layouts.dashboard')
@section('title',$title)
@section('content')
@include('accounting._header',['title'=>$title])
<div class="card"><table class="table mb-0"><thead><tr><th>الكود</th><th>الحساب</th><th class="text-end">الرصيد من القيود المرحّلة</th></tr></thead><tbody>@forelse($accounts as $account)<tr><td>{{ $account->code }}</td><td>{{ $account->name }}</td><td class="text-end fw-bold">{{ number_format((float)$account->balance,2) }}</td></tr>@empty<tr><td colspan="3" class="text-center text-muted">لا توجد أرصدة.</td></tr>@endforelse</tbody></table></div>
@if($parties)<div class="card mt-4"><div class="card-header">مطابقة الدفتر التشغيلي مع الحساب الرقابي</div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>الطرف</th><th>الرصيد التشغيلي</th><th>الأستاذ العام</th><th>الفرق</th></tr></thead><tbody>@foreach($parties as $party)<tr><td>{{ $party->code }} - {{ $party->name }}</td><td>{{ number_format((float)$party->operational_balance,2) }}</td><td>{{ number_format((float)$party->general_ledger_balance,2) }}</td><td class="{{ bccomp($party->variance,'0',2)===0?'text-success':'text-danger fw-bold' }}">{{ number_format((float)$party->variance,2) }}</td></tr>@endforeach</tbody></table></div></div>@endif
@endsection
