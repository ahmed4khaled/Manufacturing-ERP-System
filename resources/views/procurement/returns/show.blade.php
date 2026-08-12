@extends('layouts.dashboard')
@section('title',$purchaseReturn->return_number)
@section('content')
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1 class="h3 mb-1">{{ $purchaseReturn->return_number }}</h1>
        <p class="text-muted mb-0">{{ $purchaseReturn->supplier->name }} · {{ $purchaseReturn->return_date->format('Y-m-d') }}</p>
    </div>
    @if($purchaseReturn->status === 'draft')
        <form method="POST" action="{{ route('purchase-returns.confirm', $purchaseReturn) }}">@csrf
            <button class="btn btn-success">Confirm & Post Stock</button>
        </form>
    @endif
</div>

<div class="card mt-3">
    <div class="card-body">
        <div class="mb-3"><strong>Reason:</strong> {{ $purchaseReturn->reason }}</div>
        <div class="table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Variant</th>
                        <th>Qty</th>
                        <th>Unit Cost</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($purchaseReturn->items as $item)
                        <tr>
                            <td>{{ $item->productVariant->sku }}</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->unit_cost, 2) }}</td>
                            <td>{{ $item->reason ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
