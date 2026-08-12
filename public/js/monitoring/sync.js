/**
 * Sync Operations Module
 * Handles triggering sync today, range sync queue polling, and background patient syncs.
 */

let pollingInterval = null;

function triggerSyncToday() {
    const btn = document.getElementById('btn-sync-today');
    const icon = document.getElementById('sync-today-icon');
    if (btn) btn.disabled = true;
    if (icon) icon.classList.add('fa-spin');

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : '';

    fetch('/api/v1/monitoring/sync-today?date=' + encodeURIComponent(fromVal), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        }
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            window.location.reload();
        } else {
            alert('Gagal sinkronisasi: ' + res.message);
            if (btn) btn.disabled = false;
            if (icon) icon.classList.remove('fa-spin');
        }
    })
    .catch(err => {
        console.error(err);
        alert('Gagal sinkronisasi karena network error.');
        if (btn) btn.disabled = false;
        if (icon) icon.classList.remove('fa-spin');
    });
}

function triggerRangeSync() {
    const btnMissing = document.getElementById('btn-sync-missing');
    const btnEmpty = document.getElementById('btn-sync-range-empty');
    if (btnMissing) btnMissing.disabled = true;
    if (btnEmpty) btnEmpty.disabled = true;

    const progressContainer = document.getElementById('sync-progress-container');
    const progressBar = document.getElementById('sync-progress-bar');
    const progressText = document.getElementById('sync-progress-text');
    const progressTitle = document.getElementById('sync-progress-title');

    if (progressContainer) progressContainer.classList.remove('hidden');
    if (progressBar) progressBar.style.width = '0%';
    if (progressText) progressText.textContent = 'Pending...';
    if (progressTitle) progressTitle.textContent = 'Menjadwalkan sinkronisasi...';

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : '';
    const toVal = typeof dateTo !== 'undefined' ? dateTo : '';

    fetch('/api/v1/monitoring/sync-range', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
        },
        body: JSON.stringify({
            date_from: fromVal,
            date_to: toVal
        })
    })
    .then(r => r.json())
    .then(res => {
        if (res.success) {
            if (progressTitle) progressTitle.textContent = 'Sinkronisasi berjalan di antrean...';
            startPollingSyncStatus();
        } else {
            alert('Gagal sinkronisasi: ' + res.message);
            if (progressContainer) progressContainer.classList.add('hidden');
            if (btnMissing) btnMissing.disabled = false;
            if (btnEmpty) btnEmpty.disabled = false;
        }
    })
    .catch(err => {
        console.error(err);
        alert('Gagal sinkronisasi karena network error.');
        if (progressContainer) progressContainer.classList.add('hidden');
        if (btnMissing) btnMissing.disabled = false;
        if (btnEmpty) btnEmpty.disabled = false;
    });
}

function startPollingSyncStatus() {
    if (pollingInterval) clearInterval(pollingInterval);

    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : '';
    const toVal = typeof dateTo !== 'undefined' ? dateTo : '';

    pollingInterval = setInterval(() => {
        fetch(`/api/v1/monitoring/sync-status?date_from=${encodeURIComponent(fromVal)}&date_to=${encodeURIComponent(toVal)}`)
        .then(r => r.json())
        .then(res => {
            if (res.success && res.data) {
                const status = res.data;
                const progressTitle = document.getElementById('sync-progress-title');
                const progressBar = document.getElementById('sync-progress-bar');
                const progressText = document.getElementById('sync-progress-text');

                if (status.status === 'processing') {
                    if (progressTitle) progressTitle.textContent = `Menyinkronkan tanggal ${status.current_date || ''}...`;
                    if (progressBar) progressBar.style.width = status.percent + '%';
                    if (progressText) progressText.textContent = status.percent + '%';
                } else if (status.status === 'completed') {
                    if (progressTitle) progressTitle.textContent = 'Sinkronisasi selesai!';
                    if (progressBar) progressBar.style.width = '100%';
                    if (progressText) progressText.textContent = '100%';
                    clearInterval(pollingInterval);
                    setTimeout(() => {
                        window.location.reload();
                    }, 1500);
                } else if (status.status === 'failed') {
                    if (progressTitle) progressTitle.textContent = 'Sinkronisasi gagal.';
                    clearInterval(pollingInterval);
                    alert('Gagal sinkronisasi di latar belakang: ' + status.error);
                }
            }
        })
        .catch(err => console.error('Error polling status:', err));
    }, 5000);
}

