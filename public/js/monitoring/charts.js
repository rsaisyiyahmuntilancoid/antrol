/**
 * Charts Module for Monitoring Dashboard
 * Renders clinic performance bar chart and visit flow status doughnut chart using Chart.js.
 * Supports grouped (side-by-side) and stacked (accumulated) modes.
 * Supports clinic scope: 'all' (semua poliklinik) vs 'top8' (8 poliklinik teratas).
 * Supports real-time automatic updates from background sync and periodic polling.
 */

let clinicChartObj = null;
let statusChartObj = null;
let clinicChartMode = 'grouped'; // 'grouped' (berdampingan) or 'stacked' (akumulasi)
let clinicChartScope = 'all';     // 'all' (semua poli) or 'top8' (top 8)

function getAnalyticsData(customData) {
    if (customData && typeof customData === 'object' && Object.keys(customData).length > 0) {
        return customData;
    }
    if (typeof window.analytics !== 'undefined' && window.analytics && Object.keys(window.analytics).length > 0) {
        return window.analytics;
    }
    if (typeof analytics !== 'undefined' && analytics && Object.keys(analytics).length > 0) {
        return analytics;
    }
    return {};
}

/**
 * Filter and sort clinic stats based on current scope ('all' vs 'top8')
 */
function getClinicStatsEntries(data) {
    let entries = Object.entries(data.clinic_stats || {})
        .sort((a, b) => (b[1].patient_count || 0) - (a[1].patient_count || 0));

    // Prefer clinics with patients > 0 if available
    const nonZero = entries.filter(([, d]) => (d.patient_count || 0) > 0);
    if (nonZero.length > 0) {
        entries = nonZero;
    }

    if (clinicChartScope === 'top8') {
        entries = entries.slice(0, 8);
    }

    return entries;
}

/**
 * Switch clinic chart view mode: 'grouped' (berdampingan) or 'stacked' (akumulasi)
 */
function setClinicChartMode(mode) {
    clinicChartMode = mode;

    const btnGrouped = document.getElementById('btn-chart-mode-grouped');
    const btnStacked = document.getElementById('btn-chart-mode-stacked');

    if (btnGrouped && btnStacked) {
        if (mode === 'grouped') {
            btnGrouped.className = "px-3 py-1.5 rounded-lg transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400 font-bold";
            btnStacked.className = "px-3 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800 dark:hover:text-white";
        } else {
            btnStacked.className = "px-3 py-1.5 rounded-lg transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400 font-bold";
            btnGrouped.className = "px-3 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800 dark:hover:text-white";
        }
    }

    if (clinicChartObj) {
        const isStacked = (mode === 'stacked');
        clinicChartObj.options.scales.x.stacked = isStacked;
        clinicChartObj.options.scales.y.stacked = isStacked;
        clinicChartObj.options.scales.y.title.text = isStacked ? 'Akumulasi Median Menit' : 'Median Menit Riil';
        clinicChartObj.update();
    }
}

/**
 * Switch clinic chart scope: 'all' (semua poli) or 'top8' (top 8)
 */
function setClinicChartScope(scope) {
    clinicChartScope = scope;

    const btnAll = document.getElementById('btn-chart-scope-all');
    const btnTop8 = document.getElementById('btn-chart-scope-top8');

    if (btnAll && btnTop8) {
        if (scope === 'all') {
            btnAll.className = "px-2.5 py-1.5 rounded-lg transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400 font-bold";
            btnTop8.className = "px-2.5 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800 dark:hover:text-white";
        } else {
            btnTop8.className = "px-2.5 py-1.5 rounded-lg transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400 font-bold";
            btnAll.className = "px-2.5 py-1.5 rounded-lg transition-all text-slate-500 hover:text-slate-800 dark:hover:text-white";
        }
    }

    const data = getAnalyticsData();
    if (data && data.clinic_stats) {
        updateCharts(data);
    }
}

