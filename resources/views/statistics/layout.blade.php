@extends('layouts.dashboard')

@section('title', 'الإحصائيات')

@section('breadcrumb')
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('statistics.dashboard') }}">الإحصائيات</a></li>
        <li class="breadcrumb-item active">@yield('statistics-title')</li>
    </ol>
@endsection

@push('styles')
<style>
    .stats-heading { border-bottom: 1px solid #e2e8f0; padding-bottom: .7rem; margin: 2rem 0 1rem; }
    .stats-kpi { min-height: 116px; }
    .stats-kpi .kpi-icon { width: 38px; height: 38px; display: grid; place-items: center; border-radius: 6px; background: #eef2ff; color: #4338ca; flex: 0 0 38px; }
    .stats-kpi .kpi-value { font-size: 1.25rem; font-weight: 700; overflow-wrap: anywhere; }
    .chart-box { position: relative; height: 280px; }
    .stats-table { min-width: 720px; }
    .stats-table th, .stats-table td { white-space: nowrap; vertical-align: middle; }
    .rank-summary { border-inline-start: 3px solid #6366f1; padding-inline-start: .75rem; }
    .filter-card { position: relative; z-index: 2; }
    @media (max-width: 767.98px) {
        .chart-box { height: 240px; }
        .stats-kpi { min-height: 104px; }
    }
</style>
@endpush

@section('content')
<div class="page-header d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
        <h1 class="h3 mb-1">@yield('statistics-title')</h1>
        <p class="text-muted mb-0">@yield('statistics-description')</p>
    </div>
    <span class="badge bg-light text-dark border px-3 py-2">{{ $filters['date_from'] }} إلى {{ $filters['date_to'] }}</span>
</div>

@include('statistics.partials.filters')

@yield('statistics-content')
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
@include('statistics.partials.charts')
@endpush
