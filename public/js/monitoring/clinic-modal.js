/**
 * Clinic Detail Modal Module
 * Fetches and displays detailed poliklinik-level statistics, min/median/max breakdowns,
 * and negative duration anomaly warnings.
 */

function showClinicDetail(nmPoli, dateFrom, dateTo) {
    const modal = document.getElementById('clinic-detail-modal');
    const modalContent = document.getElementById('clinic-detail-content');
    const modalTitle = document.getElementById('clinic-detail-title');

    if (!modal || !modalContent || !modalTitle) return;

    modalTitle.textContent = nmPoli;
    modalContent.innerHTML = `<div class="flex items-center justify-center py-12 text-slate-400"><i class="fas fa-circle-notch animate-spin mr-2"></i> Memuat data klinik...</div>`;
    modal.classList.remove('hidden');
    modal.classList.add('flex');

    fetch(`/api/monitoring/clinic/${encodeURIComponent(nmPoli)}?date_from=${dateFrom}&date_to=${dateTo}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                modalContent.innerHTML = `<p class="text-rose-500 text-sm font-semibold py-6 text-center">${res.message}</p>`;
                return;
            }
            const s = res.stats;
            const fmt = (stat, key) => stat[key] !== null
                ? `<span class="${stat[key] < 0 ? 'text-rose-500 font-bold' : ''}">${stat[key]}m</span>`
                : '<span class="text-slate-300">—</span>';

            let negHTML = '';
            if (res.negative_durations && res.negative_durations.length > 0) {
                negHTML = `
                <div class="mt-4 bg-rose-50 dark:bg-rose-500/10 border border-rose-200 dark:border-rose-500/20 rounded-2xl p-4">
                    <h5 class="text-xs font-bold uppercase text-rose-600 dark:text-rose-400 mb-3 flex items-center gap-2">
                        <i class="fas fa-exclamation-circle"></i> Durasi Negatif (${res.negative_durations.length} kasus)
                    </h5>
                    <div class="space-y-2 max-h-32 overflow-y-auto text-xs">
                        ${res.negative_durations.map(d => `
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-slate-700 dark:text-slate-300">${d.nm_pasien}</span>
                                <div class="flex gap-2 items-center">
                                    <span class="text-slate-400">${d.metric.replace(/_/g,' ')}</span>
                                    <span class="text-rose-600 font-bold">${d.value}m</span>
                                </div>
                            </div>`).join('')}
                    </div>
                    <p class="text-[10px] text-rose-500 mt-3 font-semibold">⚠ Durasi negatif biasanya disebabkan data entry tidak urut, timestamp SIMRS terbalik, atau sinkronisasi jam server yang tidak konsisten.</p>
                </div>`;
            }

            modalContent.innerHTML = `
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-3 text-sm">
                    ${[
                        ['Tunggu Poli (T3→T4)', s.waktu_tunggu_poli, 'blue'],
                        ['Layan Poli (T4→T5)', s.waktu_layan_poli, 'emerald'],
                        ['Tunggu Farmasi (T5→T6)', s.waktu_tunggu_farmasi, 'indigo'],
                        ['Layan Farmasi (T6→T7)', s.waktu_layan_farmasi, 'purple'],
                        ['Total Waktu RS (T3→T7)', s.total_waktu_rs, 'slate'],
                    ].map(([label, stat, color]) => stat.count > 0 ? `
                    <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4">
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2">${label}</p>
                        <div class="grid grid-cols-3 gap-1 text-center text-xs font-semibold mt-2">
                            <div><span class="block text-[10px] text-slate-400">Min</span>${fmt(stat,'min')}</div>
                            <div><span class="block text-[10px] text-slate-400 font-bold">Median</span><span class="${stat.median < 0 ? 'text-rose-500 font-bold text-base' : 'text-'+color+'-600 font-bold text-base'}">${stat.median !== null ? stat.median+'m' : '—'}</span></div>
                            <div><span class="block text-[10px] text-slate-400">Max</span>${fmt(stat,'max')}</div>
                        </div>
                        <div class="flex justify-between text-[10px] text-slate-400 mt-2 pt-2 border-t border-slate-100 dark:border-slate-800">
                            <span>Avg: ${stat.avg !== null ? stat.avg+'m' : '—'}</span>
                            <span>P90: ${stat.p90 !== null ? stat.p90+'m' : '—'}</span>
                            <span>n=${stat.count}</span>
                        </div>
                    </div>` : `
                    <div class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-4 opacity-40">
                        <p class="text-xs font-bold text-slate-400 uppercase mb-2">${label}</p>
                        <p class="text-center text-slate-300 font-bold text-lg">—</p>
                    </div>`).join('')}
                </div>
                ${negHTML}
            </div>`;
        })
        .catch(() => {
            modalContent.innerHTML = `<p class="text-rose-500 text-sm text-center py-6">Gagal memuat data. Coba lagi.</p>`;
        });
}

function closeClinicModal() {
    const modal = document.getElementById('clinic-detail-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}
