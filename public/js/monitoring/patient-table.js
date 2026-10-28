/**
 * Patient Registry Table Module
 * Handles search filtering, clinic/status filtering, server-side AJAX pagination,
 * dynamic 'menampilkan X dari total Y' indicator, CSV exporting, anomaly scrolling,
 * background auto-refreshing, manual task ID dispatching, batch cancellation sending,
 * and web-based batch task ID sending for normal patients.
 */

let currentPage = 1;
let pageSize = 25;
let grandTotal = 0;
let filteredTotal = 0;
let totalPages = 1;
let isLoadingPatients = false;
let searchDebounceTimer = null;
let currentFetchController = null;

let isNormalBatchRunning = false;
let normalBatchPatientsQueue = [];

function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-5 right-5 z-[9999] flex flex-col gap-2 pointer-events-none max-w-sm w-full';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    const bg = type === 'success' ? 'bg-emerald-600 text-white' : (type === 'warning' ? 'bg-amber-500 text-white' : 'bg-rose-600 text-white');
    const icon = type === 'success' ? 'fa-check-circle' : (type === 'warning' ? 'fa-exclamation-triangle' : 'fa-times-circle');

    toast.className = `${bg} px-4 py-3 rounded-2xl shadow-xl flex items-center gap-3 text-xs font-bold pointer-events-auto transition-all duration-300 transform translate-y-2 opacity-0`;
    toast.innerHTML = `
        <i class="fas ${icon} text-base flex-shrink-0"></i>
        <span class="flex-grow leading-snug">${message}</span>
        <button class="opacity-75 hover:opacity-100 ml-1" onclick="this.parentElement.remove()"><i class="fas fa-times"></i></button>
    `;

    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    }, 10);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-x-4');
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

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

    const isBatal = (p.status === 'Batal' || p.status === 'Tidak Hadir / Batal');
    const isLengkap = (p.status === 'Lengkap (3,4,5,6,7)');

    let sendBtnClass = 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 dark:hover:text-white border-emerald-200/80 dark:border-emerald-900/60';
    let sendBtnIcon = 'fa-paper-plane';
    let sendBtnText = 'Kirim';
    let sendBtnTitle = 'Kirim Manual Task ID Pasien';

    if (isBatal) {
        sendBtnClass = 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 dark:hover:text-white border-rose-200/80 dark:border-rose-900/60';
        sendBtnIcon = 'fa-user-slash';
        sendBtnText = 'Kirim 99';
        sendBtnTitle = 'Kirim Task 99 (Batal)';
    } else if (isLengkap) {
        sendBtnClass = 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-700 hover:text-white dark:hover:bg-slate-700 dark:hover:text-white border-slate-200/80 dark:border-slate-700';
        sendBtnIcon = 'fa-redo-alt';
        sendBtnText = 'Ulang';
        sendBtnTitle = 'Kirim Ulang Task ID';
    }

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
            <td class="px-3 py-3 text-center" onclick="event.stopPropagation()">
                <div class="inline-flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/70 dark:border-slate-700/70 shadow-xs">
                    <button onclick="showPatientDetail('${p.kode_booking || ''}')"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-900 hover:bg-blue-600 hover:text-white dark:hover:bg-blue-600 dark:hover:text-white border border-slate-200/80 dark:border-slate-700 shadow-xs transition-all duration-150 transform active:scale-95 group"
                        title="Lihat Detail Pelayanan Pasien">
                        <i class="fas fa-search-plus text-[11px] opacity-80 group-hover:opacity-100"></i>
                        <span>Detail</span>
                    </button>
                    <button onclick="sendPatientTasksManual('${p.kode_booking || ''}', '${p.no_rawat || ''}', this)"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold ${sendBtnClass} shadow-xs transition-all duration-150 transform active:scale-95 group border"
                        title="${sendBtnTitle}">
                        <i class="fas ${sendBtnIcon} text-[11px]"></i>
                        <span>${sendBtnText}</span>
                    </button>
                    ${p.status !== 'Batal' ? `
                    <button onclick="confirmCancelPatient('${p.kode_booking || ''}', '${p.no_rawat || ''}', '${(p.nm_pasien || '').replace(/'/g, "\\'")}', this)"
                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold text-rose-600 dark:text-rose-400 bg-white dark:bg-slate-900 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 dark:hover:text-white border border-rose-200/80 dark:border-rose-800/80 shadow-xs transition-all duration-150 transform active:scale-95 group"
                        title="Batalkan kunjungan pasien di SIMRS dan kirim Task 99 ke BPJS">
                        <i class="fas fa-user-times text-[11px] opacity-80 group-hover:opacity-100"></i>
                        <span>Batal</span>
                    </button>` : ''}
                </div>
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

    const infoStart = document.getElementById('pagination-info-start');
    const infoEnd = document.getElementById('pagination-info-end');
    const infoTotal = document.getElementById('pagination-info-total');
    const infoGrand = document.getElementById('pagination-info-grand-total');

    if (infoStart) infoStart.textContent = startIdx;
    if (infoEnd) infoEnd.textContent = endIdx;
    if (infoTotal) infoTotal.textContent = totalItems.toLocaleString('id-ID');

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

    const emptyAlert = document.getElementById('no-patients-found');
    const paginationBar = document.getElementById('pagination-controls-bar');

    if (totalItems === 0) {
        if (emptyAlert) emptyAlert.classList.remove('hidden');
        if (paginationBar) paginationBar.classList.add('hidden');
    } else {
        if (emptyAlert) emptyAlert.classList.add('hidden');
        if (paginationBar) paginationBar.classList.remove('hidden');
    }

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

