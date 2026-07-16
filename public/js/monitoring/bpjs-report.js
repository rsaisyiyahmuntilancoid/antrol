/**
 * Official BPJS Report Module
 * Fetches daily and monthly official reports from BPJS Web Service API,
 * renders aggregate KPIs, queue charts, paginated report tables, and SIMRS comparison.
 */

let bpjsRawList = [];
let bpjsFilteredList = [];
let bpjsCurrentPage = 1;
let bpjsPageSize = 25;
let bpjsTotalPages = 1;
let bpjsVolumeChartObj = null;
let bpjsTimesChartObj = null;

function fetchBpjsDailyReport() {
    const dateInput = document.getElementById('bpjs-date');
    const dateVal = dateInput ? dateInput.value : '';
    if (!dateVal) {
        alert('Silakan pilih tanggal terlebih dahulu.');
        return;
    }

    toggleBpjsLoading(true);

    fetch(`/api/monitoring/bpjs-dashboard/tanggal?tanggal=${dateVal}`)
        .then(res => res.json())
        .then(res => {
            toggleBpjsLoading(false);
            if (res.success && res.data) {
                renderBpjsReportData(res.data, `Rapor Resmi Tanggal: ${dateVal}`);
            } else {
                renderBpjsReportError(res.metadata?.message || 'Data tidak ditemukan atau API Error.');
            }
        })
        .catch(err => {
            toggleBpjsLoading(false);
            renderBpjsReportError('Gagal terhubung ke web service RS/BPJS.');
        });
}

function fetchBpjsMonthlyReport() {
    const monthEl = document.getElementById('bpjs-month');
    const yearEl = document.getElementById('bpjs-year');
    const month = monthEl ? monthEl.value : '';
    const year = yearEl ? yearEl.value : '';

    toggleBpjsLoading(true);

    fetch(`/api/monitoring/bpjs-dashboard/bulan?bulan=${month}&tahun=${year}`)
        .then(res => res.json())
        .then(res => {
            toggleBpjsLoading(false);
            if (res.success && res.data) {
                renderBpjsReportData(res.data, `Rapor Resmi Bulan: ${month} - ${year}`);
            } else {
                renderBpjsReportError(res.metadata?.message || 'Data tidak ditemukan atau API Error.');
            }
        })
        .catch(err => {
            toggleBpjsLoading(false);
            renderBpjsReportError('Gagal terhubung ke web service RS/BPJS.');
        });
}

function toggleBpjsLoading(isLoading) {
    const loader = document.getElementById('bpjs-report-loading');
    const empty = document.getElementById('bpjs-report-empty');
    const container = document.getElementById('bpjs-report-container');

    if (isLoading) {
        if (loader) loader.classList.remove('hidden');
        if (empty) empty.classList.add('hidden');
        if (container) container.classList.add('hidden');
    } else {
        if (loader) loader.classList.add('hidden');
    }
}

