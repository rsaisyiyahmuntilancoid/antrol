/**
 * Patient Registry Table Module
 * Handles search filtering, clinic/status filtering, client-side pagination,
 * CSV exporting, anomaly scrolling, and background auto-refreshing.
 */

let currentPage = 1;
let pageSize = 25;
let filteredRows = [];

function setupFilters() {
    const searchInput = document.getElementById('search-patient');
    const selectClinic = document.getElementById('filter-clinic');
    const selectStatus = document.getElementById('filter-status');

    if (!searchInput || !selectClinic || !selectStatus) return;

    const performFilter = () => {
        const query = searchInput.value.toLowerCase().trim();
        const selectedClinic = selectClinic.value;
        const selectedStatus = selectStatus.value;

        const rows = Array.from(document.querySelectorAll('.patient-row'));
        filteredRows = [];

        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const rm = row.getAttribute('data-rm');
            const clinic = row.getAttribute('data-clinic');
            const status = row.getAttribute('data-status');
            const hasAnomali = row.getAttribute('data-has-anomali') === 'true';
            const anomalies = (row.getAttribute('data-anomalies') || '').split(',').filter(Boolean);

            let matchesSearch = !query || name.includes(query) || rm.includes(query);
            let matchesClinic = !selectedClinic || clinic === selectedClinic;

            let matchesStatus = true;
            if (selectedStatus === 'anomali') {
                matchesStatus = hasAnomali;
            } else if (selectedStatus && selectedStatus.startsWith('anomali:')) {
                const targetAnomaly = selectedStatus.split(':')[1];
                matchesStatus = anomalies.includes(targetAnomaly);
            } else if (selectedStatus) {
                matchesStatus = status === selectedStatus;
            }

            if (matchesSearch && matchesClinic && matchesStatus) {
                filteredRows.push(row);
            } else {
                row.classList.add('hidden');
            }
        });

        currentPage = 1;
        paginate();
    };

    // Store performFilter globally so auto-refresh can invoke it
    window.performFilter = performFilter;

    searchInput.addEventListener('input', performFilter);
    selectClinic.addEventListener('change', performFilter);
    selectStatus.addEventListener('change', performFilter);

    // Run initial filter to set up pagination
    performFilter();
}

function paginate() {
    const totalItems = filteredRows.length;
    const totalPages = Math.ceil(totalItems / pageSize) || 1;

    if (currentPage < 1) currentPage = 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const startIdx = (currentPage - 1) * pageSize;
    const endIdx = Math.min(startIdx + pageSize, totalItems);

    // Hide all rows first
    const allRows = document.querySelectorAll('.patient-row');
    allRows.forEach(row => row.classList.add('hidden'));

    // Show only current page items
    for (let i = startIdx; i < endIdx; i++) {
        if (filteredRows[i]) {
            filteredRows[i].classList.remove('hidden');
        }
    }

    // Show/hide empty state
    const emptyAlert = document.getElementById('no-patients-found');
    const paginationBar = document.getElementById('pagination-controls-bar');

    if (totalItems === 0) {
        if (emptyAlert) emptyAlert.classList.remove('hidden');
        if (paginationBar) paginationBar.classList.add('hidden');
    } else {
        if (emptyAlert) emptyAlert.classList.add('hidden');
        if (paginationBar) paginationBar.classList.remove('hidden');
    }

    // Update pagination numbers info
    const infoStart = document.getElementById('pagination-info-start');
    const infoEnd = document.getElementById('pagination-info-end');
    const infoTotal = document.getElementById('pagination-info-total');

    if (infoStart) infoStart.textContent = totalItems === 0 ? 0 : startIdx + 1;
    if (infoEnd) infoEnd.textContent = endIdx;
    if (infoTotal) infoTotal.textContent = totalItems;

    // Button disabled states
    const btnFirst = document.getElementById('btn-page-first');
    const btnPrev = document.getElementById('btn-page-prev');
    const btnNext = document.getElementById('btn-page-next');
    const btnLast = document.getElementById('btn-page-last');

    if (btnFirst) btnFirst.disabled = currentPage === 1;
    if (btnPrev) btnPrev.disabled = currentPage === 1;
    if (btnNext) btnNext.disabled = currentPage === totalPages;
    if (btnLast) btnLast.disabled = currentPage === totalPages;

    // Page buttons rendering
    const pagesContainer = document.getElementById('pagination-pages');
    if (pagesContainer) {
        pagesContainer.innerHTML = '';

        const startPage = Math.max(1, currentPage - 2);
        const endPage = Math.min(totalPages, currentPage + 2);

        for (let p = startPage; p <= endPage; p++) {
            const btn = document.createElement('button');
            btn.onclick = () => changePage(p);
            btn.textContent = p;
            if (p === currentPage) {
                btn.className = "px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-600 text-white shadow-sm shadow-blue-500/20";
            } else {
                btn.className = "glass px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors";
            }
            pagesContainer.appendChild(btn);
        }
    }
}

