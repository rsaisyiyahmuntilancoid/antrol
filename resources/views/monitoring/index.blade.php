@extends('layouts.main')

@section('title', 'Flow Analytics & Monitoring')

@section('content')
<div class="max-w-[1600px] mx-auto px-6 py-12 relative overflow-hidden">
    @include('monitoring.partials._header')

    <!-- TAB 1: INTERNAL SIMRS DASHBOARD -->
    <div id="tab-simrs-content" class="space-y-8 animate-in fade-in duration-300">
        @include('monitoring.partials._kpi-cards')
        @include('monitoring.partials._anomaly-banner')
        @include('monitoring.partials._flow-timeline')
        @include('monitoring.partials._charts')
        @include('monitoring.partials._summary-tables')
        @include('monitoring.partials._patient-registry')
    </div>

    <!-- TAB 2: OFFICIAL BPJS DASHBOARD REPORT -->
    @include('monitoring.partials._tab-bpjs')
</div>

@include('monitoring.partials._slide-panel')
@include('monitoring.partials._clinic-modal')
@endsection

@include('monitoring.partials._styles')

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Global Dashboard data parsed from Laravel
    const analytics = @json($analytics);
    const simrsGlobalStats = @json($analytics['global_stats']);
    const dateFrom = '{{ $dateFrom }}';
    const dateTo = '{{ $dateTo }}';
</script>
<script src="{{ asset('js/monitoring/core.js') }}"></script>
<script src="{{ asset('js/monitoring/charts.js') }}"></script>
<script src="{{ asset('js/monitoring/patient-table.js') }}"></script>
<script src="{{ asset('js/monitoring/sync.js') }}"></script>
<script src="{{ asset('js/monitoring/detail-panel.js') }}"></script>
<script src="{{ asset('js/monitoring/clinic-modal.js') }}"></script>
<script src="{{ asset('js/monitoring/bpjs-report.js') }}"></script>
@endpush
