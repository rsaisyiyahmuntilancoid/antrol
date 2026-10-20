/**
 * Patient Registry Table Module
 * Handles search filtering, clinic/status filtering, server-side AJAX pagination,
 * dynamic 'menampilkan X dari total Y' indicator, CSV exporting, anomaly scrolling,
 * and background auto-refreshing.
 */

let currentPage = 1;
let pageSize = 25;
let grandTotal = 0;
let filteredTotal = 0;
let totalPages = 1;
let isLoadingPatients = false;
let searchDebounceTimer = null;

function getStatusBadgeMarkup(status, waktuBatal) {
    if (typeof getStatusBadgeHtml === 'function') {
        return getStatusBadgeHtml(status, waktuBatal);
    }
    const s = status || 'Belum Terkirim';
    if (s === 'Batal' || s === 'Tidak Hadir / Batal') {
        let extra = waktuBatal ? `<span class="text-[9px] opacity-75 ml-0.5">(${waktuBatal})</span>` : '';
        return `<span class="status-badge status-badge-rose" title="Batal / Task 99"><i class="fas fa-user-times mr-1"></i>Batal${extra}</span>`;
    } else if (s === 'Lengkap (3,4,5,6,7)') {
        return `<span class="status-badge status-badge-green" title="Lengkap (3,4,5,6,7)">Lengkap</span>`;
    } else if (s === 'Lengkap (3,4,5,6) - Farmasi Belum Selesai') {
        return `<span class="status-badge status-badge-blue" title="Lengkap (3,4,5,6) - Farmasi Belum Selesai">Lengkap (3-6)</span>`;
    } else if (s === 'Task 3,4,5') {
        return `<span class="status-badge status-badge-purple" title="Task 3,4,5">Task 3,4,5</span>`;
    } else if (s === 'Task 3,4' || s === 'Task 3') {
        return `<span class="status-badge status-badge-amber" title="${s}">${s}</span>`;
    } else if (s === 'Belum Terkirim') {
        return `<span class="status-badge status-badge-slate"><i class="fas fa-clock mr-1"></i>Belum Terkirim</span>`;
    } else {
        return `<span class="status-badge status-badge-slate">${s}</span>`;
    }
}