function fetchPatientsPage(page = 1) {
    // Abort previous in-flight request if user is actively searching/filtering
    if (currentFetchController) {
        currentFetchController.abort();
    }
    currentFetchController = new AbortController();
    const signal = currentFetchController.signal;

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
        signal: signal,
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(res => {
        isLoadingPatients = false;
        currentFetchController = null;
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
        if (err.name === 'AbortError') {
            // Superseded by newer search query
            return;
        }
        isLoadingPatients = false;
        currentFetchController = null;
        if (tbody) tbody.style.opacity = '1';
        console.error('[Patients Fetch Error]', err);
    });
}

// ═════════════════════════════════════════════════════════════════
// SINGLE PATIENT CANCELLATION (TASK 99)
// ═════════════════════════════════════════════════════════════════
function confirmCancelPatient(kodebooking, noRawat, nmPasien, btnElement) {
    const modal = document.getElementById('modal-single-cancel');
    if (!modal) {
        if (confirm(`Batalkan kunjungan pasien ${nmPasien || ''} (${noRawat}) di SIMRS dan kirim Task 99 ke BPJS?`)) {
            executeSingleCancel(kodebooking, noRawat, 'Pasien Batal / Tidak Hadir', btnElement);
        }
        return;
    }

    document.getElementById('single-cancel-kodebooking').value = kodebooking || '';
    document.getElementById('single-cancel-norawat').value = noRawat || '';
    document.getElementById('single-cancel-patient-name').textContent = nmPasien || 'Nama Pasien';
    document.getElementById('single-cancel-patient-info').textContent = `No. Rawat: ${noRawat || '-'} • Booking: ${kodebooking || '-'}`;
    document.getElementById('single-cancel-reason').value = 'Pasien Batal / Tidak Hadir';

    modal.classList.remove('hidden');
}

function closeSingleCancelModal() {
    const modal = document.getElementById('modal-single-cancel');
    if (modal) modal.classList.add('hidden');
}

async function submitSingleCancel() {
    const kb = document.getElementById('single-cancel-kodebooking')?.value || '';
    const nr = document.getElementById('single-cancel-norawat')?.value || '';
    const reason = document.getElementById('single-cancel-reason')?.value || 'Pasien Batal / Tidak Hadir';
    const btn = document.getElementById('btn-submit-single-cancel');

    if (!nr) {
        showToast('No. Rawat tidak valid', 'error');
        return;
    }

    let origBtnHtml = '';
    if (btn) {
        origBtnHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Memproses...';
    }

    try {
        await executeSingleCancel(kb, nr, reason);
        closeSingleCancelModal();
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = origBtnHtml;
        }
    }
}

