/**
 * Charts Module for Monitoring Dashboard
 * Renders clinic performance bar chart and visit flow status doughnut chart using Chart.js.
 */

let clinicChartObj = null;
let statusChartObj = null;

function initCharts() {
    const clinicCanvas = document.getElementById('clinicChart');
    if (!clinicCanvas) return;

    // --- 1. Bar Chart (Clinic Performance) ---
    const ctxClinic = clinicCanvas.getContext('2d');
    const clinicStatsEntries = Object.entries(analytics.clinic_stats || {});
    const clinicLabels = clinicStatsEntries.map(([name]) => name).slice(0, 8);
    const wtpData = clinicStatsEntries.map(([, data]) => data.waktu_tunggu_poli?.median || 0).slice(0, 8);
    const wlpData = clinicStatsEntries.map(([, data]) => data.waktu_layan_poli?.median || 0).slice(0, 8);
    const wtfData = clinicStatsEntries.map(([, data]) => data.waktu_tunggu_farmasi?.median || 0).slice(0, 8);
    const wlfData = clinicStatsEntries.map(([, data]) => data.waktu_layan_farmasi?.median || 0).slice(0, 8);

    if (clinicChartObj) {
        clinicChartObj.destroy();
    }

    clinicChartObj = new Chart(ctxClinic, {
        type: 'bar',
        data: {
            labels: clinicLabels,
            datasets: [
                {
                    label: 'Tunggu Poli',
                    data: wtpData,
                    backgroundColor: 'rgba(245, 158, 11, 0.85)', // Amber
                    borderRadius: 4,
                },
                {
                    label: 'Layan Poli',
                    data: wlpData,
                    backgroundColor: 'rgba(59, 130, 246, 0.85)', // Blue
                    borderRadius: 4,
                },
                {
                    label: 'Tunggu Farmasi',
                    data: wtfData,
                    backgroundColor: 'rgba(168, 85, 247, 0.85)', // Purple
                    borderRadius: 4,
                },
                {
                    label: 'Layan Farmasi',
                    data: wlfData,
                    backgroundColor: 'rgba(20, 184, 166, 0.85)', // Teal
                    borderRadius: 4,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        font: { family: 'Plus Jakarta Sans', weight: '600', size: 10 }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, stacked: true },
                y: { border: { dash: [4, 4] }, title: { display: true, text: 'Median Menit' }, stacked: true }
            }
        }
    });

    // --- 2. Doughnut Chart (Flow Status Counts) ---
    const statusCanvas = document.getElementById('statusChart');
    if (!statusCanvas) return;

    const ctxStatus = statusCanvas.getContext('2d');
    const patients = analytics.patients || [];
    const statusCounts = {};

    patients.forEach(p => {
        const status = p.status || 'Belum Terkirim';
        statusCounts[status] = (statusCounts[status] || 0) + 1;
    });

    const standardLabels = [
        'Lengkap (3,4,5,6,7)',
        'Lengkap (3,4,5,6) - Farmasi Belum Selesai',
        'Task 3,4,5',
        'Task 3,4',
        'Task 3',
        'Belum Terkirim',
        'Tidak Hadir / Batal'
    ];

    const statusLabels = [];
    const statusValues = [];
    const statusColors = [];

    const colorMap = {
        'Lengkap (3,4,5,6,7)': '#10b981', // Emerald
        'Lengkap (3,4,5,6) - Farmasi Belum Selesai': '#3b82f6', // Blue
        'Task 3,4,5': '#818cf8', // Indigo
        'Task 3,4': '#fb923c', // Orange
        'Task 3': '#f59e0b', // Amber
        'Belum Terkirim': '#64748b', // Slate
        'Tidak Hadir / Batal': '#f43f5e', // Rose
        'Tidak Terdaftar': '#94a3b8'
    };

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
            statusColors.push(colorMap[label] || '#cbd5e1');
        }
    });

    if (statusChartObj) {
        statusChartObj.destroy();
    }

    statusChartObj = new Chart(ctxStatus, {
        type: 'doughnut',
        data: {
            labels: statusLabels,
            datasets: [{
                data: statusValues,
                backgroundColor: statusColors,
                borderWidth: 0,
                hoverOffset: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false }
            }
        }
    });

    // Dynamic status legend render
    const legendContainer = document.getElementById('status-chart-legend');
    if (legendContainer) {
        legendContainer.innerHTML = statusLabels.map((label, idx) => {
            const color = statusColors[idx];
            const val = statusValues[idx];
            let shortLabel = label;
            if (label === 'Lengkap (3,4,5,6,7)') shortLabel = 'Lengkap 3-7';
            else if (label === 'Lengkap (3,4,5,6) - Farmasi Belum Selesai') shortLabel = 'Lengkap 3-6';
            else if (label === 'Tidak Hadir / Batal') shortLabel = 'Batal';

            return `
                <div class="flex items-center min-w-0" title="${label}: ${val} pasien">
                    <span class="w-2.5 h-2.5 rounded-full mr-2 shrink-0 animate-pulse" style="background-color: ${color}"></span>
                    <span class="truncate text-[11px] font-bold text-slate-600 dark:text-slate-400">${shortLabel} <span class="text-[9px] font-semibold text-slate-400">(${val})</span></span>
                </div>
            `;
        }).join('');
    }
}