function renderPatientRowHtml(p) {
    const durations = p.durations || {};
    const wtp = durations.waktu_tunggu_poli;
    const wlp = durations.waktu_layan_poli;
    const wtf = durations.waktu_tunggu_farmasi;
    const wlf = durations.waktu_layan_farmasi;
    const twr = durations.total_waktu_rs;

    const formatDur = (val, color) => {
        if (val === null || val === undefined) return '-';
        const rounded = Math.round(val * 10) / 10;
        if (rounded < 0) {
            return `<span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs" title="Anomali: Durasi Negatif">${rounded}m</span>`;
        }
        return `${rounded}m`;
    };

    const syncBadgeClass = p.sync_status === 'synced' ? 'badge-green' : 'badge-slate';
    const syncBadgeText = p.sync_status === 'synced' ? 'BPJS' : 'Pending';
    const mainBadgeHtml = getStatusBadgeMarkup(p.status, p.waktu_batal);

    const anomalyBadge = p.has_anomalies
        ? `<span class="inline-flex px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[9px] font-bold tracking-wide uppercase" title="Memiliki Anomali Data"><i class="fas fa-exclamation-triangle mr-0.5"></i> ANOMALI</span>`
        : '';

    const sumberClass = p.sumber === 'Mobile JKN'
        ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400'
        : 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400';

    return `
        <tr class="patient-row hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors group cursor-pointer"
            onclick="showPatientDetail('${p.kode_booking || ''}')"
            data-rawat="${p.no_rawat || ''}"
            data-name="${(p.nm_pasien || '').toLowerCase()}"
            data-rm="${p.no_rkm_medis || ''}"
            data-clinic="${p.nm_poli || ''}"
            data-status="${p.status || ''}"
            data-has-anomali="${p.has_anomalies ? 'true' : 'false'}"
            data-anomalies="${(p.anomalies || []).join(',')}"
            data-kodebooking="${p.kode_booking || ''}">
            <td class="px-4 py-3.5">
                <div class="flex flex-col whitespace-normal">
                    <span class="font-bold text-slate-800 dark:text-slate-200 flex flex-wrap items-center gap-1.5 leading-snug">
                        ${p.nm_pasien || 'N/A'}
                        ${anomalyBadge}
                    </span>
                    <span class="text-[11px] text-slate-400 font-semibold mt-1 flex flex-wrap items-center gap-1.5">
                        <span>RM: ${p.no_rkm_medis || '-'}</span> &bull;
                        <span>Booking: <code class="font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-1.5 py-0.5 rounded border border-blue-200/50 dark:border-blue-800/50 text-[10px]">${p.kode_booking || '-'}</code></span> &bull;
                        <span>Jam Reg: ${p.jam_reg || '-'}</span> &bull;
                        <span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase ${sumberClass}">
                            ${p.sumber || 'Onsite'}
                        </span>
                    </span>
                </div>
            </td>
            <td class="px-4 py-3.5">
                <div class="flex flex-col whitespace-normal">
                    <span class="text-slate-700 dark:text-slate-300 font-bold text-xs leading-snug">${p.nm_poli || '-'}</span>
                    <span class="text-[11px] text-slate-400 font-semibold mt-1 leading-snug">${p.nm_dokter || '-'}</span>
                </div>
            </td>
            <td class="px-4 py-3.5 text-center text-blue-600 dark:text-blue-400 font-semibold">${formatDur(wtp, 'blue')}</td>
            <td class="px-4 py-3.5 text-center text-emerald-600 dark:text-emerald-400 font-semibold">${formatDur(wlp, 'emerald')}</td>
            <td class="px-4 py-3.5 text-center text-indigo-600 dark:text-indigo-400 font-semibold">${formatDur(wtf, 'indigo')}</td>
            <td class="px-4 py-3.5 text-center text-teal-600 dark:text-teal-400 font-semibold">${formatDur(wlf, 'teal')}</td>
            <td class="px-4 py-3.5 text-center text-purple-600 dark:text-purple-400 font-bold">${formatDur(twr, 'purple')}</td>
            <td class="px-4 py-3.5 text-center">
                <div class="flex flex-col gap-1 items-center justify-center">
                    ${mainBadgeHtml}
                    <span class="sync-status badge ${syncBadgeClass} text-[9px]">${syncBadgeText}</span>
                </div>
            </td>
            <td class="px-4 py-3.5 text-center" onclick="event.stopPropagation()">
                <button onclick="showPatientDetail('${p.kode_booking || ''}')"
                    class="p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all shadow-md shadow-blue-500/10">
                    <i class="fas fa-search-plus text-xs"></i> Detail
                </button>
            </td>
        </tr>
    `;
}

function renderPageButtons(pagesCount) {
    const pagesContainer = document.getElementById('pagination-pages');
    if (!pagesContainer) return;

    pagesContainer.innerHTML = '';

    const createPageBtn = (p, label = null) => {
        const btn = document.createElement('button');
        btn.onclick = () => changePage(p);
        btn.textContent = label || p;
        if (p === currentPage) {
            btn.className = "px-3 py-1.5 rounded-xl text-xs font-bold bg-blue-600 text-white shadow-sm shadow-blue-500/20";
        } else {
            btn.className = "glass px-3 py-1.5 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors";
        }
        return btn;
    };

    const createEllipsis = () => {
        const span = document.createElement('span');
        span.textContent = '...';
        span.className = "px-1.5 py-1 text-slate-400 font-bold text-xs select-none";
        return span;
    };

    if (pagesCount <= 7) {
        for (let p = 1; p <= pagesCount; p++) {
            pagesContainer.appendChild(createPageBtn(p));
        }
    } else {
        // First page
        pagesContainer.appendChild(createPageBtn(1));

        if (currentPage > 3) {
            pagesContainer.appendChild(createEllipsis());
        }

        const start = Math.max(2, currentPage - 1);
        const end = Math.min(pagesCount - 1, currentPage + 1);

        for (let p = start; p <= end; p++) {
            pagesContainer.appendChild(createPageBtn(p));
        }

        if (currentPage < pagesCount - 2) {
            pagesContainer.appendChild(createEllipsis());
        }

        // Last page
        pagesContainer.appendChild(createPageBtn(pagesCount));
    }
}