async function executeSingleCancel(kodebooking, noRawat, keterangan = 'Pasien Batal / Tidak Hadir', btnElement = null) {
    let origHtml = '';
    if (btnElement) {
        origHtml = btnElement.innerHTML;
        btnElement.disabled = true;
        btnElement.innerHTML = '<i class="fas fa-circle-notch fa-spin text-[11px]"></i>';
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/api/v1/monitoring/cancel-patient', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || ''
            },
            body: JSON.stringify({
                kodebooking: kodebooking || noRawat,
                no_rawat: noRawat,
                keterangan: keterangan
            })
        });

        const res = await response.json();

        if (res.success) {
            showToast(res.message || 'Pasien berhasil dibatalkan dan Task 99 dikirim', 'success');

            if (res.patient) {
                const row = document.querySelector(`tr.patient-row[data-rawat="${noRawat}"]`) ||
                            document.querySelector(`tr.patient-row[data-kodebooking="${kodebooking}"]`);
                if (row) {
                    const tempTable = document.createElement('tbody');
                    tempTable.innerHTML = renderPatientRowHtml(res.patient);
                    const newRow = tempTable.firstElementChild;
                    if (newRow) {
                        row.replaceWith(newRow);
                    }
                }
            } else {
                fetchPatientsPage(currentPage);
            }
        } else {
            showToast(res.message || 'Gagal membatalkan pasien', 'error');
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = origHtml;
            }
        }
    } catch (err) {
        console.error('[Cancel Patient Error]', err);
        showToast('Terjadi kesalahan koneksi saat membatalkan pasien', 'error');
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.innerHTML = origHtml;
        }
    }
}