function renderBpjsReportData(bpjsData, title) {
    const container = document.getElementById('bpjs-report-container');
    if (container) container.classList.remove('hidden');

    const list = bpjsData.list || [];

    let totalQueues = 0;
    let totalPoliWait = 0;
    let totalPoliLayan = 0;
    let totalFarmasiLayan = 0;
    let totalAllTasks = 0;
    let numRecords = list.length;

    // Quick Insights Variables
    let busiestPoli = null;
    let maxQueue = -1;
    let longestWaitPoli = null;
    let maxWait = -1;
    let longestLayanPoli = null;
    let maxLayan = -1;

    list.forEach(item => {
        const q = parseInt(item.jumlah_antrean || 0);
        const w3 = parseFloat(item.avg_waktu_task3 || 0);
        const l4 = parseFloat(item.avg_waktu_task4 || 0);
        const name = item.namapoli || item.kodepoli || 'N/A';

        totalQueues += q;
        totalPoliWait += w3;
        totalPoliLayan += l4;
        totalFarmasiLayan += parseFloat(item.avg_waktu_task6 || 0);

        // Sum of all tasks 1-6
        const sumSeconds = parseFloat(item.avg_waktu_task1 || 0) +
                           parseFloat(item.avg_waktu_task2 || 0) +
                           parseFloat(item.avg_waktu_task3 || 0) +
                           parseFloat(item.avg_waktu_task4 || 0) +
                           parseFloat(item.avg_waktu_task5 || 0) +
                           parseFloat(item.avg_waktu_task6 || 0);
        totalAllTasks += sumSeconds;

        // Compute Busiest & Longest
        if (q > maxQueue) {
            maxQueue = q;
            busiestPoli = `${name} (${q} Antrean)`;
        }
        if (w3 > maxWait) {
            maxWait = w3;
            longestWaitPoli = `${name} (${(w3 / 60).toFixed(1)}m)`;
        }
        if (l4 > maxLayan) {
            maxLayan = l4;
            longestLayanPoli = `${name} (${(l4 / 60).toFixed(1)}m)`;
        }
    });

    // Convert seconds to minutes for average cards
    const avgPoliWait = numRecords ? ((totalPoliWait / numRecords) / 60).toFixed(1) : 0;
    const avgPoliLayan = numRecords ? ((totalPoliLayan / numRecords) / 60).toFixed(1) : 0;
    const avgFarmasiLayan = numRecords ? ((totalFarmasiLayan / numRecords) / 60).toFixed(1) : 0;
    const avgTotalTime = numRecords ? ((totalAllTasks / numRecords) / 60).toFixed(1) : 0;

    const statQueues = document.getElementById('bpjs-stat-queues');
    const statWaitPoli = document.getElementById('bpjs-stat-wait-poli');
    const statPoli = document.getElementById('bpjs-stat-poli');
    const statFarmasi = document.getElementById('bpjs-stat-farmasi');
    const statTotalTime = document.getElementById('bpjs-stat-total-time');

    if (statQueues) statQueues.textContent = totalQueues;
    if (statWaitPoli) statWaitPoli.textContent = `${avgPoliWait}m`;
    if (statPoli) statPoli.textContent = `${avgPoliLayan}m`;
    if (statFarmasi) statFarmasi.textContent = `${avgFarmasiLayan}m`;
    if (statTotalTime) statTotalTime.textContent = `${avgTotalTime}m`;

    // Update Quick Insights text
    const insightBusiest = document.getElementById('bpjs-insight-busiest');
    const insightWait = document.getElementById('bpjs-insight-longest-wait');
    const insightLayan = document.getElementById('bpjs-insight-longest-layan');

    if (insightBusiest) insightBusiest.textContent = busiestPoli || 'Tidak Ada';
    if (insightWait) insightWait.textContent = longestWaitPoli || 'Tidak Ada';
    if (insightLayan) insightLayan.textContent = longestLayanPoli || 'Tidak Ada';

    // Render BPJS Charts
    renderBpjsCharts(list);

    // Store to global state for pagination
    bpjsRawList = [...list];
    // Sort chronologically by date then by clinic name
    bpjsRawList.sort((a, b) => {
        const dateA = a.tanggal || '';
        const dateB = b.tanggal || '';
        if (dateA !== dateB) return dateA.localeCompare(dateB);
        const nameA = a.namapoli || a.kodepoli || '';
        const nameB = b.namapoli || b.kodepoli || '';
        return nameA.localeCompare(nameB);
    });

    bpjsFilteredList = [...bpjsRawList];
    bpjsCurrentPage = 1;

    // Reset search input
    const searchInput = document.getElementById('bpjs-search-input');
    if (searchInput) searchInput.value = '';

    paginateBpjsTable();

    // Render SIMRS vs BPJS comparison
    renderCompareTable(bpjsData);
}