/**
 * Initialize charts with analytics data
 */
function initCharts(customData) {
    const clinicCanvas = document.getElementById('clinicChart');
    const statusCanvas = document.getElementById('statusChart');
    if (!clinicCanvas && !statusCanvas) return;

    const data = getAnalyticsData(customData);

    // --- 1. Bar Chart (Clinic Performance) ---
    if (clinicCanvas) {
        const ctxClinic = clinicCanvas.getContext('2d');
        const clinicStatsEntries = getClinicStatsEntries(data);

        const clinicLabels = clinicStatsEntries.map(([name]) => name);
        const wtpData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_tunggu_poli?.median || 0));
        const wlpData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_layan_poli?.median || 0));
        const wtfData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_tunggu_farmasi?.median || 0));
        const wlfData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_layan_farmasi?.median || 0));

        if (clinicChartObj) {
            clinicChartObj.destroy();
        }

        const isStacked = (clinicChartMode === 'stacked');

        clinicChartObj = new Chart(ctxClinic, {
            type: 'bar',
            data: {
                labels: clinicLabels,
                datasets: [
                    {
                        label: 'Tunggu Poli',
                        data: wtpData,
                        backgroundColor: 'rgba(245, 158, 11, 0.90)', // Amber
                        borderRadius: 4,
                        barPercentage: 0.85,
                        categoryPercentage: 0.85,
                    },
                    {
                        label: 'Layan Poli',
                        data: wlpData,
                        backgroundColor: 'rgba(59, 130, 246, 0.90)', // Blue
                        borderRadius: 4,
                        barPercentage: 0.85,
                        categoryPercentage: 0.85,
                    },
                    {
                        label: 'Tunggu Farmasi',
                        data: wtfData,
                        backgroundColor: 'rgba(168, 85, 247, 0.90)', // Purple
                        borderRadius: 4,
                        barPercentage: 0.85,
                        categoryPercentage: 0.85,
                    },
                    {
                        label: 'Layan Farmasi',
                        data: wlfData,
                        backgroundColor: 'rgba(20, 184, 166, 0.90)', // Teal
                        borderRadius: 4,
                        barPercentage: 0.85,
                        categoryPercentage: 0.85,
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                onClick: (event, elements) => {
                    if (elements && elements.length > 0) {
                        const index = elements[0].index;
                        const clinicName = clinicLabels[index];
                        const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : '';
                        const toVal = typeof dateTo !== 'undefined' ? dateTo : '';
                        if (clinicName && typeof showClinicDetail === 'function') {
                            showClinicDetail(clinicName, fromVal, toVal);
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            font: { family: 'Plus Jakarta Sans', weight: '600', size: 11 },
                            boxWidth: 12,
                            padding: 14,
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        padding: 10,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        callbacks: {
                            label: function(context) {
                                return ` ${context.dataset.label}: ${context.raw} menit`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        stacked: isStacked,
                        ticks: {
                            font: { size: 10, weight: '600' },
                            maxRotation: 35,
                            minRotation: 0,
                            autoSkip: false,
                        }
                    },
                    y: {
                        border: { dash: [4, 4] },
                        title: {
                            display: true,
                            text: isStacked ? 'Akumulasi Median Menit' : 'Median Menit Riil',
                            font: { size: 11, weight: 'bold' }
                        },
                        stacked: isStacked
                    }
                }
            }
        });
    }

    // --- 2. Doughnut Chart (Flow Status Counts) ---
    if (statusCanvas) {
        const ctxStatus = statusCanvas.getContext('2d');
        const statusCounts = Object.assign({}, data.status_counts || {});

        const standardLabels = [
            'Lengkap (3,4,5,6,7)',
            'Lengkap (3,4,5,6) - Farmasi Belum Selesai',
            'Task 3,4,5',
            'Task 3,4',
            'Task 3',
            'Belum Terkirim',
            'Batal'
        ];

        const colorMap = {
            'Lengkap (3,4,5,6,7)': '#10b981', // Emerald
            'Lengkap (3,4,5,6) - Farmasi Belum Selesai': '#3b82f6', // Blue
            'Task 3,4,5': '#818cf8', // Indigo
            'Task 3,4': '#fb923c', // Orange
            'Task 3': '#f59e0b', // Amber
            'Belum Terkirim': '#64748b', // Slate
            'Batal': '#f43f5e', // Rose
            'Tidak Hadir / Batal': '#f43f5e', // Rose
            'Task 1,2': '#a855f7', // Purple
            'Task 6,7': '#06b6d4', // Cyan
            'Tidak Terdaftar': '#94a3b8'
        };

        const statusLabels = [];
        const statusValues = [];
        const statusColors = [];

        standardLabels.forEach(label => {
            if (statusCounts[label] !== undefined && statusCounts[label] > 0) {
                statusLabels.push(label);
                statusValues.push(statusCounts[label]);
                statusColors.push(colorMap[label] || '#64748b');
            }
        });

        Object.entries(statusCounts).forEach(([label, count]) => {
            if (!standardLabels.includes(label) && count > 0) {
                statusLabels.push(label);
                statusValues.push(count);
                let matchedColor = colorMap[label];
                if (!matchedColor) {
                    const lcase = label.toLowerCase();
                    if (lcase.includes('batal')) matchedColor = '#f43f5e';
                    else if (lcase.includes('lengkap')) matchedColor = '#10b981';
                    else if (lcase.includes('belum')) matchedColor = '#64748b';
                    else matchedColor = '#cbd5e1';
                }
                statusColors.push(matchedColor);
            }
        });

        if (statusChartObj) {
            statusChartObj.destroy();
        }

        const totalPatients = statusValues.reduce((a, b) => a + b, 0);

        statusChartObj = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: statusColors,
                    borderWidth: 0,
                    hoverOffset: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.92)',
                        padding: 10,
                        titleFont: { size: 12, weight: 'bold' },
                        bodyFont: { size: 11 },
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const val = context.raw || 0;
                                const pct = total > 0 ? Math.round((val / total) * 100) : 0;
                                return ` ${context.label}: ${val.toLocaleString()} pasien (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Dynamic status legend render
        renderStatusLegend(statusLabels, statusValues, statusColors);

        // Update badge total
        const badge = document.getElementById('status-chart-total-badge');
        if (badge) {
            badge.textContent = `Total: ${totalPatients.toLocaleString()}`;
        }
    }
}

function renderStatusLegend(statusLabels, statusValues, statusColors) {
    const legendContainer = document.getElementById('status-chart-legend');
    if (!legendContainer) return;

    legendContainer.innerHTML = statusLabels.map((label, idx) => {
        const color = statusColors[idx];
        const val = statusValues[idx];
        let shortLabel = label;
        if (label === 'Lengkap (3,4,5,6,7)') shortLabel = 'Lengkap 3-7';
        else if (label === 'Lengkap (3,4,5,6) - Farmasi Belum Selesai') shortLabel = 'Lengkap 3-6';
        else if (label === 'Batal' || label === 'Tidak Hadir / Batal') shortLabel = 'Batal';

        return `
            <div class="flex items-center min-w-0" title="${label}: ${val} pasien">
                <span class="w-2.5 h-2.5 rounded-full mr-2 shrink-0" style="background-color: ${color}"></span>
                <span class="truncate text-[11px] font-bold text-slate-600 dark:text-slate-400">${shortLabel} <span class="text-[9px] font-semibold text-slate-400">(${val.toLocaleString()})</span></span>
            </div>
        `;
    }).join('');
}

/**
 * Updates both Chart.js instances in-place with fresh analytics data
 */
function updateCharts(data) {
    if (!data) return;

    // --- 1. Update Bar Chart ---
    if (clinicChartObj && data.clinic_stats) {
        const clinicStatsEntries = getClinicStatsEntries(data);

        const clinicLabels = clinicStatsEntries.map(([name]) => name);
        const wtpData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_tunggu_poli?.median || 0));
        const wlpData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_layan_poli?.median || 0));
        const wtfData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_tunggu_farmasi?.median || 0));
        const wlfData = clinicStatsEntries.map(([, d]) => Math.max(0, d.waktu_layan_farmasi?.median || 0));

        clinicChartObj.data.labels = clinicLabels;
        clinicChartObj.data.datasets[0].data = wtpData;
        clinicChartObj.data.datasets[1].data = wlpData;
        clinicChartObj.data.datasets[2].data = wtfData;
        clinicChartObj.data.datasets[3].data = wlfData;
        clinicChartObj.update();
    } else if (!clinicChartObj && data.clinic_stats) {
        initCharts(data);
    }

    // --- 2. Update Doughnut Chart ---
    if (statusChartObj && data.status_counts) {
        const statusCounts = Object.assign({}, data.status_counts);

        const standardLabels = [
            'Lengkap (3,4,5,6,7)',
            'Lengkap (3,4,5,6) - Farmasi Belum Selesai',
            'Task 3,4,5',
            'Task 3,4',
            'Task 3',
            'Belum Terkirim',
            'Batal'
        ];

        const colorMap = {
            'Lengkap (3,4,5,6,7)': '#10b981',
            'Lengkap (3,4,5,6) - Farmasi Belum Selesai': '#3b82f6',
            'Task 3,4,5': '#818cf8',
            'Task 3,4': '#fb923c',
            'Task 3': '#f59e0b',
            'Belum Terkirim': '#64748b',
            'Batal': '#f43f5e',
            'Tidak Hadir / Batal': '#f43f5e',
            'Task 1,2': '#a855f7',
            'Task 6,7': '#06b6d4',
            'Tidak Terdaftar': '#94a3b8'
        };

        const statusLabels = [];
        const statusValues = [];
        const statusColors = [];

        standardLabels.forEach(label => {
            if (statusCounts[label] !== undefined && statusCounts[label] > 0) {
                statusLabels.push(label);
                statusValues.push(statusCounts[label]);
                statusColors.push(colorMap[label] || '#64748b');
            }
        });

        Object.entries(statusCounts).forEach(([label, count]) => {
            if (!standardLabels.includes(label) && count > 0) {
                statusLabels.push(label);
                statusValues.push(count);
                let matchedColor = colorMap[label];
                if (!matchedColor) {
                    const lcase = label.toLowerCase();
                    if (lcase.includes('batal')) matchedColor = '#f43f5e';
                    else if (lcase.includes('lengkap')) matchedColor = '#10b981';
                    else if (lcase.includes('belum')) matchedColor = '#64748b';
                    else matchedColor = '#cbd5e1';
                }
                statusColors.push(matchedColor);
            }
        });

        statusChartObj.data.labels = statusLabels;
        statusChartObj.data.datasets[0].data = statusValues;
        statusChartObj.data.datasets[0].backgroundColor = statusColors;
        statusChartObj.update();

        renderStatusLegend(statusLabels, statusValues, statusColors);

        const totalPatients = statusValues.reduce((a, b) => a + b, 0);
        const badge = document.getElementById('status-chart-total-badge');
        if (badge) {
            badge.textContent = `Total: ${totalPatients.toLocaleString()}`;
        }
    } else if (!statusChartObj && data.status_counts) {
        initCharts(data);
    }
}

/**
 * Updates the Visual Flow Timeline durations dynamically
 */
function updateFlowTimeline(globalStats) {
    if (!globalStats) return;

    const updateConnector = (id, metric, normalColor) => {
        const el = document.getElementById(id);
        if (!el || !metric) return;

        const count = metric.count || 0;
        const median = metric.median !== null && metric.median !== undefined ? metric.median : null;

        if (count > 0 && median !== null) {
            el.textContent = `${median} m`;
            if (median < 0) {
                el.className = `flow-duration px-3 py-1.5 rounded-full bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300 font-bold text-xs shadow-sm mt-2 md:mt-0`;
            } else {
                el.className = `flow-duration px-3 py-1.5 rounded-full bg-${normalColor}-100 dark:bg-${normalColor}-900/40 text-${normalColor}-700 dark:text-${normalColor}-300 font-bold text-xs shadow-sm mt-2 md:mt-0`;
            }
        } else {
            el.textContent = 'N/A';
            el.className = `flow-duration px-3 py-1.5 rounded-full bg-slate-100 dark:bg-slate-800 text-slate-400 font-bold text-xs shadow-sm mt-2 md:mt-0`;
        }
    };

    updateConnector('timeline-waktu-tunggu-poli', globalStats.waktu_tunggu_poli, 'blue');
    updateConnector('timeline-waktu-layan-poli', globalStats.waktu_layan_poli, 'emerald');
    updateConnector('timeline-waktu-tunggu-farmasi', globalStats.waktu_tunggu_farmasi, 'indigo');
    updateConnector('timeline-waktu-layan-farmasi', globalStats.waktu_layan_farmasi, 'purple');
}

/**
 * Updates all KPI cards dynamically
 */
function updateKpiCards(data) {
    if (!data) return;

    if (data.summary) {
        const totalEl = document.getElementById('kpi-total-patients');
        const batalEl = document.getElementById('kpi-batal-patients');
        if (totalEl) totalEl.textContent = Number(data.summary.total_patients || 0).toLocaleString();
        if (batalEl) batalEl.textContent = Number(data.summary.batal_patients || 0).toLocaleString();
    }

    if (data.global_stats) {
        const updateMetric = (id, countId, metric) => {
            const el = document.getElementById(id);
            const countEl = document.getElementById(countId);
            if (el && metric) {
                if (metric.count > 0 && metric.median !== null) {
                    const isNeg = metric.median < 0;
                    el.innerHTML = isNeg
                        ? `<span class="text-rose-500">${metric.median}</span><span class="text-sm font-semibold ml-0.5 text-rose-400">m</span>`
                        : `${metric.median}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>`;
                } else {
                    el.innerHTML = `<span class="text-slate-300 dark:text-slate-600">—</span>`;
                }
            }
            if (countEl && metric) {
                countEl.textContent = metric.count > 0 ? `n=${metric.count}` : '';
            }
        };

        updateMetric('kpi-waktu-tunggu-poli', 'kpi-count-waktu-tunggu-poli', data.global_stats.waktu_tunggu_poli);
        updateMetric('kpi-waktu-layan-poli', 'kpi-count-waktu-layan-poli', data.global_stats.waktu_layan_poli);
        updateMetric('kpi-waktu-tunggu-farmasi', 'kpi-count-waktu-tunggu-farmasi', data.global_stats.waktu_tunggu_farmasi);
        updateMetric('kpi-waktu-layan-farmasi', 'kpi-count-waktu-layan-farmasi', data.global_stats.waktu_layan_farmasi);
        updateMetric('kpi-total-waktu-rs', 'kpi-count-total-waktu-rs', data.global_stats.total_waktu_rs);
    }
}

/**
 * Updates summary tables for clinics and doctors dynamically
 */
function updateSummaryTables(data) {
    if (!data) return;

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : '';
    const toVal = typeof dateTo !== 'undefined' ? dateTo : '';

    // 1. Update Clinic Summary Table
    if (data.clinic_stats) {
        const clinicTbody = document.getElementById('clinic-summary-tbody');
        if (clinicTbody) {
            const metrics = [
                { key: 'waktu_tunggu_poli', color: 'blue' },
                { key: 'waktu_layan_poli', color: 'emerald' },
                { key: 'waktu_tunggu_farmasi', color: 'indigo' },
                { key: 'waktu_layan_farmasi', color: 'teal' },
                { key: 'total_waktu_rs', color: 'purple' },
            ];

            clinicTbody.innerHTML = Object.entries(data.clinic_stats).map(([clinic, stats]) => {
                const escapedClinic = clinic.replace(/'/g, "\\'");
                const cells = metrics.map(({ key, color }) => {
                    const m = stats[key];
                    if (m && m.count > 0 && m.median !== null) {
                        return m.median < 0
                            ? `<td class="px-3 py-3 text-center font-semibold text-xs text-rose-500 font-bold">${m.median}m</td>`
                            : `<td class="px-3 py-3 text-center font-semibold text-xs text-${color}-600 dark:text-${color}-400">${m.median}m</td>`;
                    }
                    return '<td class="px-3 py-3 text-center font-semibold text-xs text-slate-300">&mdash;</td>';
                }).join('');

                return `
                    <tr class="hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-colors font-medium cursor-pointer"
                        onclick="showClinicDetail('${escapedClinic}', '${fromVal}', '${toVal}')">
                        <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200 text-xs">${clinic}</td>
                        <td class="px-3 py-3 text-center text-xs">${stats.patient_count || 0}</td>
                        ${cells}
                    </tr>
                `;
            }).join('');
        }
    }

    // 2. Update Doctor Summary Table
    if (data.doctor_stats) {
        const doctorTbody = document.getElementById('doctor-summary-tbody');
        if (doctorTbody) {
            const metrics = [
                { key: 'waktu_tunggu_poli', color: 'blue' },
                { key: 'waktu_layan_poli', color: 'emerald' },
                { key: 'waktu_tunggu_farmasi', color: 'indigo' },
                { key: 'waktu_layan_farmasi', color: 'teal' },
                { key: 'total_waktu_rs', color: 'purple' },
            ];

            doctorTbody.innerHTML = Object.entries(data.doctor_stats).map(([doctor, stats]) => {
                const cells = metrics.map(({ key, color }) => {
                    const m = stats[key];
                    if (m && m.count > 0 && m.median !== null) {
                        return m.median < 0
                            ? `<td class="px-3 py-3 text-center font-semibold text-xs text-rose-500 font-bold">${m.median}m</td>`
                            : `<td class="px-3 py-3 text-center font-semibold text-xs text-${color}-600 dark:text-${color}-400">${m.median}m</td>`;
                    }
                    return '<td class="px-3 py-3 text-center font-semibold text-xs text-slate-300">&mdash;</td>';
                }).join('');

                return `
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors font-medium">
                        <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200 text-xs">${doctor}</td>
                        <td class="px-3 py-3 text-center text-xs">${stats.patient_count || 0}</td>
                        ${cells}
                    </tr>
                `;
            }).join('');
        }
    }
}

/**
 * Master function to update all monitoring dashboard elements seamlessly
 */
function updateDashboardAnalytics(data) {
    if (!data) return;

    // Keep global analytics synchronized
    if (typeof window.analytics !== 'undefined' && window.analytics) {
        window.analytics = Object.assign({}, window.analytics, data);
    }

    updateCharts(data);

    if (data.global_stats) {
        updateFlowTimeline(data.global_stats);
    }

    updateKpiCards(data);
    updateSummaryTables(data);
}

// Expose globally
window.initCharts = initCharts;
window.setClinicChartMode = setClinicChartMode;
window.setClinicChartScope = setClinicChartScope;
window.updateCharts = updateCharts;
window.updateFlowTimeline = updateFlowTimeline;
window.updateKpiCards = updateKpiCards;
window.updateSummaryTables = updateSummaryTables;
window.updateDashboardAnalytics = updateDashboardAnalytics;