function updatePaginationControls() {
    const totalItems = filteredTotal;
    totalPages = Math.max(1, Math.ceil(totalItems / pageSize));

    if (currentPage < 1) currentPage = 1;
    if (currentPage > totalPages) currentPage = totalPages;

    const startIdx = totalItems === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const endIdx = Math.min(currentPage * pageSize, totalItems);

    // Update bottom pagination numbers info
    const infoStart = document.getElementById('pagination-info-start');
    const infoEnd = document.getElementById('pagination-info-end');
    const infoTotal = document.getElementById('pagination-info-total');
    const infoGrand = document.getElementById('pagination-info-grand-total');

    if (infoStart) infoStart.textContent = startIdx;
    if (infoEnd) infoEnd.textContent = endIdx;
    if (infoTotal) infoTotal.textContent = totalItems.toLocaleString('id-ID');

    // Check if filter is active
    const searchVal = document.getElementById('search-patient')?.value.trim() || '';
    const clinicVal = document.getElementById('filter-clinic')?.value || '';
    const statusVal = document.getElementById('filter-status')?.value || '';
    const isFiltered = (searchVal !== '' || clinicVal !== '' || statusVal !== '');

    if (infoGrand) {
        if (isFiltered && grandTotal > 0 && totalItems !== grandTotal) {
            infoGrand.textContent = `(dari total keseluruhan ${grandTotal.toLocaleString('id-ID')} pasien)`;
            infoGrand.classList.remove('hidden');
        } else {
            infoGrand.textContent = '';
            infoGrand.classList.add('hidden');
        }
    }

    // Update top header badge: "Menampilkan 1 - 25 dari X data pasien"
    const headerRange = document.getElementById('header-info-range');
    const headerTotal = document.getElementById('header-info-total');
    const headerGrand = document.getElementById('header-info-grand');

    if (headerRange) {
        headerRange.textContent = totalItems === 0 ? '0' : `${startIdx} - ${endIdx}`;
    }
    if (headerTotal) {
        headerTotal.textContent = totalItems.toLocaleString('id-ID');
    }
    if (headerGrand) {
        if (isFiltered && grandTotal > 0 && totalItems !== grandTotal) {
            headerGrand.textContent = `(total: ${grandTotal.toLocaleString('id-ID')})`;
            headerGrand.classList.remove('hidden');
        } else {
            headerGrand.textContent = '';
            headerGrand.classList.add('hidden');
        }
    }

    // Support legacy IDs if any external script touches them
    const legacyBadgeRange = document.getElementById('pagination-info-badge');
    const legacyBadgeTotal = document.getElementById('total-patients-badge');
    if (legacyBadgeRange) legacyBadgeRange.textContent = totalItems === 0 ? '0' : `${startIdx} - ${endIdx}`;
    if (legacyBadgeTotal) legacyBadgeTotal.textContent = totalItems.toLocaleString('id-ID');

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

    // Button disabled states
    const btnFirst = document.getElementById('btn-page-first');
    const btnPrev = document.getElementById('btn-page-prev');
    const btnNext = document.getElementById('btn-page-next');
    const btnLast = document.getElementById('btn-page-last');

    if (btnFirst) btnFirst.disabled = currentPage === 1;
    if (btnPrev) btnPrev.disabled = currentPage === 1;
    if (btnNext) btnNext.disabled = currentPage === totalPages;
    if (btnLast) btnLast.disabled = currentPage === totalPages;

    renderPageButtons(totalPages);
}

/**
 * Fetch patient page from server with current active filters
 */
