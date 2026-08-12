@extends('layouts.dashboard')
@section('title','Purchase Orders')
@section('content')
<div class="page-header"><h1 class="h3 mb-1">Purchase Orders</h1><p class="text-muted mb-0">Orders placed with suppliers</p></div>
<div class="card"><div class="card-body"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Number</th><th>Supplier</th><th>Order Date</th><th>Expected Delivery</th><th>Status</th><th class="text-end">Total</th></tr></thead><tbody>@forelse($orders as $order)<tr><td><a href="{{ route('purchase-orders.show',$order) }}" class="fw-semibold text-decoration-none">{{ $order->purchase_order_number }}</a></td><td>{{ $order->supplier->name }}</td><td>{{ $order->order_date->format('Y-m-d') }}</td><td>{{ $order->expected_delivery_date?->format('Y-m-d') ?? '—' }}</td><td><span class="badge bg-secondary">{{ str_replace('_',' ',ucfirst($order->status)) }}</span></td><td class="text-end">{{ number_format($order->total,2) }}</td></tr>@empty<tr><td colspan="6" class="text-center text-muted py-5">No purchase orders yet.</td></tr>@endforelse</tbody></table></div>{{ $orders->links('pagination::bootstrap-5') }}</div></div>
@endsection