function renderBpjsCharts(list) {
    const poliMap = {};
    list.forEach(item => {
        const name = item.namapoli || item.kodepoli || 'N/A';
        if (!poliMap[name]) {
            poliMap[name] = {
                name: name,
                totalQueues: 0,
                totalWaitPoli: 0,
                totalLayanPoli: 0,
                totalLayanFarmasi: 0,
                count: 0
            };
        }
        poliMap[name].totalQueues += parseInt(item.jumlah_antrean || 0);
        poliMap[name].totalWaitPoli += parseFloat(item.avg_waktu_task3 || 0);
        poliMap[name].totalLayanPoli += parseFloat(item.avg_waktu_task4 || 0);
        poliMap[name].totalLayanFarmasi += parseFloat(item.avg_waktu_task6 || 0);
        poliMap[name].count += 1;
    });

    const aggregatedPoli = Object.values(poliMap);
    const sortedForCharts = aggregatedPoli.sort((a, b) => b.totalQueues - a.totalQueues).slice(0, 7);

    const labels = sortedForCharts.map(item => item.name);
    const queueData = sortedForCharts.map(item => item.totalQueues);
    const waitData = sortedForCharts.map(item => (item.totalWaitPoli / item.count / 60).toFixed(1));
    const layanData = sortedForCharts.map(item => (item.totalLayanPoli / item.count / 60).toFixed(1));
    const layanFarmasiData = sortedForCharts.map(item => (item.totalLayanFarmasi / item.count / 60).toFixed(1));

    // Render/Update Volume Chart
    const canvasVolume = document.getElementById('bpjs-chart-volume');
    if (canvasVolume) {
        if (bpjsVolumeChartObj) bpjsVolumeChartObj.destroy();
        const ctxVolume = canvasVolume.getContext('2d');
        bpjsVolumeChartObj = new Chart(ctxVolume, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Antrean',
                    data: queueData,
                    backgroundColor: 'rgba(20, 184, 166, 0.7)',
                    borderColor: 'rgb(20, 184, 166)',
                    borderWidth: 1.5,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(226, 232, 240, 0.05)' } },
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                }
            }
        });
    }

    // Render/Update Times Chart
    const canvasTimes = document.getElementById('bpjs-chart-times');
    if (canvasTimes) {
        if (bpjsTimesChartObj) bpjsTimesChartObj.destroy();
        const ctxTimes = canvasTimes.getContext('2d');
        bpjsTimesChartObj = new Chart(ctxTimes, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Tunggu Poli (T3)',
                        data: waitData,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1.5,
                        borderRadius: 6
                    },
                    {
                        label: 'Layan Poli (T4)',
                        data: layanData,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1.5,
                        borderRadius: 6
                    },
                    {
                        label: 'Layan Farmasi (T6)',
                        data: layanFarmasiData,
                        backgroundColor: 'rgba(20, 184, 166, 0.7)',
                        borderColor: 'rgb(20, 184, 166)',
                        borderWidth: 1.5,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10 } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(226, 232, 240, 0.05)' } },
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                }
            }
        });
    }
}

function paginateBpjsTable() {
    const tbody = document.querySelector('#bpjs-report-table tbody');
    if (!tbody) return;
    tbody.innerHTML = '';

    const pageSizeEl = document.getElementById('bpjs-page-size');
    bpjsPageSize = pageSizeEl ? parseInt(pageSizeEl.value, 10) : 25;
    bpjsTotalPages = Math.ceil(bpjsFilteredList.length / bpjsPageSize) || 1;

    if (bpjsCurrentPage < 1) bpjsCurrentPage = 1;
    if (bpjsCurrentPage > bpjsTotalPages) bpjsCurrentPage = bpjsTotalPages;

    const startIdx = (bpjsCurrentPage - 1) * bpjsPageSize;
    const endIdx = startIdx + bpjsPageSize;
    const pageItems = bpjsFilteredList.slice(startIdx, endIdx);

    if (pageItems.length === 0) {
        tbody.innerHTML = `<tr><td colspan="10" class="py-10 text-center text-slate-400">Tidak ada laporan pelayanan tercatat di server BPJS Kesehatan.</td></tr>`;
        updateBpjsPaginationControls();
        return;
    }

    const formatDateStr = (dateStr) => {
        if (!dateStr) return '-';
        try {
            const parts = dateStr.split('-');
            if (parts.length === 3) {
                return `${parts[2]}-${parts[1]}-${parts[0]}`;
            }
            return dateStr;
        } catch (e) {
            return dateStr;
        }
    };

    pageItems.forEach(item => {
        const sumSeconds = parseFloat(item.avg_waktu_task1 || 0) +
                           parseFloat(item.avg_waktu_task2 || 0) +
                           parseFloat(item.avg_waktu_task3 || 0) +
                           parseFloat(item.avg_waktu_task4 || 0) +
                           parseFloat(item.avg_waktu_task5 || 0) +
                           parseFloat(item.avg_waktu_task6 || 0);
        const totalMinutes = (sumSeconds / 60).toFixed(1);

        tbody.innerHTML += `
            <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/10 transition-colors border-b border-slate-100 dark:border-slate-800/50">
                <td class="px-6 py-4 text-slate-500 font-mono text-xs">${formatDateStr(item.tanggal)}</td>
                <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">${item.namapoli || item.kodepoli || 'N/A'}</td>
                <td class="px-6 py-4 text-center font-bold text-slate-600 dark:text-slate-400">${item.jumlah_antrean || 0}</td>
                <td class="px-6 py-4 text-center text-blue-600 font-semibold">${(parseFloat(item.avg_waktu_task1 || 0) / 60).toFixed(1)}m</td>
                <td class="px-6 py-4 text-center text-indigo-600 font-semibold">${(parseFloat(item.avg_waktu_task2 || 0) / 60).toFixed(1)}m</td>
                <td class="px-6 py-4 text-center text-amber-600 font-semibold">${(parseFloat(item.avg_waktu_task3 || 0) / 60).toFixed(1)}m</td>
                <td class="px-6 py-4 text-center text-emerald-600 font-semibold">${(parseFloat(item.avg_waktu_task4 || 0) / 60).toFixed(1)}m</td>
                <td class="px-6 py-4 text-center text-pink-600 font-semibold">${(parseFloat(item.avg_waktu_task5 || 0) / 60).toFixed(1)}m</td>
                <td class="px-6 py-4 text-center text-purple-600 font-bold">${(parseFloat(item.avg_waktu_task6 || 0) / 60).toFixed(1)}m</td>
                <td class="px-6 py-4 text-center text-teal-600 dark:text-teal-400 font-extrabold">${totalMinutes}m</td>
            </tr>
        `;
    });

    updateBpjsPaginationControls();
}