async function sendPatientTasksManual(kodebooking, noRawat, btnElement) {
    if (!kodebooking && !noRawat) {
        showToast('Data booking / No. Rawat tidak valid', 'error');
        return;
    }

    let origHtml = '';
    if (btnElement) {
        origHtml = btnElement.innerHTML;
        btnElement.disabled = true;
        btnElement.innerHTML = '<i class="fas fa-circle-notch fa-spin text-[11px]"></i> <span>Kirim...</span>';
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/api/v1/monitoring/send-patient-tasks', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || ''
            },
            body: JSON.stringify({
                kodebooking: kodebooking,
                no_rawat: noRawat
            })
        });

        const res = await response.json();

        if (res.success) {
            showToast(res.message || 'Task ID berhasil diproses', 'success');

            if (res.patient) {
                const row = document.querySelector(`tr.patient-row[data-rawat="${noRawat}"]`) ||
                            document.querySelector(`tr.patient-row[data-kodebooking="${kodebooking}"]`);
                if (row) {
                    const tempTable = document.createElement('tbody');
                    tempTable.innerHTML = renderPatientRowHtml(res.patient);
                    const newRow = tempTable.firstElementChild;
                    if (newRow) {
                        row.replaceWith(newRow);
                    }
                }
            }
        } else {
            showToast(res.message || 'Gagal mengirim Task ID', 'error');
            if (btnElement) {
                btnElement.disabled = false;
                btnElement.innerHTML = origHtml;
            }
        }
    } catch (err) {
        console.error('[Send Patient Tasks Error]', err);
        showToast('Terjadi kesalahan koneksi saat mengirim Task ID', 'error');
        if (btnElement) {
            btnElement.disabled = false;
            btnElement.innerHTML = origHtml;
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// MODAL PASIEN BATAL (TASK 99)
// ═══════════════════════════════════════════════════════════════
function openCancelTasksModal() {
    const modal = document.getElementById('modal-cancel-tasks');
    if (!modal) return;

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : (document.querySelector('input[name="date_from"]')?.value || '');
    const toVal = typeof dateTo !== 'undefined' ? dateTo : (document.querySelector('input[name="date_to"]')?.value || fromVal);

    const dateFromInput = document.getElementById('cancel-task-date-from');
    const dateToInput = document.getElementById('cancel-task-date-to');

    if (dateFromInput && fromVal) dateFromInput.value = fromVal;
    if (dateToInput && toVal) dateToInput.value = toVal;

    modal.classList.remove('hidden');
    fetchCancelledPatientsPreview();
}

function closeCancelTasksModal() {
    const modal = document.getElementById('modal-cancel-tasks');
    if (modal) modal.classList.add('hidden');
}

async function fetchCancelledPatientsPreview() {
    const fromInput = document.getElementById('cancel-task-date-from');
    const toInput = document.getElementById('cancel-task-date-to');

    const dateFromVal = fromInput ? fromInput.value : '';
    const dateToVal = toInput ? toInput.value : dateFromVal;

    if (!dateFromVal) {
        showToast('Pilih tanggal awal terlebih dahulu', 'warning');
        return;
    }

    const tbody = document.getElementById('cancel-tasks-tbody');
    const btnCheck = document.getElementById('btn-check-cancelled');
    const btnExec = document.getElementById('btn-execute-cancel');

    if (btnCheck) {
        btnCheck.disabled = true;
        btnCheck.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> Memuat...';
    }

    const rangeLabel = (dateFromVal === dateToVal || !dateToVal) ? dateFromVal : `${dateFromVal} s/d ${dateToVal}`;

    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-slate-400 font-semibold">
                    <i class="fas fa-circle-notch fa-spin text-blue-500 mr-1.5"></i> Mengambil data pasien batal (${rangeLabel})...
                </td>
            </tr>
        `;
    }

    try {
        const incUnex = document.getElementById('cancel-task-include-unexamined')?.checked ? 1 : 0;
        const res = await fetch(`/api/v1/monitoring/cancelled-patients?date_from=${dateFromVal}&date_to=${dateToVal}&include_unexamined=${incUnex}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const json = await res.json();

        if (btnCheck) {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fas fa-search text-xs"></i> Cek Pasien Batal';
        }

        if (!json.success || !json.data) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-rose-500 font-semibold">${json.message || 'Gagal memuat data'}</td></tr>`;
            return;
        }

        const d = json.data;
        const patients = d.data || [];

        const totalEl = document.getElementById('modal-stat-total');
        const belumEl = document.getElementById('modal-stat-belum');
        const sudahEl = document.getElementById('modal-stat-sudah');

        if (totalEl) totalEl.textContent = d.total || 0;
        if (belumEl) belumEl.textContent = d.belum_kirim || 0;
        if (sudahEl) sudahEl.textContent = d.sudah_kirim || 0;

        if (patients.length === 0) {
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-semibold">
                            <i class="fas fa-check-circle text-emerald-500 text-lg mb-1 block"></i>
                            Tidak ada pasien batal tercatat pada rentang ${rangeLabel}
                        </td>
                    </tr>
                `;
            }
            if (btnExec) btnExec.disabled = true;
            return;
        }

        if (btnExec) btnExec.disabled = (d.belum_kirim === 0);

        if (tbody) {
            tbody.innerHTML = patients.map(p => {
                const statusBadge = p.is_sent_99
                    ? '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400"><i class="fas fa-check mr-1"></i>Sudah Terkirim</span>'
                    : '<span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-400"><i class="fas fa-clock mr-1"></i>Belum Terkirim</span>';

                return `
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-4 py-2.5 font-bold text-slate-700 dark:text-slate-300">
                            ${p.tgl_registrasi || '-'}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-bold text-slate-800 dark:text-slate-200">${p.no_rawat}</div>
                            <code class="text-[10px] text-blue-600 dark:text-blue-400 font-mono">${p.kodebooking}</code>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-bold text-slate-800 dark:text-slate-200">${p.nm_pasien}</div>
                            <span class="text-[10px] text-slate-400">RM: ${p.no_rkm_medis}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-semibold text-slate-700 dark:text-slate-300">${p.nm_poli}</div>
                            <span class="text-[10px] text-slate-400">${p.nm_dokter}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">${p.stts || 'Batal'}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            ${statusBadge}
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (err) {
        console.error('[Fetch Cancelled Preview Error]', err);
        if (btnCheck) {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fas fa-search text-xs"></i> Cek Pasien Batal';
        }
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-rose-500 font-semibold">Terjadi kesalahan memuat data</td></tr>`;
    }
}

async function executeBatchCancelTasks() {
    const fromInput = document.getElementById('cancel-task-date-from');
    const toInput = document.getElementById('cancel-task-date-to');

    const dateFromVal = fromInput ? fromInput.value : '';
    const dateToVal = toInput ? toInput.value : dateFromVal;
    const forceCheckbox = document.getElementById('cancel-task-force');
    const isForce = forceCheckbox ? forceCheckbox.checked : false;

    if (!dateFromVal) {
        showToast('Pilih tanggal awal terlebih dahulu', 'warning');
        return;
    }

    const rangeLabel = (dateFromVal === dateToVal || !dateToVal) ? dateFromVal : `${dateFromVal} s/d ${dateToVal}`;
    const btnExec = document.getElementById('btn-execute-cancel');
    const statusText = document.getElementById('cancel-task-status-text');

    if (!confirm(`Apakah Anda yakin ingin mengirim Task 99 untuk pasien batal pada rentang tanggal ${rangeLabel}?`)) {
        return;
    }

    if (btnExec) {
        btnExec.disabled = true;
        btnExec.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> <span>Mengirim Task 99 ke BPJS...</span>';
    }
    if (statusText) {
        statusText.textContent = `Sedang memproses pengiriman batch Task 99 (${rangeLabel})...`;
        statusText.className = 'text-xs font-semibold text-blue-600 dark:text-blue-400';
    }

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch('/api/v1/monitoring/send-cancel-tasks-date', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken || ''
            },
            body: JSON.stringify({
                date_from: dateFromVal,
                date_to: dateToVal,
                force: isForce,
                include_unexamined: document.getElementById('cancel-task-include-unexamined')?.checked ? 1 : 0
            })
        });

        const res = await response.json();

        if (btnExec) {
            btnExec.disabled = false;
            btnExec.innerHTML = '<i class="fas fa-paper-plane text-xs"></i> <span>Kirim Semua Task 99 Sekarang</span>';
        }

        if (res.success) {
            const msg = `Selesai! Berhasil: ${res.success_count}, Gagal: ${res.failed_count} (Total: ${res.total_processed})`;
            showToast(msg, res.failed_count === 0 ? 'success' : 'warning');

            if (statusText) {
                statusText.textContent = msg;
                statusText.className = res.failed_count === 0 ? 'text-xs font-semibold text-emerald-600 dark:text-emerald-400' : 'text-xs font-semibold text-amber-600';
            }

            fetchCancelledPatientsPreview();

            if (typeof doAjaxRefresh === 'function') {
                doAjaxRefresh();
            }
        } else {
            showToast(res.message || 'Gagal memproses batch Task 99', 'error');
            if (statusText) {
                statusText.textContent = 'Gagal: ' + (res.message || 'Terjadi kesalahan');
                statusText.className = 'text-xs font-semibold text-rose-500';
            }
        }
    } catch (err) {
        console.error('[Execute Batch Cancel Error]', err);
        showToast('Terjadi kesalahan koneksi saat memproses batch Task 99', 'error');
        if (btnExec) {
            btnExec.disabled = false;
            btnExec.innerHTML = '<i class="fas fa-paper-plane text-xs"></i> <span>Kirim Semua Task 99 Sekarang</span>';
        }
        if (statusText) {
            statusText.textContent = 'Terjadi kesalahan koneksi.';
            statusText.className = 'text-xs font-semibold text-rose-500';
        }
    }
}

