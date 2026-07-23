<!-- Charts Grid Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Chart: Clinic Performance (2 cols width) -->
    <div class="glass p-8 rounded-[32px] lg:col-span-2 shadow-sm">
        <h3 class="text-lg font-bold tracking-tight mb-6 flex items-center gap-2">
            <i class="fas fa-chart-bar text-blue-600"></i> Kinerja Waktu Layanan & Tunggu per Poliklinik (Menit)
        </h3>
        <div class="relative min-h-[300px]">
            <canvas id="clinicChart"></canvas>
        </div>
    </div>

    <!-- Right Chart: Flow Completeness (1 col width) -->
    <div class="glass p-8 rounded-[32px] lg:col-span-1 shadow-sm flex flex-col">
        <h3 class="text-lg font-bold tracking-tight mb-6 flex items-center gap-2">
            <i class="fas fa-chart-pie text-indigo-600"></i> Kelengkapan Flow Kunjungan
        </h3>
        <div class="relative flex-grow flex items-center justify-center min-h-[220px]">
            <canvas id="statusChart"></canvas>
        </div>
        <div id="status-chart-legend" class="mt-4 grid grid-cols-2 gap-2 text-xs font-semibold text-slate-500">
            <!-- Dynamic legend items will be generated here -->
        </div>
    </div>
</div>