function updateBpjsPaginationControls() {
    const start = bpjsFilteredList.length === 0 ? 0 : (bpjsCurrentPage - 1) * bpjsPageSize + 1;
    const end = Math.min(bpjsCurrentPage * bpjsPageSize, bpjsFilteredList.length);
    const total = bpjsFilteredList.length;

    const infoStart = document.getElementById('bpjs-pagination-info-start');
    const infoEnd = document.getElementById('bpjs-pagination-info-end');
    const infoTotal = document.getElementById('bpjs-pagination-info-total');

    if (infoStart) infoStart.textContent = start;
    if (infoEnd) infoEnd.textContent = end;
    if (infoTotal) infoTotal.textContent = total;

    const btnFirst = document.getElementById('btn-bpjs-page-first');
    const btnPrev = document.getElementById('btn-bpjs-page-prev');
    const btnNext = document.getElementById('btn-bpjs-page-next');
    const btnLast = document.getElementById('btn-bpjs-page-last');

    if (btnFirst) btnFirst.disabled = (bpjsCurrentPage === 1);
    if (btnPrev) btnPrev.disabled = (bpjsCurrentPage === 1);
    if (btnNext) btnNext.disabled = (bpjsCurrentPage === bpjsTotalPages);
    if (btnLast) btnLast.disabled = (bpjsCurrentPage === bpjsTotalPages);

    const pagesContainer = document.getElementById('bpjs-pagination-pages');
    if (pagesContainer) {
        pagesContainer.innerHTML = '';

        let startPage = Math.max(1, bpjsCurrentPage - 2);
        let endPage = Math.min(bpjsTotalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.onclick = () => changeBpjsPage(i);
            btn.textContent = i;
            if (i === bpjsCurrentPage) {
                btn.className = "px-3 py-1.5 rounded-xl text-xs font-extrabold bg-teal-600 text-white shadow-sm shadow-teal-500/20";
            } else {
                btn.className = "glass px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 dark:text-slate-400";
            }
            pagesContainer.appendChild(btn);
        }
    }
}

function handleBpjsSearch() {
    const searchEl = document.getElementById('bpjs-search-input');
    const query = searchEl ? searchEl.value.toLowerCase().trim() : '';
    if (query === '') {
        bpjsFilteredList = [...bpjsRawList];
    } else {
        bpjsFilteredList = bpjsRawList.filter(item => {
            const poli = (item.namapoli || item.kodepoli || '').toLowerCase();
            const tgl = (item.tanggal || '').toLowerCase();
            return poli.includes(query) || tgl.includes(query);
        });
    }
    bpjsCurrentPage = 1;
    paginateBpjsTable();
}

function changeBpjsPage(page) {
    if (page < 1 || page > bpjsTotalPages) return;
    bpjsCurrentPage = page;
    paginateBpjsTable();
}

function changeBpjsPageSize(size) {
    bpjsPageSize = parseInt(size, 10);
    bpjsCurrentPage = 1;
    paginateBpjsTable();
}

