<!-- Anomaly Alert Banner -->
@if ($analytics['anomalies']['total_anomalies'] > 0)
<div
    class="bg-rose-500/10 dark:bg-rose-500/5 border border-rose-500/20 rounded-3xl p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
    <div class="flex items-start gap-4">
        <div
            class="w-12 h-12 bg-rose-500 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-rose-500/20 shrink-0">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold text-rose-600 dark:text-rose-400">Terdeteksi {{
                $analytics['anomalies']['total_anomalies'] }} Anomali Data Kunjungan</h4>
            <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                Ditemukan data transaksi antrean yang tidak wajar atau melanggar aturan sinkronisasi Task ID
                BPJS.
            </p>
            <div
                class="flex flex-wrap gap-x-4 gap-y-1.5 mt-3 text-xs font-semibold text-slate-600 dark:text-slate-400">
                <span onclick="filterPatientByAnomaly('timestamp_buatan')"
                    class="flex items-center cursor-pointer hover:text-rose-600 transition-colors"><span
                        class="w-2.5 h-2.5 bg-rose-500 rounded-full mr-2"></span> {{
                    count($analytics['anomalies']['timestamp_buatan']) }} Timestamp Buatan</span>
                <span onclick="filterPatientByAnomaly('durasi_negatif')"
                    class="flex items-center cursor-pointer hover:text-amber-600 transition-colors"><span
                        class="w-2.5 h-2.5 bg-amber-500 rounded-full mr-2"></span> {{
                    count($analytics['anomalies']['durasi_negatif']) }} Durasi Negatif</span>
                <span onclick="filterPatientByAnomaly('farmasi_10_menit')"
                    class="flex items-center cursor-pointer hover:text-teal-600 transition-colors"><span
                        class="w-2.5 h-2.5 bg-teal-500 rounded-full mr-2"></span> {{
                    count($analytics['anomalies']['farmasi_10_menit']) }} Farmasi Tepat 10 Menit</span>
                <span onclick="filterPatientByAnomaly('belum_terkirim')"
                    class="flex items-center cursor-pointer hover:text-slate-600 dark:hover:text-white transition-colors"><span
                        class="w-2.5 h-2.5 bg-slate-500 rounded-full mr-2"></span> {{
                    count($analytics['anomalies']['belum_terkirim']) }} Belum Terkirim</span>
            </div>
        </div>
    </div>
    <button onclick="scrollToPatientTableWithAnomalies()"
        class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-3 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all shadow-md shadow-rose-500/20 shrink-0">
        Lihat Detail Anomali &rarr;
    </button>
</div>
@endif
