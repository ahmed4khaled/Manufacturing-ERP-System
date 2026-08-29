@extends('layouts.dashboard')
@section('title','دليل الحسابات')
@section('content')
@include('accounting._header',['title'=>'دليل الحسابات'])
@can('accounting.manage_accounts')
<div class="card mb-4"><div class="card-header">حساب جديد</div><div class="card-body"><form method="POST" action="{{ route('accounting.accounts.store') }}" class="row g-2">@csrf
<div class="col-md-2"><input class="form-control" name="code" placeholder="الكود" required></div><div class="col-md-3"><input class="form-control" name="name" placeholder="الاسم" required></div>
<div class="col-md-2"><select class="form-select" name="type" required>@foreach(['asset'=>'أصل','liability'=>'التزام','equity'=>'حقوق ملكية','revenue'=>'إيراد','expense'=>'مصروف'] as $v=>$l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></div>
<div class="col-md-3"><select class="form-select" name="parent_id"><option value="">بدون أب</option>@foreach($accounts as $a)<option value="{{ $a->id }}">{{ $a->code }} - {{ $a->name }}</option>@endforeach</select></div>
<div class="col-md-1 form-check pt-2"><input class="form-check-input" type="checkbox" name="allow_posting" value="1" id="posting"><label for="posting">ترحيل</label></div><div class="col-md-1"><button class="btn btn-primary">حفظ</button></div><input type="hidden" name="is_active" value="1"></form></div></div>
@endcan
<div class="card"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>الكود</th><th>الحساب</th><th>النوع</th><th>الأب</th><th>الترحيل</th><th>الحالة</th></tr></thead><tbody>@foreach($accounts as $a)<tr><td class="fw-bold">{{ $a->code }}</td><td>{{ $a->name }}</td><td>{{ $a->type }}</td><td>{{ $a->parent?->name ?? '-' }}</td><td>{{ $a->allow_posting?'نعم':'لا' }}</td><td>{{ $a->is_active?'نشط':'موقوف' }}</td></tr>@endforeach</tbody></table></div></div>
@endsection