// ═══════════════════════════════════════════════════════════════
// MODAL PASIEN NORMAL (TASK 3-7 BATCH WEB)
// ═══════════════════════════════════════════════════════════════
function openNormalBatchModal() {
    const modal = document.getElementById('modal-normal-batch-tasks');
    if (!modal) return;

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : (document.querySelector('input[name="date_from"]')?.value || '');
    const toVal = typeof dateTo !== 'undefined' ? dateTo : (document.querySelector('input[name="date_to"]')?.value || fromVal);

    const dateFromInput = document.getElementById('normal-batch-date-from');
    const dateToInput = document.getElementById('normal-batch-date-to');

    if (dateFromInput && fromVal) dateFromInput.value = fromVal;
    if (dateToInput && toVal) dateToInput.value = toVal;

    modal.classList.remove('hidden');
    fetchNormalBatchPreview();
}

function closeNormalBatchModal() {
    if (isNormalBatchRunning) {
        if (!confirm('Pengiriman batch sedang berjalan. Yakin ingin menutup? Proses yang sedang berjalan akan dihentikan.')) {
            return;
        }
        isNormalBatchRunning = false;
    }
    const modal = document.getElementById('modal-normal-batch-tasks');
    if (modal) modal.classList.add('hidden');
}

async function fetchNormalBatchPreview() {
    const fromInput = document.getElementById('normal-batch-date-from');
    const toInput = document.getElementById('normal-batch-date-to');

    const dateFromVal = fromInput ? fromInput.value : '';
    const dateToVal = toInput ? toInput.value : dateFromVal;

    if (!dateFromVal) {
        showToast('Pilih tanggal awal terlebih dahulu', 'warning');
        return;
    }

    const tbody = document.getElementById('normal-batch-tbody');
    const btnCheck = document.getElementById('btn-check-normal-batch');
    const btnExec = document.getElementById('btn-execute-normal-batch');

    if (btnCheck) {
        btnCheck.disabled = true;
        btnCheck.innerHTML = '<i class="fas fa-circle-notch fa-spin text-xs"></i> Memuat...';
    }

    const rangeLabel = (dateFromVal === dateToVal || !dateToVal) ? dateFromVal : `${dateFromVal} s/d ${dateToVal}`;

    if (tbody) {
        tbody.innerHTML = `
            <tr>
                <td colspan="6" class="px-4 py-8 text-center text-slate-400 font-semibold">
                    <i class="fas fa-circle-notch fa-spin text-blue-500 mr-1.5"></i> Mengambil data pasien normal (${rangeLabel})...
                </td>
            </tr>
        `;
    }

    try {
        const res = await fetch(`/api/v1/monitoring/normal-batch-preview?date_from=${dateFromVal}&date_to=${dateToVal}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        const json = await res.json();

        if (btnCheck) {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fas fa-search text-xs"></i> Cek Pasien Normal';
        }

        if (!json.success || !json.data) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-rose-500 font-semibold">${json.message || 'Gagal memuat data'}</td></tr>`;
            return;
        }

        const d = json.data;
        const patients = d.data || [];
        normalBatchPatientsQueue = patients;

        const totalEl = document.getElementById('normal-stat-total');
        const belumEl = document.getElementById('normal-stat-belum');
        const lengkapEl = document.getElementById('normal-stat-lengkap');

        if (totalEl) totalEl.textContent = d.total || 0;
        if (belumEl) belumEl.textContent = d.belum_lengkap || 0;
        if (lengkapEl) lengkapEl.textContent = d.sudah_lengkap || 0;

        if (patients.length === 0) {
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-semibold">
                            <i class="fas fa-check-circle text-emerald-500 text-lg mb-1 block"></i>
                            Tidak ada pasien normal tercatat pada rentang ${rangeLabel}
                        </td>
                    </tr>
                `;
            }
            if (btnExec) btnExec.disabled = true;
            return;
        }

        if (btnExec) btnExec.disabled = (d.belum_lengkap === 0);

        if (tbody) {
            tbody.innerHTML = patients.map(p => {
                const badgeClass = p.is_complete ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-950/60 dark:text-amber-400';
                return `
                    <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/50 transition-colors">
                        <td class="px-4 py-2.5 font-bold text-slate-700 dark:text-slate-300">
                            ${p.tgl_registrasi || '-'}
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-bold text-slate-800 dark:text-slate-200">${p.no_rawat}</div>
                            <code class="text-[10px] text-blue-600 dark:text-blue-400 font-mono">${p.kodebooking}</code>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-bold text-slate-800 dark:text-slate-200">${p.nm_pasien}</div>
                            <span class="text-[10px] text-slate-400">RM: ${p.no_rkm_medis}</span>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="font-semibold text-slate-700 dark:text-slate-300">${p.nm_poli}</div>
                            <span class="text-[10px] text-slate-400">${p.nm_dokter}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">${p.stts || 'Periksa'}</span>
                        </td>
                        <td class="px-4 py-2.5 text-center">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold ${badgeClass}">${p.status_task}</span>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (err) {
        console.error('[Fetch Normal Batch Preview Error]', err);
        if (btnCheck) {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="fas fa-search text-xs"></i> Cek Pasien Normal';
        }
        if (tbody) tbody.innerHTML = `<tr><td colspan="6" class="px-4 py-6 text-center text-rose-500 font-semibold">Terjadi kesalahan memuat data</td></tr>`;
    }
}

async function startNormalBatchProcessing() {
    const isForce = document.getElementById('normal-batch-force')?.checked || false;
    let targets = isForce ? normalBatchPatientsQueue : normalBatchPatientsQueue.filter(p => !p.is_complete);

    if (targets.length === 0) {
        showToast('Tidak ada pasien normal yang perlu diproses', 'info');
        return;
    }

    if (!confirm(`Mulai pengiriman batch Task ID untuk ${targets.length} pasien normal? Proses akan dikirim bertahap ke server BPJS.`)) {
        return;
    }

    isNormalBatchRunning = true;

    const btnExec = document.getElementById('btn-execute-normal-batch');
    const btnStop = document.getElementById('btn-stop-normal-batch');
    const btnClose = document.getElementById('btn-close-normal-batch');
    const progressContainer = document.getElementById('normal-batch-progress-container');
    const progressBar = document.getElementById('normal-batch-progress-bar');
    const progressPercent = document.getElementById('normal-batch-progress-percent');
    const progressStatus = document.getElementById('normal-batch-progress-status');
    const progressCounts = document.getElementById('normal-batch-progress-counts');
    const progressStats = document.getElementById('normal-batch-progress-stats');
    const statusText = document.getElementById('normal-batch-status-text');

    if (btnExec) btnExec.classList.add('hidden');
    if (btnStop) {
        btnStop.disabled = false;
        btnStop.innerHTML = '<i class="fas fa-stop text-xs"></i> <span>Hentikan</span>';
        btnStop.classList.remove('hidden');
    }
    if (btnClose) btnClose.disabled = true;
    if (progressContainer) progressContainer.classList.remove('hidden');

    const totalTargets = targets.length;
    const chunkSize = 15; // 15 patients per chunk = fast & safe
    let processedTotal = 0;
    let successTotal = 0;
    let failedTotal = 0;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

    for (let i = 0; i < totalTargets; i += chunkSize) {
        if (!isNormalBatchRunning) {
            showToast('Pengiriman batch dihentikan oleh pengguna.', 'warning');
            break;
        }

        const chunk = targets.slice(i, i + chunkSize);
        const chunkPayload = chunk.map(p => ({ kodebooking: p.kodebooking, no_rawat: p.no_rawat }));

        const currentEnd = Math.min(i + chunkSize, totalTargets);
        if (progressStatus) progressStatus.innerHTML = `<i class="fas fa-circle-notch fa-spin"></i> Memproses ${i + 1} - ${currentEnd} dari ${totalTargets}...`;
        if (statusText) statusText.textContent = `Mengirim chunk ${Math.floor(i / chunkSize) + 1}...`;

        try {
            const resp = await fetch('/api/v1/monitoring/send-normal-batch-chunk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken || ''
                },
                body: JSON.stringify({ patients: chunkPayload })
            });

            const resJson = await resp.json();

            if (resJson.success) {
                successTotal += (resJson.success_count || 0);
                failedTotal += (resJson.failed_count || 0);
            } else {
                failedTotal += chunk.length;
            }
        } catch (err) {
            console.error('[Batch Chunk Error]', err);
            failedTotal += chunk.length;
        }

        processedTotal = currentEnd;
        const pct = Math.round((processedTotal / totalTargets) * 100);

        if (progressBar) progressBar.style.width = `${pct}%`;
        if (progressPercent) progressPercent.textContent = `${pct}%`;
        if (progressCounts) progressCounts.textContent = `Proses: ${processedTotal} / ${totalTargets}`;
        if (progressStats) progressStats.textContent = `Sukses: ${successTotal} | Gagal: ${failedTotal}`;
    }

    isNormalBatchRunning = false;

    if (btnExec) btnExec.classList.remove('hidden');
    if (btnStop) btnStop.classList.add('hidden');
    if (btnClose) btnClose.disabled = false;

    const summaryMsg = `Batch Selesai! Berhasil: ${successTotal}, Gagal: ${failedTotal} dari total ${processedTotal} pasien.`;
    showToast(summaryMsg, failedTotal === 0 ? 'success' : 'warning');

    if (statusText) {
        statusText.textContent = summaryMsg;
        statusText.className = failedTotal === 0 ? 'text-xs font-semibold text-emerald-600 dark:text-emerald-400' : 'text-xs font-semibold text-amber-600';
    }
    if (progressStatus) {
        progressStatus.innerHTML = `<i class="fas fa-check-circle text-emerald-500"></i> Pengiriman Selesai`;
    }

    fetchNormalBatchPreview();

    if (typeof doAjaxRefresh === 'function') {
        doAjaxRefresh();
    }
}