function changePage(page) {
    const totalItems = filteredRows.length;
    const totalPages = Math.ceil(totalItems / pageSize) || 1;

    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;

    currentPage = page;
    paginate();

    // Scroll back to table header
    const registryCard = document.getElementById('patient-registry-card');
    if (registryCard) {
        registryCard.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function changePageSize(size) {
    pageSize = parseInt(size, 10);
    currentPage = 1;
    paginate();
}

function scrollToPatientTableWithAnomalies() {
    const selectStatus = document.getElementById('filter-status');
    if (selectStatus) {
        selectStatus.value = 'anomali';
        selectStatus.dispatchEvent(new Event('change'));
    }

    const registryCard = document.getElementById('patient-registry-card');
    if (registryCard) {
        registryCard.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function filterPatientByAnomaly(anomalyType) {
    const selectStatus = document.getElementById('filter-status');
    if (!selectStatus) return;

    let option = selectStatus.querySelector(`option[value="anomali:${anomalyType}"]`);
    if (!option) {
        option = document.createElement('option');
        option.value = `anomali:${anomalyType}`;
        let label = 'Anomali: ' + anomalyType.split('_').map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
        if (anomalyType === 'timestamp_buatan') label = 'Anomali: Timestamp Buatan';
        if (anomalyType === 'durasi_negatif') label = 'Anomali: Durasi Negatif';
        if (anomalyType === 'farmasi_10_menit') label = 'Anomali: Farmasi 10 Menit';
        if (anomalyType === 'belum_terkirim') label = 'Anomali: Belum Terkirim';
        option.textContent = label;
        selectStatus.appendChild(option);
    }

    selectStatus.value = `anomali:${anomalyType}`;
    selectStatus.dispatchEvent(new Event('change'));

    const tableCard = document.getElementById('patient-registry-card');
    if (tableCard) {
        tableCard.scrollIntoView({ behavior: 'smooth' });
    }
}

function exportToCSV() {
    if (!filteredRows || filteredRows.length === 0) {
        alert('Tidak ada data untuk diekspor.');
        return;
    }

    let csvContent = "\uFEFF"; // BOM for Excel UTF-8 support
    csvContent += "No,Nama Pasien,No RM,Poliklinik,Dokter,Tunggu Poli,Layan Poli,Tunggu Farmasi,Total Waktu RS,Status\r\n";

    filteredRows.forEach((row, index) => {
        const nameElement = row.querySelector('td:nth-child(1) span.font-bold');
        const name = nameElement ? nameElement.textContent.trim().replace(/"/g, '""') : '';
        const rmElement = row.querySelector('td:nth-child(1) span.text-slate-400');
        const rm = rmElement ? rmElement.textContent.replace('RM: ', '').trim().replace(/"/g, '""') : '';

        const clinic = (row.querySelector('td:nth-child(2) span:nth-child(1)')?.textContent || '').trim().replace(/"/g, '""');
        const doctor = (row.querySelector('td:nth-child(2) span:nth-child(2)')?.textContent || '').trim().replace(/"/g, '""');

        const wtp = (row.querySelector('td:nth-child(3)')?.textContent || '').trim().replace('m', '');
        const wlp = (row.querySelector('td:nth-child(4)')?.textContent || '').trim().replace('m', '');
        const wtf = (row.querySelector('td:nth-child(5)')?.textContent || '').trim().replace('m', '');
        const twr = (row.querySelector('td:nth-child(6)')?.textContent || '').trim().replace('m', '');
        const status = (row.querySelector('td:nth-child(7)')?.textContent || '').trim().replace(/\s+/g, ' ').replace(/"/g, '""');

        csvContent += `${index + 1},"${name}","${rm}","${clinic}","${doctor}",${wtp},${wlp},${wtf},${twr},"${status}"\r\n`;
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : (document.querySelector('input[name="date_from"]')?.value || 'data');
    const toVal = typeof dateTo !== 'undefined' ? dateTo : (document.querySelector('input[name="date_to"]')?.value || 'data');
    link.setAttribute("download", `rekap_monitoring_antrean_${fromVal}_to_${toVal}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Auto Refresh Logic
let autoRefreshTimer = null;

function doAjaxRefresh() {
    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : (document.querySelector('input[name="date_from"]')?.value || '');
    const toVal = typeof dateTo !== 'undefined' ? dateTo : (document.querySelector('input[name="date_to"]')?.value || '');

    const refreshBtn = document.querySelector('[title="Refresh Halaman"]');
    if (refreshBtn) refreshBtn.innerHTML = '<i class="fas fa-circle-notch animate-spin"></i>';

    fetch(`/api/monitoring/analytics?date_from=${fromVal}&date_to=${toVal}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success || !res.data) return;
            const data = res.data;

            // Update patient count card
            const totalEl = document.querySelector('#kpi-total-patients');
            const batalEl = document.querySelector('#kpi-batal-patients');
            if (totalEl) totalEl.textContent = data.summary.total_patients;
            if (batalEl) batalEl.textContent = data.summary.batal_patients;

            // Update patient table rows
            const allRows = document.querySelectorAll('.patient-row');
            if (allRows.length !== data.patients.length) {
                // Row count changed — reload to get fresh SSR
                window.location.reload();
                return;
            }

            // Update row durations and status attributes
            data.patients.forEach((p, idx) => {
                const row = allRows[idx];
                if (!row) return;

                const durations = p.durations || {};
                const cols = [
                    { key: 'waktu_tunggu_poli', idx: 3 },
                    { key: 'waktu_layan_poli', idx: 4 },
                    { key: 'waktu_tunggu_farmasi', idx: 5 },
                    { key: 'waktu_layan_farmasi', idx: 6 },
                    { key: 'total_waktu_rs', idx: 7 },
                ];

                cols.forEach(({ key, idx: colIdx }) => {
                    const cell = row.querySelector(`td:nth-child(${colIdx})`);
                    if (!cell) return;
                    const val = durations[key];
                    if (val !== null && val !== undefined) {
                        const rounded = Math.round(val * 10) / 10;
                        cell.textContent = rounded + 'm';
                    } else {
                        cell.textContent = '-';
                    }
                });

                if (p.status) row.setAttribute('data-status', p.status);
                if (p.timestamps_sent) row.setAttribute('data-timestamps-sent', JSON.stringify(p.timestamps_sent));
                if (p.durations) row.setAttribute('data-durations', JSON.stringify(p.durations));
                row.setAttribute('data-has-anomali', p.has_anomalies ? 'true' : 'false');
                row.setAttribute('data-anomalies', (p.anomalies || []).join(','));
            });

            // Re-run filter to keep display consistent
            if (typeof window.performFilter === 'function') window.performFilter();

            if (refreshBtn) refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
            console.log('[Auto-Refresh] Data diperbarui:', new Date().toLocaleTimeString());
        })
        .catch(() => {
            if (refreshBtn) refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i>';
        });
}

document.addEventListener('DOMContentLoaded', function () {
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    const autoRefreshIcon = document.getElementById('auto-refresh-icon');

    if (autoRefreshToggle) {
        const isAutoRefreshActive = localStorage.getItem('auto_refresh_monitoring') === 'true';
        autoRefreshToggle.checked = isAutoRefreshActive;

        if (isAutoRefreshActive) {
            autoRefreshIcon?.classList.add('fa-spin');
            autoRefreshTimer = setInterval(doAjaxRefresh, 30000);
        }

        autoRefreshToggle.addEventListener('change', function(e) {
            if (e.target.checked) {
                localStorage.setItem('auto_refresh_monitoring', 'true');
                autoRefreshIcon?.classList.add('fa-spin');
                autoRefreshTimer = setInterval(doAjaxRefresh, 30000);
            } else {
                localStorage.setItem('auto_refresh_monitoring', 'false');
                autoRefreshIcon?.classList.remove('fa-spin');
                if (autoRefreshTimer) {
                    clearInterval(autoRefreshTimer);
                    autoRefreshTimer = null;
                }
            }
        });
    }
});
