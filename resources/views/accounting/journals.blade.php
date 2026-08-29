@extends('layouts.dashboard')

@section('title', 'القيود اليومية')

@section('content')
    @include('accounting._header', ['title' => 'القيود اليومية'])

    @can('accounting.post')
        <div class="card mb-4">
            <div class="card-header">قيد يدوي متوازن</div>
            <div class="card-body">
                <form method="POST" action="{{ route('accounting.journals.store') }}">
                    @csrf

                    <div class="row g-2 mb-3">
                        <div class="col-md-3">
                            <input type="date" name="entry_date" value="{{ date('Y-m-d') }}" class="form-control" required>
                        </div>
                        <div class="col-md-9">
                            <input name="description" class="form-control" placeholder="البيان" required>
                        </div>
                    </div>

                    @foreach ([0, 1] as $i)
                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <select name="lines[{{ $i }}][account_id]" class="form-select" required>
                                    <option value="">الحساب</option>
                                    @foreach ($accounts as $account)
                                        <option value="{{ $account->id }}">
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" min="0" name="lines[{{ $i }}][debit]" class="form-control" placeholder="مدين">
                            </div>
                            <div class="col-md-3">
                                <input type="number" step="0.01" min="0" name="lines[{{ $i }}][credit]" class="form-control" placeholder="دائن">
                            </div>
                        </div>
                    @endforeach

                    <button class="btn btn-primary mt-2">ترحيل القيد</button>
                </form>
            </div>
        </div>
    @endcan

    <div class="card">
        <div class="card-body">
            <form class="row g-2 mb-3">
                <div class="col">
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
                </div>
                <div class="col">
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-primary">تصفية</button>
                </div>
            </form>

            @foreach ($entries as $entry)
                <div class="border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between">
                        <strong>{{ $entry->entry_number }} — {{ $entry->description }}</strong>
                        <span>
                            {{ $entry->entry_date->format('Y-m-d') }}
                            <span class="badge bg-success">مرحّل</span>
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm mt-2 mb-0">
                            <tbody>
                                @foreach ($entry->lines as $line)
                                    <tr>
                                        <td>{{ $line->account->code }} - {{ $line->account->name }}</td>
                                        <td class="text-end">{{ number_format($line->debit, 2) }}</td>
                                        <td class="text-end">{{ number_format($line->credit, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    @can('accounting.reverse')
                        @if (! $entry->reversed_by_id)
                            <form method="POST" action="{{ route('accounting.journals.reverse', $entry) }}" class="mt-2">
                                @csrf
                                <button class="btn btn-sm btn-outline-danger">إنشاء قيد عكس</button>
                            </form>
                        @endif
                    @endcan
                </div>
            @endforeach

            {{ $entries->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endsection
