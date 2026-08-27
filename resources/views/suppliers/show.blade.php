@extends('layouts.dashboard')
@section('title', $supplier->name . ' - المورد')
@section('content')
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1 class="h3 mb-1">{{ $supplier->name }} <span
                    class="badge bg-{{ $supplier->status === 'active' ? 'success' : 'secondary' }}">{{ \App\Support\ArabicLabels::status($supplier->status) }}</span>
            </h1>
            <p class="text-muted mb-0">{{ $supplier->code }}</p>
        </div>
       <div class="d-flex gap-2">
    <a href="{{ route('suppliers.ledger', $supplier) }}" class="btn btn-outline-primary">
        <i class="bi bi-journal-text me-1"></i>
        كشف حساب المورد
    </a>

    <a href="{{ route('suppliers.edit', $supplier) }}" class="btn btn-primary">
        <i class="bi bi-pencil me-1"></i>
        تعديل
    </a>

    <form method="POST" action="{{ route('suppliers.destroy', $supplier) }}"
        onsubmit="return confirm('هل أنت متأكد من حذف هذا السجل؟ سيتم إخفاؤه من السجلات النشطة مع الاحتفاظ بتاريخه.')">
        @csrf
        @method('DELETE')
        <button class="btn btn-outline-danger">
            حذف
        </button>
    </form>
</div>
    </div>
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">تفاصيل المورد</h5>
                </div>
                <div class="card-body row g-3">
                    <div class="col-md-6"><small class="text-muted">الهاتف</small>
                        <div>{{ $supplier->phone ?? '—' }}</div>
                    </div>
                    <div class="col-md-6"><small class="text-muted">البريد الإلكتروني</small>
                        <div>{{ $supplier->email ?? '—' }}</div>
                    </div>
                    <div class="col-md-6"><small class="text-muted">الرقم الضريبي</small>
                        <div>{{ $supplier->tax_number ?? '—' }}</div>
                    </div>
                    <div class="col-12"><small class="text-muted">العنوان</small>
                        <div>{{ $supplier->address ?? '—' }}</div>
                    </div>
                    <div class="col-12"><small class="text-muted">ملاحظات</small>
                        <div>{{ $supplier->notes ?? '—' }}</div>
                    </div>
                </div>
            </div>
            <div class="card mt-4">
                <div class="card-header bg-white">
                    <h5 class="mb-0">جهات الاتصال</h5>
                </div>
                <div class="card-body">
                    <form class="row g-2 mb-4" method="POST" action="{{ route('suppliers.contacts.store', $supplier) }}">
                        @csrf <div class="col-md-4"><input required name="name" placeholder="اسم جهة الاتصال"
                                class="form-control"></div>
                        <div class="col-md-3"><input name="job_title" placeholder="المسمى الوظيفي" class="form-control"></div>
                        <div class="col-md-2"><input name="phone" placeholder="الهاتف" class="form-control"></div>
                        <div class="col-md-2"><input type="email" name="email" placeholder="البريد الإلكتروني" class="form-control">
                        </div>
                        <div class="col-md-1 d-flex align-items-center"><input type="checkbox" name="is_primary"
                                value="1" title="رئيسي"></div>
                        <div class="col-12"><button class="btn btn-sm btn-primary">إضافة جهة اتصال</button></div>
                    </form>
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th>الاسم</th>
                                <th>المسمى الوظيفي</th>
                                <th>الهاتف</th>
                                <th>البريد الإلكتروني</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($supplier->contacts as $contact)
                                <tr>
                                    <td>{{ $contact->name }} @if ($contact->is_primary)
                                            <span class="badge bg-primary">رئيسي</span>
                                        @endif
                                    </td>
                                    <td>{{ $contact->job_title }}</td>
                                    <td>{{ $contact->phone }}</td>
                                    <td>{{ $contact->email }}</td>
                                    <td class="text-end">
                                        <form method="POST"
                                            action="{{ route('suppliers.contacts.destroy', [$supplier, $contact]) }}">@csrf
                                            @method('DELETE')<button class="btn btn-sm btn-outline-danger"><i
                                                    class="bi bi-trash"></i></button></form>
                                    </td>
                            </tr>@empty<tr>
                                    <td colspan="5" class="text-center text-muted">لا توجد جهات اتصال حتى الآن.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-white">
                    <h5 class="mb-0">الملخص المالي</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3"><small class="text-muted">إجمالي المشتريات</small>
                        <div class="fw-semibold">{{ number_format($summary['purchases'], 2) }}</div>
                    </div>
                    <div class="mb-3"><small class="text-muted">إجمالي الفواتير</small>
                        <div class="fw-semibold">{{ number_format($summary['invoiced'], 2) }}</div>
                    </div>
                    <div class="mb-3"><small class="text-muted">إجمالي المدفوع</small>
                        <div class="fw-semibold">{{ number_format($summary['paid'], 2) }}</div>
                    </div>
                    <div><small class="text-muted">المتبقي</small>
                        <div class="fw-bold text-danger">{{ number_format($summary['outstanding'], 2) }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