function fetchPatientsPage(page = 1) {
    if (isLoadingPatients) return;
    isLoadingPatients = true;

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : (document.querySelector('input[name="date_from"]')?.value || '');
    const toVal = typeof dateTo !== 'undefined' ? dateTo : (document.querySelector('input[name="date_to"]')?.value || '');
    const searchInput = document.getElementById('search-patient');
    const selectClinic = document.getElementById('filter-clinic');
    const selectStatus = document.getElementById('filter-status');

    const query = searchInput ? searchInput.value.trim() : '';
    const selectedClinic = selectClinic ? selectClinic.value : '';
    const selectedStatus = selectStatus ? selectStatus.value : '';

    const tbody = document.getElementById('patients-table-body');
    if (tbody) {
        tbody.style.opacity = '0.5';
    }

    const url = `/api/v1/monitoring/patients?page=${page}&per_page=${pageSize}&date_from=${fromVal}&date_to=${toVal}&search=${encodeURIComponent(query)}&clinic=${encodeURIComponent(selectedClinic)}&status=${encodeURIComponent(selectedStatus)}`;

    fetch(url, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(res => {
        isLoadingPatients = false;
        if (tbody) tbody.style.opacity = '1';

        if (!res.success || !res.data) return;

        const data = res.data;
        currentPage = data.current_page || page;
        filteredTotal = data.total !== undefined ? data.total : 0;
        totalPages = data.last_page || Math.max(1, Math.ceil(filteredTotal / pageSize));

        const patients = data.data || [];

        if (tbody) {
            if (patients.length === 0) {
                tbody.innerHTML = '';
            } else {
                tbody.innerHTML = patients.map(p => renderPatientRowHtml(p)).join('');
            }
        }

        updatePaginationControls();
    })
    .catch(err => {
        isLoadingPatients = false;
        if (tbody) tbody.style.opacity = '1';
        console.error('[Patients Fetch Error]', err);
    });
}

function setupFilters() {
    const searchInput = document.getElementById('search-patient');
    const selectClinic = document.getElementById('filter-clinic');
    const selectStatus = document.getElementById('filter-status');
    const pageSizeSelect = document.getElementById('pagination-page-size');

    // Read initial grand total from global analytics or DOM
    if (typeof window.analytics !== 'undefined' && window.analytics?.summary?.total_patients) {
        grandTotal = Number(window.analytics.summary.total_patients);
    } else {
        const headerTotal = document.getElementById('header-info-total') || document.getElementById('total-patients-badge');
        if (headerTotal) {
            const parsed = parseInt(headerTotal.textContent.replace(/[^0-9]/g, ''), 10);
            if (!isNaN(parsed) && parsed > 0) grandTotal = parsed;
        }
    }

    filteredTotal = grandTotal;

    if (pageSizeSelect) {
        pageSize = parseInt(pageSizeSelect.value, 10) || 25;
    }

    const triggerServerFilter = () => {
        currentPage = 1;
        fetchPatientsPage(1);
    };

    window.performFilter = triggerServerFilter;

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            searchDebounceTimer = setTimeout(triggerServerFilter, 300);
        });
    }

    if (selectClinic) {
        selectClinic.addEventListener('change', triggerServerFilter);
    }

    if (selectStatus) {
        selectStatus.addEventListener('change', triggerServerFilter);
    }

    updatePaginationControls();
}