function stopNormalBatchProcessing() {
    isNormalBatchRunning = false;
    const btnStop = document.getElementById('btn-stop-normal-batch');
    if (btnStop) {
        btnStop.disabled = true;
        btnStop.innerHTML = '<i class="fas fa-spinner fa-spin text-xs"></i> Menghentikan...';
    }
}

function setupFilters() {
    const searchInput = document.getElementById('search-patient');
    const selectClinic = document.getElementById('filter-clinic');
    const selectStatus = document.getElementById('filter-status');
    const pageSizeSelect = document.getElementById('pagination-page-size');

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
            searchDebounceTimer = setTimeout(triggerServerFilter, 400);
        });

        searchInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
                triggerServerFilter();
            }
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

        if (typeof window.updateDashboardAnalytics === 'function') {
            window.updateDashboardAnalytics(data);
        }

        if (data.summary?.total_patients) {
            grandTotal = Number(data.summary.total_patients);
        }

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
window.showToast = showToast;
window.sendPatientTasksManual = sendPatientTasksManual;
window.confirmCancelPatient = confirmCancelPatient;
window.closeSingleCancelModal = closeSingleCancelModal;
window.submitSingleCancel = submitSingleCancel;
window.openCancelTasksModal = openCancelTasksModal;
window.closeCancelTasksModal = closeCancelTasksModal;
window.fetchCancelledPatientsPreview = fetchCancelledPatientsPreview;
window.executeBatchCancelTasks = executeBatchCancelTasks;

window.openNormalBatchModal = openNormalBatchModal;
window.closeNormalBatchModal = closeNormalBatchModal;
window.fetchNormalBatchPreview = fetchNormalBatchPreview;
window.startNormalBatchProcessing = startNormalBatchProcessing;
window.stopNormalBatchProcessing = stopNormalBatchProcessing;

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