function renderCompareTable(bpjsData) {
    const compareSection = document.getElementById('bpjs-compare-section');
    const tbody = document.getElementById('compare-table-body');
    if (!compareSection || !tbody) return;

    const list = bpjsData.list || [];
    let bpjsWaitPoli = 0, bpjsLayanPoli = 0, bpjsWaitFarmasi = 0, bpjsLayanFarmasi = 0;
    let countPoli = 0, countFarmasi = 0;

    list.forEach(row => {
        const wp = parseFloat(row.waktutunggu || row.waktu_tunggu || 0);
        const lp = parseFloat(row.waktulayanan || row.waktu_layan || 0);
        const wf = parseFloat(row.waktutunggufarmasi || row.waktu_tunggu_farmasi || 0);
        const lf = parseFloat(row.waktulayanfarmasi || row.waktu_layan_farmasi || 0);
        if (lp > 0) { bpjsWaitPoli += wp; bpjsLayanPoli += lp; countPoli++; }
        if (lf > 0) { bpjsWaitFarmasi += wf; bpjsLayanFarmasi += lf; countFarmasi++; }
    });

    const avgWaitPoli     = countPoli > 0 ? (bpjsWaitPoli / countPoli).toFixed(1) : null;
    const avgLayanPoli    = countPoli > 0 ? (bpjsLayanPoli / countPoli).toFixed(1) : null;
    const avgWaitFarmasi  = countFarmasi > 0 ? (bpjsWaitFarmasi / countFarmasi).toFixed(1) : null;
    const avgLayanFarmasi = countFarmasi > 0 ? (bpjsLayanFarmasi / countFarmasi).toFixed(1) : null;

    const simrsStats = (typeof simrsGlobalStats !== 'undefined') ? simrsGlobalStats : ((typeof analytics !== 'undefined') ? analytics.global_stats : {});

    const rows = [
        { label: 'Tunggu Poli (T3→T4)',   simrs: simrsStats.waktu_tunggu_poli,    bpjs: avgWaitPoli },
        { label: 'Layan Poli (T4→T5)',    simrs: simrsStats.waktu_layan_poli,     bpjs: avgLayanPoli },
        { label: 'Tunggu Farmasi (T5→T6)',simrs: simrsStats.waktu_tunggu_farmasi, bpjs: avgWaitFarmasi },
        { label: 'Layan Farmasi (T6→T7)', simrs: simrsStats.waktu_layan_farmasi,  bpjs: avgLayanFarmasi },
    ];

    tbody.innerHTML = rows.map(r => {
        const simrsVal = r.simrs?.median ?? null;
        const bpjsVal  = r.bpjs !== null ? parseFloat(r.bpjs) : null;
        const simrsStr = simrsVal !== null ? `<span class="${simrsVal < 0 ? 'text-rose-500 font-bold' : 'text-blue-600 dark:text-blue-400'}">${simrsVal}m</span>` : '<span class="text-slate-300">—</span>';
        const bpjsStr  = bpjsVal !== null ? `<span class="text-teal-600 dark:text-teal-400">${bpjsVal}m</span>` : '<span class="text-slate-300">—</span>';

        let diffStr = '—', diffClass = 'text-slate-400', note = 'Data tidak cukup';
        if (simrsVal !== null && bpjsVal !== null) {
            const diff = (bpjsVal - simrsVal).toFixed(1);
            const absDiff = Math.abs(diff);
            if (absDiff <= 5) {
                diffClass = 'text-emerald-600 dark:text-emerald-400 font-bold';
                note = 'Konsisten ✓';
            } else if (absDiff <= 15) {
                diffClass = 'text-amber-500 font-bold';
                note = 'Perlu dipantau ⚠';
            } else {
                diffClass = 'text-rose-500 font-bold';
                note = 'Investigasi ❌';
            }
            diffStr = `<span class="${diffClass}">${diff > 0 ? '+' : ''}${diff}m</span>`;
        }

        return `<tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition-colors">
            <td class="px-4 py-3 text-xs font-bold text-slate-700 dark:text-slate-300">${r.label}</td>
            <td class="px-4 py-3 text-center text-xs">${simrsStr} <span class="text-[10px] text-slate-400 ml-1">n=${r.simrs?.count ?? 0}</span></td>
            <td class="px-4 py-3 text-center text-xs">${bpjsStr}</td>
            <td class="px-4 py-3 text-center text-xs">${diffStr}</td>
            <td class="px-4 py-3 text-center text-xs font-semibold ${diffClass}">${note}</td>
        </tr>`;
    }).join('');

    compareSection.classList.remove('hidden');
}

function renderBpjsReportError(message) {
    const container = document.getElementById('bpjs-report-container');
    const empty = document.getElementById('bpjs-report-empty');

    if (container) container.classList.add('hidden');
    if (empty) {
        empty.classList.remove('hidden');
        empty.innerHTML = `
            <div class="w-24 h-24 bg-rose-50 dark:bg-rose-500/10 rounded-[35px] flex items-center justify-center text-rose-600 text-4xl shadow-xl shadow-rose-500/5 mx-auto">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h4 class="text-xl font-bold text-rose-600">Gagal Mengambil Rapor BPJS</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm mx-auto">
                    ${message}
                </p>
            </div>
        `;
    }
}
