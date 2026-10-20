<!-- Charts Grid Row -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Left Chart: Clinic Performance (2 cols width) -->
    <div class="glass p-8 rounded-[32px] lg:col-span-2 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
            <div>
                <h3 class="text-lg font-bold tracking-tight flex items-center gap-2">
                    <i class="fas fa-chart-bar text-blue-600"></i> Kinerja Waktu Layanan & Tunggu per Poliklinik (Menit)
                </h3>
                <p class="text-xs text-slate-400 mt-1">Perbandingan median durasi tunggu dan pelayanan antar poliklinik</p>
            </div>
            <!-- Scope & Mode Toggles -->
            <div class="flex flex-wrap items-center gap-2 self-start sm:self-auto">
                <!-- Scope Toggle: Semua Poli vs Top 8 -->
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200/50 dark:border-slate-700/50 text-xs font-semibold select-none">
                    <button type="button" onclick="setClinicChartScope('all')" id="btn-chart-scope-all"
                        class="px-2.5 py-1.5 rounded-lg transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400 font-bold"
                        title="Tampilkan seluruh poliklinik aktif">
                        Semua Poli
                    </button>
                    <button type="button" onclick="setClinicChartScope('top8')" id="btn-chart-scope-top8"
                        class="px-2.5 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800 dark:hover:text-white"
                        title="Tampilkan 8 poliklinik dengan kunjungan terbanyak">
                        Top 8
                    </button>
                </div>

                <!-- Mode Toggle: Berdampingan (Grouped) vs Akumulasi (Stacked) -->
                <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-xl border border-slate-200/50 dark:border-slate-700/50 text-xs font-semibold select-none">
                    <button type="button" onclick="setClinicChartMode('grouped')" id="btn-chart-mode-grouped"
                        class="px-2.5 py-1.5 rounded-lg transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400 font-bold"
                        title="Tampilkan waktu masing-masing task berdiri sendiri (nilai riil)">
                        <i class="fas fa-columns mr-1 text-[10px]"></i> Berdampingan
                    </button>
                    <button type="button" onclick="setClinicChartMode('stacked')" id="btn-chart-mode-stacked"
                        class="px-2.5 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800 dark:hover:text-white"
                        title="Tumpuk waktu task untuk melihat akumulasi total estimasi">
                        <i class="fas fa-layer-group mr-1 text-[10px]"></i> Akumulasi
                    </button>
                </div>
            </div>
        </div>
        <div class="relative min-h-[320px]">
            <canvas id="clinicChart"></canvas>
        </div>
        <p class="text-[11px] text-slate-400 mt-3 text-right font-medium italic">
            <i class="fas fa-info-circle mr-1 text-blue-500"></i> Klik batang poliklinik untuk membuka rincian modal
        </p>
    </div>

    <!-- Right Chart: Flow Completeness (1 col width) -->
    <div class="glass p-8 rounded-[32px] lg:col-span-1 shadow-sm flex flex-col">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-lg font-bold tracking-tight flex items-center gap-2">
                <i class="fas fa-chart-pie text-indigo-600"></i> Kelengkapan Flow Kunjungan
            </h3>
            <span id="status-chart-total-badge" class="px-2.5 py-1 rounded-full text-xs font-bold bg-indigo-50 dark:bg-indigo-950/50 text-indigo-600 dark:text-indigo-400 border border-indigo-200/50 dark:border-indigo-800/50">
                Total: {{ number_format($analytics['summary']['total_patients'] ?? 0) }}
            </span>
        </div>
        <div class="relative flex-grow flex items-center justify-center min-h-[220px]">
            <canvas id="statusChart"></canvas>
        </div>
        <div id="status-chart-legend" class="mt-4 grid grid-cols-2 gap-2 text-xs font-semibold text-slate-500">
            <!-- Dynamic legend items will be generated here -->
        </div>
    </div>
</div>
