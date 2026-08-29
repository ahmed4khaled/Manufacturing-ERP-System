@extends('layouts.dashboard')
@section('title','ميزان المراجعة')
@section('content')
@include('accounting._header',['title'=>'ميزان المراجعة'])
@unless($report['balanced'])<div class="alert alert-danger"><strong>خطأ محاسبي:</strong> ميزان المراجعة غير متوازن ولا يجوز اعتماده.</div>@endunless
<div class="card"><div class="table-responsive"><table class="table table-sm"><thead><tr><th>الحساب</th><th>افتتاحي مدين</th><th>افتتاحي دائن</th><th>حركة مدين</th><th>حركة دائن</th><th>ختامي مدين</th><th>ختامي دائن</th></tr></thead><tbody>@foreach($report['rows'] as $r)<tr><td>{{ $r['account']->code }} - {{ $r['account']->name }}</td>@foreach(['opening_debit','opening_credit','period_debit','period_credit','closing_debit','closing_credit'] as $c)<td>{{ number_format((float)$r[$c],2) }}</td>@endforeach</tr>@endforeach</tbody><tfoot><tr class="fw-bold"><td>الإجمالي</td>@foreach(['opening_debit','opening_credit','period_debit','period_credit','closing_debit','closing_credit'] as $c)<td>{{ number_format((float)$report['totals'][$c],2) }}</td>@endforeach</tr></tfoot></table></div></div>
@endsection