function changePage(page) {
    if (page < 1) page = 1;
    if (page > totalPages) page = totalPages;
    if (page === currentPage) return;

    currentPage = page;
    fetchPatientsPage(page);

    const registryCard = document.getElementById('patient-registry-card');
    if (registryCard) {
        registryCard.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function changePageSize(size) {
    pageSize = parseInt(size, 10) || 25;
    currentPage = 1;
    fetchPatientsPage(1);
}

function scrollToPatientTableWithAnomalies() {
    const selectStatus = document.getElementById('filter-status');
    if (selectStatus) {
        selectStatus.value = 'anomali';
        currentPage = 1;
        fetchPatientsPage(1);
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
    currentPage = 1;
    fetchPatientsPage(1);

    const registryCard = document.getElementById('patient-registry-card');
    if (registryCard) {
        registryCard.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }
}

function exportToCSV() {
    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : (document.querySelector('input[name="date_from"]')?.value || '');
    const toVal = typeof dateTo !== 'undefined' ? dateTo : (document.querySelector('input[name="date_to"]')?.value || '');
    const rows = Array.from(document.querySelectorAll('.patient-row'));

    if (rows.length === 0) {
        alert('Tidak ada data pasien untuk diekspor.');
        return;
    }

    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "No Rawat,No RM,Nama Pasien,Poliklinik,Dokter,Tunggu Poli (m),Layan Poli (m),Tunggu Farmasi (m),Layan Farmasi (m),Total RS (m),Status,Kode Booking\n";

    rows.forEach(row => {
        const rawat = row.getAttribute('data-rawat') || '';
        const rm = row.getAttribute('data-rm') || '';
        const name = (row.querySelector('td:nth-child(1) span:first-child')?.innerText || '').replace(/,/g, ' ');
        const clinicName = (row.querySelector('td:nth-child(2) span:first-child')?.innerText || '').replace(/,/g, ' ');
        const docName = (row.querySelector('td:nth-child(2) span:last-child')?.innerText || '').replace(/,/g, ' ');
        const wtp = (row.querySelector('td:nth-child(3)')?.innerText || '').replace('m', '').trim();
        const wlp = (row.querySelector('td:nth-child(4)')?.innerText || '').replace('m', '').trim();
        const wtf = (row.querySelector('td:nth-child(5)')?.innerText || '').replace('m', '').trim();
        const wlf = (row.querySelector('td:nth-child(6)')?.innerText || '').replace('m', '').trim();
        const total = (row.querySelector('td:nth-child(7)')?.innerText || '').replace('m', '').trim();
        const statusVal = row.getAttribute('data-status') || '';
        const booking = row.getAttribute('data-kodebooking') || '';

        csvContent += `\"${rawat}\",\"${rm}\",\"${name}\",\"${clinicName}\",\"${docName}\",\"${wtp}\",\"${wlp}\",\"${wtf}\",\"${wlf}\",\"${total}\",\"${statusVal}\",\"${booking}\"\n`;
    });

    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", `rekap_monitoring_antrean_${fromVal}_to_${toVal}_hal_${currentPage}.csv`);
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

    const manualIcon = document.getElementById('manual-refresh-icon');
    const refreshStatus = document.getElementById('dashboard-refresh-status');
    if (manualIcon) manualIcon.classList.add('fa-spin');
    if (refreshStatus) refreshStatus.textContent = 'Memperbarui...';

    fetch(`/api/v1/monitoring/analytics?date_from=${fromVal}&date_to=${toVal}`, {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(res => {
        if (!res.success || !res.data) return;
        const data = res.data;

        // 1. Automatically update all diagrams, timeline, and KPI cards
        if (typeof window.updateDashboardAnalytics === 'function') {
            window.updateDashboardAnalytics(data);
        }

        if (data.summary?.total_patients) {
            grandTotal = Number(data.summary.total_patients);
        }

        // 2. Fetch fresh patient rows for current page
        fetchPatientsPage(currentPage);

        if (refreshBtn) refreshBtn.innerHTML = '<i class="fas fa-redo"></i>';
        if (manualIcon) manualIcon.classList.remove('fa-spin');
        if (refreshStatus) {
            const timeStr = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
            refreshStatus.textContent = `Update: ${timeStr}`;
            refreshStatus.className = "text-xs font-semibold hidden md:inline text-emerald-600 dark:text-emerald-400";
        }
        console.log('[Auto-Refresh] Dashboard diperbarui otomatis:', new Date().toLocaleTimeString());
    })
    .catch((err) => {
        console.error('[Auto-Refresh Error]', err);
        if (refreshBtn) refreshBtn.innerHTML = '<i class="fas fa-redo"></i>';
        if (manualIcon) manualIcon.classList.remove('fa-spin');
        if (refreshStatus) {
            refreshStatus.textContent = 'Update Gagal';
            refreshStatus.className = "text-xs font-semibold hidden md:inline text-rose-500";
        }
    });
}

// Expose globally
window.setupFilters = setupFilters;
window.changePage = changePage;
window.changePageSize = changePageSize;
window.fetchPatientsPage = fetchPatientsPage;
window.scrollToPatientTableWithAnomalies = scrollToPatientTableWithAnomalies;
window.filterPatientByAnomaly = filterPatientByAnomaly;
window.exportToCSV = exportToCSV;
window.doAjaxRefresh = doAjaxRefresh;

document.addEventListener('DOMContentLoaded', function () {
    setupFilters();

    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    const autoRefreshIcon = document.getElementById('auto-refresh-icon');

    if (autoRefreshToggle) {
        const storedVal = localStorage.getItem('auto_refresh_monitoring');
        const isAutoRefreshActive = (storedVal === null || storedVal === 'true');
        autoRefreshToggle.checked = isAutoRefreshActive;

        if (isAutoRefreshActive) {
            autoRefreshIcon?.classList.add('fa-spin');
            autoRefreshTimer = setInterval(doAjaxRefresh, 30000);
        }

        autoRefreshToggle.addEventListener('change', function(e) {
            if (e.target.checked) {
                localStorage.setItem('auto_refresh_monitoring', 'true');
                autoRefreshIcon?.classList.add('fa-spin');
                if (autoRefreshTimer) clearInterval(autoRefreshTimer);
                autoRefreshTimer = setInterval(doAjaxRefresh, 30000);
                doAjaxRefresh();
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