// Check active range sync on page load
document.addEventListener('DOMContentLoaded', function () {
    const fromVal = typeof dateFrom !== 'undefined' ? dateFrom : '';
    const toVal = typeof dateTo !== 'undefined' ? dateTo : '';

    fetch(`/api/v1/monitoring/sync-status?date_from=${encodeURIComponent(fromVal)}&date_to=${encodeURIComponent(toVal)}`)
    .then(r => r.json())
    .then(res => {
        if (res.success && res.data && res.data.status === 'processing') {
            const progressContainer = document.getElementById('sync-progress-container');
            const progressBar = document.getElementById('sync-progress-bar');
            const progressText = document.getElementById('sync-progress-text');
            const progressTitle = document.getElementById('sync-progress-title');

            if (progressContainer) progressContainer.classList.remove('hidden');
            if (progressBar) progressBar.style.width = res.data.percent + '%';
            if (progressText) progressText.textContent = res.data.percent + '%';
            if (progressTitle) progressTitle.textContent = `Menyinkronkan tanggal ${res.data.current_date || ''}...`;

            const btnMissing = document.getElementById('btn-sync-missing');
            const btnEmpty = document.getElementById('btn-sync-range-empty');
            if (btnMissing) btnMissing.disabled = true;
            if (btnEmpty) btnEmpty.disabled = true;

            startPollingSyncStatus();
        }
    });

    // Check needs_sync array from analytics
    if (typeof analytics !== 'undefined' && analytics.needs_sync && analytics.needs_sync.length > 0) {
        console.log(`Menyinkronkan ${analytics.needs_sync.length} pasien dari BPJS...`);
        syncPatientsInBackground(analytics.needs_sync);
    }
});

async function syncPatientsInBackground(patients) {
    const delayMs = 400; // 400ms delay to avoid rate limit
    const progressContainer = document.getElementById('sync-progress-container');
    const progressBar = document.getElementById('sync-progress-bar');
    const progressText = document.getElementById('sync-progress-text');
    const progressTitle = document.getElementById('sync-progress-title');

    if (progressContainer) progressContainer.classList.remove('hidden');

    for (let i = 0; i < patients.length; i++) {
        const patient = patients[i];
        const percent = Math.round(((i + 1) / patients.length) * 100);

        if (progressTitle) progressTitle.textContent = `Menyinkronkan data BPJS (${i + 1}/${patients.length})...`;
        if (progressBar) progressBar.style.width = percent + '%';
        if (progressText) progressText.textContent = percent + '%';

        try {
            const response = await fetch('/api/v1/monitoring/sync-patient', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(patient)
            });
            const result = await response.json();
            if (result.success && result.data && result.data.patient) {
                console.log(`Berhasil sync: ${patient.kodebooking}`);
                updatePatientRow(result.data.patient);
            } else if (result.success && result.patient) {
                console.log(`Berhasil sync: ${patient.kodebooking}`);
                updatePatientRow(result.patient);
            } else {
                console.error(`Gagal sync: ${patient.kodebooking}`, result.message);
            }
        } catch (e) {
            console.error(`Error sync: ${patient.kodebooking}`, e);
        }
        if (i < patients.length - 1) {
            await new Promise(r => setTimeout(r, delayMs));
        }
    }

    if (progressTitle) progressTitle.textContent = 'Sinkronisasi latar belakang selesai!';
    if (progressBar) progressBar.style.width = '100%';
    if (progressText) progressText.textContent = '100%';

    setTimeout(() => {
        if (progressContainer) progressContainer.classList.add('hidden');
    }, 3000);
}

function getStatusBadgeHtml(status, waktuBatal) {
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

function updatePatientRow(patient) {
    const rows = Array.from(document.querySelectorAll('.patient-row'));
    const row = rows.find(r => r.getAttribute('data-kodebooking') === patient.kode_booking);
    if (!row) return;

    // Update status cell (8th column)
    const statusCell = row.querySelector('td:nth-child(8)');
    if (statusCell) {
        const syncBadgeClass = patient.sync_status === 'synced' ? 'badge-green' : 'badge-slate';
        const syncBadgeText = patient.sync_status === 'synced' ? 'BPJS' : 'Pending';
        const mainBadgeHtml = getStatusBadgeHtml(patient.status, patient.waktu_batal);

        statusCell.innerHTML = `
            <div class="flex flex-col gap-1 items-center justify-center">
                ${mainBadgeHtml}
                <span class="sync-status badge ${syncBadgeClass} text-[9px]">${syncBadgeText}</span>
            </div>
        `;
    }

    // Update visible duration cells in table (3rd - 7th columns)
    const durations = patient.durations || {};
    const cols = [
        { key: 'waktu_tunggu_poli', idx: 3 },
        { key: 'waktu_layan_poli', idx: 4 },
        { key: 'waktu_tunggu_farmasi', idx: 5 },
        { key: 'waktu_layan_farmasi', idx: 6 },
        { key: 'total_waktu_rs', idx: 7 },
    ];

    cols.forEach(({ key, idx }) => {
        const cell = row.querySelector(`td:nth-child(${idx})`);
        if (!cell) return;
        const val = durations[key];
        if (val !== null && val !== undefined) {
            const rounded = Math.round(val * 10) / 10;
            if (rounded < 0) {
                cell.innerHTML = `<span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs" title="Anomali: Durasi Negatif">${rounded}m</span>`;
            } else {
                cell.textContent = rounded + 'm';
            }
        } else {
            cell.textContent = '-';
        }
    });

    if (patient.status) {
        row.setAttribute('data-status', patient.status);
    }
    if (patient.timestamps_sent) {
        row.setAttribute('data-timestamps-sent', JSON.stringify(patient.timestamps_sent));
    }
    if (patient.durations) {
        row.setAttribute('data-durations', JSON.stringify(patient.durations));
    }
    row.setAttribute('data-has-anomali', patient.has_anomalies ? 'true' : 'false');
    row.setAttribute('data-anomalies', (patient.anomalies || []).join(','));
}
