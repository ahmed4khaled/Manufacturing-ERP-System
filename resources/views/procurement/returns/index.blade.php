@extends('layouts.dashboard')

@section('title', 'Purchase Returns')

@section('content')
    <div class="page-header d-flex justify-content-between align-items-start mb-4">
        <div>
            <h1 class="h3 mb-1">Purchase Returns</h1>
            <p class="text-muted mb-0">Goods returned to suppliers</p>
        </div>

        <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary">
            Create Purchase Return
        </a>
    </div>

    <div class="card">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-1">Return Register</h5>
                <small class="text-muted">
                    Track material returns and their stock posting status
                </small>
            </div>

            <span class="badge bg-light text-dark">
                {{ $returns->total() }} Returns
            </span>
        </div>

        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Return</th>
                            <th>Supplier</th>
                            <th>Goods Receipt</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th class="text-end pe-4">Actions</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($returns as $return)
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold">
                                        {{ $return->return_number }}
                                    </div>

                                    @if ($return->reason)
                                        <small class="text-muted">
                                            {{ \Illuminate\Support\Str::limit($return->reason, 35) }}
                                        </small>
                                    @endif
                                </td>

                                <td>
                                    <div class="fw-semibold">
                                        {{ $return->supplier->name }}
                                    </div>
                                </td>

                                <td>
                                    @if ($return->goodsReceipt)
                                        <a href="{{ route('goods-receipts.show', $return->goodsReceipt) }}"
                                           class="text-decoration-none fw-semibold">
                                            {{ $return->goodsReceipt->receipt_number }}
                                        </a>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>

                                <td>
                                    {{ $return->return_date->format('Y-m-d') }}
                                </td>

                                <td class="fw-semibold">
                                    {{ number_format((float) $return->items->sum(fn ($item) => $item->quantity * $item->unit_cost), 2) }}
                                </td>

                                <td>
                                    @if ($return->status === 'confirmed')
                                        <span class="badge bg-success">
                                            Confirmed
                                        </span>
                                    @else
                                        <span class="badge bg-warning text-dark">
                                            Draft
                                        </span>
                                    @endif
                                </td>

                                <td class="text-end pe-4">
                                    <div class="d-flex justify-content-end gap-2">

                                        <a href="{{ route('purchase-returns.show', $return) }}"
                                           class="btn btn-sm btn-outline-secondary">
                                            View
                                        </a>

                                        @if ($return->status === 'draft')
                                            <form method="POST"
                                                  action="{{ route('purchase-returns.confirm', $return) }}">
                                                @csrf

                                                <button type="submit"
                                                        class="btn btn-sm btn-success"
                                                        onclick="return confirm('Confirm this purchase return and deduct the returned quantity from material inventory?')">
                                                    Confirm
                                                </button>
                                            </form>
                                        @endif

                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted mb-2">
                                        No purchase returns yet.
                                    </div>

                                    <a href="{{ route('purchase-returns.create') }}"
                                       class="btn btn-sm btn-primary">
                                        Create First Purchase Return
                                    </a>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if ($returns->hasPages())
            <div class="card-footer bg-white">
                {{ $returns->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
@endsection