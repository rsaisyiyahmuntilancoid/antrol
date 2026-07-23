<!-- TAB 2: OFFICIAL BPJS DASHBOARD REPORT -->
<div id="tab-bpjs-content" class="hidden space-y-8 animate-in fade-in duration-300">
    <div class="glass p-8 rounded-[32px] min-h-[400px]">
        <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-6">
            <i class="fas fa-globe text-teal-600"></i> Rapor Resmi Kinerja Waktu Tunggu Server BPJS Kesehatan
        </h2>
        <p class="text-slate-500 dark:text-slate-400 text-sm max-w-2xl mb-8">
            Data di bawah ini ditarik secara real-time langsung dari web service BPJS Kesehatan (Dashboard Waktu
            Antrean). Ini adalah nilai agregat resmi yang diakui oleh BPJS.
        </p>

        <div id="bpjs-report-loading" class="hidden flex-col items-center justify-center py-20 space-y-4">
            <div class="w-12 h-12 border-4 border-teal-600/20 border-t-teal-600 rounded-full animate-spin"></div>
            <p class="text-sm font-semibold text-slate-500">Menghubungi server BPJS Kesehatan...</p>
        </div>

        <div id="bpjs-report-empty" class="flex flex-col items-center justify-center py-20 text-center space-y-4">
            <div
                class="w-24 h-24 bg-teal-50 dark:bg-teal-500/10 rounded-[35px] flex items-center justify-center text-teal-600 text-4xl shadow-xl shadow-teal-500/5">
                <i class="fas fa-network-wired"></i>
            </div>
            <div>
                <h4 class="text-xl font-bold">Menunggu Permintaan Data</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm">
                    Gunakan tombol filter di atas ("Rapor Harian" atau "Rapor Bulanan") untuk memicu penarikan data
                    dari API BPJS.
                </p>
            </div>
        </div>

        <div id="bpjs-report-container" class="hidden space-y-8">
            <!-- Aggregate Stats Cards (Premium HSL Style) -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                <!-- Card 1: Total Queue -->
                <div
                    class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-teal-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <span
                                class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Total
                                Antrean Resmi</span>
                            <p class="text-3xl font-extrabold text-teal-600 dark:text-teal-400 mt-2 leading-none"
                                id="bpjs-stat-queues">0</p>
                        </div>
                        <span
                            class="w-8 h-8 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xs">
                            <i class="fas fa-users"></i>
                        </span>
                    </div>
                    <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Terverifikasi pada
                        server BPJS</p>
                </div>

                <!-- Card 2: Tunggu Poli -->
                <div
                    class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-blue-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <span
                                class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata
                                Tunggu Poli</span>
                            <p class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-2 leading-none"
                                id="bpjs-stat-wait-poli">0.0m</p>
                        </div>
                        <span
                            class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs">
                            <i class="fas fa-clock"></i>
                        </span>
                    </div>
                    <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Task 3: Menunggu
                        panggilan dokter</p>
                </div>

                <!-- Card 3: Layan Poli -->
                <div
                    class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-emerald-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <span
                                class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata
                                Layan Poli</span>
                            <p class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2 leading-none"
                                id="bpjs-stat-poli">0.0m</p>
                        </div>
                        <span
                            class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">
                            <i class="fas fa-stethoscope"></i>
                        </span>
                    </div>
                    <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Task 4: Pemeriksaan
                        dokter</p>
                </div>

                <!-- Card 4: Layan Farmasi -->
                <div
                    class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-purple-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <span
                                class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata
                                Layan Farmasi</span>
                            <p class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-2 leading-none"
                                id="bpjs-stat-farmasi">0.0m</p>
                        </div>
                        <span
                            class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs">
                            <i class="fas fa-capsules"></i>
                        </span>
                    </div>
                    <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Task 6: Penyiapan
                        obat resep</p>
                </div>

                <!-- Card 5: Rerata Total Waktu -->
                <div
                    class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-amber-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                    <div class="flex justify-between items-start">
                        <div>
                            <span
                                class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata
                                Total Pelayanan</span>
                            <p class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-2 leading-none"
                                id="bpjs-stat-total-time">0.0m</p>
                        </div>
                        <span
                            class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs">
                            <i class="fas fa-history"></i>
                        </span>
                    </div>
                    <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Total akumulasi
                        Task 1 s/d 6</p>
                </div>
            </div>

            <!-- Quick Insights Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="bpjs-insights-row">
                <!-- Insight 1: Highest Queue -->
                <div
                    class="bg-teal-500/5 border border-teal-500/10 dark:border-teal-500/20 p-4 rounded-2xl flex items-center gap-4">
                    <span
                        class="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-fire"></i>
                    </span>
                    <div>
                        <span
                            class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Poliklinik
                            Terpadat</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200 mt-1"
                            id="bpjs-insight-busiest">-</p>
                    </div>
                </div>
                <!-- Insight 2: Longest Wait -->
                <div
                    class="bg-amber-500/5 border border-amber-500/10 dark:border-amber-500/20 p-4 rounded-2xl flex items-center gap-4">
                    <span
                        class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-hourglass-half"></i>
                    </span>
                    <div>
                        <span
                            class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Antrean
                            Poli Terlama</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200 mt-1"
                            id="bpjs-insight-longest-wait">-</p>
                    </div>
                </div>
                <!-- Insight 3: Longest Layan -->
                <div
                    class="bg-rose-500/5 border border-rose-500/10 dark:border-rose-500/20 p-4 rounded-2xl flex items-center gap-4">
                    <span
                        class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm shrink-0">
                        <i class="fas fa-user-md"></i>
                    </span>
                    <div>
                        <span
                            class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Pelayanan
                            Dokter Terlama</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-200 mt-1"
                            id="bpjs-insight-longest-layan">-</p>
                    </div>
                </div>
            </div>

            <!-- BPJS Charts Row -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="bpjs-charts-row">
                <!-- Chart 1: Queue Volume per Poliklinik -->
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 p-6 rounded-3xl shadow-sm">
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-4">Volume Antrean per
                        Poliklinik (Top 7)</h4>
                    <div class="h-64 relative">
                        <canvas id="bpjs-chart-volume"></canvas>
                    </div>
                </div>
                <!-- Chart 2: Wait vs Service Times -->
                <div
                    class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 p-6 rounded-3xl shadow-sm">
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-4">Perbandingan Waktu Tunggu
                        & Layan Dokter (Top 7 Menit)</h4>
                    <div class="h-64 relative">
                        <canvas id="bpjs-chart-times"></canvas>
                    </div>
                </div>
            </div>

            <!-- Detailed Table -->
            <div
                class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                <div
                    class="px-8 py-5 bg-slate-50/50 dark:bg-slate-800/10 border-b border-slate-200/40 dark:border-slate-800/40 flex flex-col md:flex-row justify-between items-center gap-4">
                    <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Rapor Detail Waktu per
                        Poliklinik (Kalkulasi Server BPJS)</h4>
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                        <!-- Search input -->
                        <div class="relative w-full sm:w-64">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                <i class="fas fa-search text-xs"></i>
                            </span>
                            <input type="text" id="bpjs-search-input" onkeyup="handleBpjsSearch()"
                                placeholder="Cari poliklinik atau tanggal..."
                                class="w-full pl-9 pr-4 py-2 text-xs font-semibold rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-colors">
                        </div>
                        <!-- Page size select -->
                        <select id="bpjs-page-size" onchange="changeBpjsPageSize(this.value)"
                            class="px-3 py-2 text-xs font-bold rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-colors">
                            <option value="10">10 Baris</option>
                            <option value="25" selected>25 Baris</option>
                            <option value="50">50 Baris</option>
                            <option value="100">100 Baris</option>
                        </select>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full text-left border-collapse text-sm" id="bpjs-report-table">
                        <thead>
                            <tr
                                class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40">
                                <th class="px-6 py-4">Tanggal</th>
                                <th class="px-6 py-4">Poliklinik BPJS</th>
                                <th class="px-6 py-4 text-center">Jumlah Antrean</th>
                                <th class="px-6 py-4 text-center">Tunggu Admisi</th>
                                <th class="px-6 py-4 text-center">Layan Admisi</th>
                                <th class="px-6 py-4 text-center">Tunggu Poli</th>
                                <th class="px-6 py-4 text-center">Layan Poli</th>
                                <th class="px-6 py-4 text-center">Tunggu Farmasi</th>
                                <th class="px-6 py-4 text-center">Layan Farmasi</th>
                                <th class="px-6 py-4 text-center text-teal-600 dark:text-teal-400">Total Waktu</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold">
                            <!-- Ajax populated -->
                        </tbody>
                    </table>
                </div>

                <!-- BPJS Pagination Controls -->
                <div class="px-8 py-5 border-t border-slate-200/50 dark:border-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/10"
                    id="bpjs-pagination-controls-bar">
                    <div class="text-xs font-semibold text-slate-500">
                        Menampilkan <span id="bpjs-pagination-info-start"
                            class="font-bold text-slate-700 dark:text-slate-300">0</span> - <span
                            id="bpjs-pagination-info-end"
                            class="font-bold text-slate-700 dark:text-slate-300">0</span> dari <span
                            id="bpjs-pagination-info-total"
                            class="font-bold text-slate-700 dark:text-slate-300">0</span> baris
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button onclick="changeBpjsPage(1)" id="btn-bpjs-page-first"
                            class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-angle-double-left"></i>
                        </button>
                        <button onclick="changeBpjsPage(bpjsCurrentPage - 1)" id="btn-bpjs-page-prev"
                            class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-angle-left"></i> Prev
                        </button>
                        <div id="bpjs-pagination-pages" class="flex items-center gap-1.5">
                            <!-- JS renders page buttons here -->
                        </div>
                        <button onclick="changeBpjsPage(bpjsCurrentPage + 1)" id="btn-bpjs-page-next"
                            class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            Next <i class="fas fa-angle-right"></i>
                        </button>
                        <button onclick="changeBpjsPage(bpjsTotalPages)" id="btn-bpjs-page-last"
                            class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                            <i class="fas fa-angle-double-right"></i>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- COMPARE: SIMRS Internal vs BPJS Resmi -->
        <div id="bpjs-compare-section"
            class="hidden mt-8 bg-slate-50/40 dark:bg-slate-800/10 border border-slate-200/50 dark:border-slate-800 rounded-3xl p-6">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-2">
                <i class="fas fa-balance-scale text-blue-600"></i> Perbandingan: Kalkulasi SIMRS vs Angka Resmi BPJS
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">
                Median dari DB SIMRS (data riil tanpa fallback) dibandingkan dengan rerata agregat resmi dari server
                BPJS pada tanggal/bulan yang sama.
                Selisih menunjukkan seberapa jauh data yang dikirim ke BPJS berbeda dari kondisi aktual di lapangan.
            </p>
            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
                        <tr
                            class="bg-white dark:bg-slate-900 border-b border-slate-200/50 dark:border-slate-800 text-slate-400 text-xs font-bold uppercase tracking-wider">
                            <th class="px-4 py-3 text-left">Dimensi Waktu</th>
                            <th class="px-4 py-3 text-center text-blue-600 dark:text-blue-400">SIMRS (Median)</th>
                            <th class="px-4 py-3 text-center text-teal-600 dark:text-teal-400">BPJS Resmi
                                (Rata-rata)</th>
                            <th class="px-4 py-3 text-center">Selisih</th>
                            <th class="px-4 py-3 text-center">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody id="compare-table-body"
                        class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold">
                        <tr>
                            <td colspan="5" class="text-center text-slate-400 text-xs py-8">
                                Ambil rapor BPJS terlebih dahulu untuk menampilkan perbandingan ini.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <p class="text-[10px] text-slate-400 mt-4">
                ⚠ BPJS menggunakan rata-rata (mean), SIMRS menggunakan median. Selisih kecil (~5m) bisa karena
                perbedaan metode statistik.
                Selisih besar (>15 menit) perlu diinvestigasi — kemungkinan besar akibat fallback random di
                pengiriman Task ID.
            </p>
        </div>
    </div>
</div>
