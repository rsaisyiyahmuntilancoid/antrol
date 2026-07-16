/**
 * Detail Panel Module
 * Manages slide-over patient detail panel, timeline rendering,
 * date parsing helpers, and on-demand BPJS API cross-verification.
 */

// Helper to safely parse different date formats in JS
function parseJsDate(val) {
    if (!val) return null;
    let sVal = String(val).trim();
    // Clean WIB/WITA/WIT
    sVal = sVal.replace(/\s+(WIB|WITA|WIT)$/i, '');

    // If it is a 13-digit timestamp
    if (/^\d{13}$/.test(sVal)) {
        return new Date(parseInt(sVal, 10));
    }
    // If it is a 10-digit timestamp
    if (/^\d{10}$/.test(sVal)) {
        return new Date(parseInt(sVal, 10) * 1000);
    }

    // Match DD-MM-YYYY HH:mm:ss or DD-MM-YYYY HH:mm
    const dmyMatch = sVal.match(/^(\d{2})[-/](\d{2})[-/](\d{4})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    if (dmyMatch) {
        const day = parseInt(dmyMatch[1], 10);
        const month = parseInt(dmyMatch[2], 10) - 1; // JS months are 0-indexed
        const year = parseInt(dmyMatch[3], 10);
        const hour = dmyMatch[4] ? parseInt(dmyMatch[4], 10) : 0;
        const minute = dmyMatch[5] ? parseInt(dmyMatch[5], 10) : 0;
        const second = dmyMatch[6] ? parseInt(dmyMatch[6], 10) : 0;
        return new Date(year, month, day, hour, minute, second);
    }

    // Match YYYY-MM-DD HH:mm:ss
    const ymdMatch = sVal.match(/^(\d{4})[-/](\d{2})[-/](\d{2})(?:\s+(\d{2}):(\d{2})(?::(\d{2}))?)?$/);
    if (ymdMatch) {
        const year = parseInt(ymdMatch[1], 10);
        const month = parseInt(ymdMatch[2], 10) - 1;
        const day = parseInt(ymdMatch[3], 10);
        const hour = ymdMatch[4] ? parseInt(ymdMatch[4], 10) : 0;
        const minute = ymdMatch[5] ? parseInt(ymdMatch[5], 10) : 0;
        const second = ymdMatch[6] ? parseInt(ymdMatch[6], 10) : 0;
        return new Date(year, month, day, hour, minute, second);
    }

    const d = new Date(sVal);
    return isNaN(d.getTime()) ? null : d;
}

// Format Date to Time string
function formatTimeOnly(dateObj) {
    if (!dateObj) return '--:--:--';
    return dateObj.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

function showPatientDetail(noRawat) {
    const panel = document.getElementById('slide-detail-panel');
    const overlay = document.getElementById('panel-overlay');
    const content = document.getElementById('panel-content');
    const footer = document.getElementById('panel-footer');

    if (!panel || !overlay || !content) return;

    // Loading indicator
    content.innerHTML = `
        <div class="flex flex-col items-center justify-center py-20 space-y-4">
            <div class="w-10 h-10 border-4 border-blue-600/20 border-t-blue-600 rounded-full animate-spin"></div>
            <p class="text-sm font-semibold text-slate-500">Mengambil data rekam medis...</p>
        </div>
    `;
    if (footer) footer.innerHTML = '';

    // Show panel & overlay
    overlay.classList.remove('hidden');
    setTimeout(() => {
        overlay.classList.remove('opacity-0');
        panel.classList.add('open');
    }, 50);

    fetch(`/api/monitoring/patient/${encodeURIComponent(noRawat)}`)
        .then(res => res.json())
        .then(res => {
            if (res.success && res.data) {
                renderDetailContent(res.data);
            } else {
                content.innerHTML = `<div class="p-6 text-rose-500 font-bold bg-rose-500/10 border border-rose-500/20 rounded-2xl">Gagal memuat detail pasien: ${res.message || 'Error'}</div>`;
            }
        })
        .catch(err => {
            content.innerHTML = `<div class="p-6 text-rose-500 font-bold bg-rose-500/10 border border-rose-500/20 rounded-2xl">Terjadi kesalahan koneksi saat memuat data pasien.</div>`;
        });
}

function renderDetailContent(patient) {
    const content = document.getElementById('panel-content');
    const footer = document.getElementById('panel-footer');
    if (!content) return;

    // Render durasi internal
    let durationItemsHTML = '';
    const durLabels = {
        'waktu_tunggu_poli': 'Tunggu Poli (Task 3 &rarr; 4)',
        'waktu_layan_poli': 'Layanan Poli (Task 4 &rarr; 5)',
        'waktu_tunggu_farmasi': 'Tunggu Farmasi (Task 5 &rarr; 6)',
        'waktu_layan_farmasi': 'Layanan Farmasi (Task 6 &rarr; 7)',
        'total_waktu_rs': 'Total Pelayanan RS (Task 3 &rarr; 7)'
    };

    for (const [key, val] of Object.entries(patient.durations || {})) {
        if (val !== null) {
            let badgeClass = 'text-slate-600 dark:text-slate-300 font-bold';
            let boxClass = 'bg-slate-50/50 dark:bg-slate-900 border border-slate-200/40 dark:border-slate-800/40';
            const valNum = Number(val);

            if (valNum < 0) {
                boxClass = 'bg-rose-500/5 border border-rose-500/10 dark:border-rose-500/20';
                badgeClass = 'text-rose-600 dark:text-rose-400 font-bold bg-rose-50 dark:bg-rose-500/10 px-2 py-0.5 rounded-lg';
            } else {
                if (key === 'waktu_layan_poli') {
                    boxClass = 'bg-emerald-500/5 border border-emerald-500/10 dark:border-emerald-500/20';
                    badgeClass = 'text-emerald-600 dark:text-emerald-400 font-bold';
                }
                if (key === 'waktu_layan_farmasi') {
                    boxClass = 'bg-teal-500/5 border border-teal-500/10 dark:border-teal-500/20';
                    badgeClass = 'text-teal-600 dark:text-teal-400 font-bold';
                }
                if (key === 'total_waktu_rs') {
                    boxClass = 'bg-blue-600/5 border border-blue-600/10 dark:border-blue-600/20';
                    badgeClass = 'text-blue-600 dark:text-blue-400 font-extrabold';
                }
            }

            durationItemsHTML += `
                <div class="flex justify-between items-center p-3.5 rounded-2xl ${boxClass}">
                    <span class="text-xs font-semibold text-slate-500">${durLabels[key] || key}</span>
                    <span class="text-sm ${badgeClass}">${valNum.toFixed(1)} Menit</span>
                </div>
            `;
        }
    }

    // Timeline items
    const taskDetails = {
        3: { name: 'Admisi / Check-in', icon: 'fa-hospital-user', color: 'blue' },
        4: { name: 'Mulai Layanan Perawat', icon: 'fa-user-nurse', color: 'indigo' },
        5: { name: 'Selesai Layanan Dokter', icon: 'fa-user-md', color: 'emerald' },
        6: { name: 'Mulai Layanan Farmasi', icon: 'fa-prescription-bottle', color: 'purple' },
        7: { name: 'Selesai Penyerahan Obat', icon: 'fa-capsules', color: 'slate' }
    };

    let timelineHTML = '';
    for (let i = 3; i <= 7; i++) {
        const realT = (patient.timestamps_real || {})[i];
        const sentT = (patient.timestamps_sent || {})[i];
        const task = taskDetails[i];

        const parsedReal = parseJsDate(realT);
        const parsedSent = parseJsDate(sentT);

        let statusHTML = '';
        let lineActive = parsedSent ? 'border-blue-500 dark:border-blue-600' : 'border-slate-200 dark:border-slate-800';
        let circleColor = parsedSent ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-600';

        if (parsedReal && parsedSent) {
            const diffSec = Math.abs(parsedReal - parsedSent) / 1000;
            if (diffSec < 2) {
                statusHTML = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-500 mt-1"><i class="fas fa-check-circle"></i> Sinkron & Sesuai</span>`;
            } else {
                const offsetDiff = Math.round(diffSec / 60);
                statusHTML = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-amber-500 mt-1"><i class="fas fa-exclamation-circle"></i> Selisih ${offsetDiff}m (Buatan)</span>`;
            }
        } else if (parsedReal && !parsedSent) {
            statusHTML = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-rose-500 mt-1"><i class="fas fa-times-circle"></i> Belum Dikirim</span>`;
        } else if (!parsedReal && parsedSent) {
            statusHTML = `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-indigo-500 mt-1"><i class="fas fa-magic"></i> Hasil Fallback (Fake)</span>`;
        } else {
            statusHTML = `<span class="inline-flex items-center gap-1 text-[10px] text-slate-400 mt-1"><i class="far fa-circle"></i> Belum Ada</span>`;
        }

        const bpjsTimeStr = parsedSent ? formatTimeOnly(parsedSent) : '--:--:--';
        timelineHTML += `
            <div class="relative pl-8 pb-6 border-l-2 ${lineActive} last:border-l-0 ml-3.5">
                <!-- Circle indicator -->
                <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full flex items-center justify-center ${circleColor} z-10 shadow-sm border-2 border-white dark:border-slate-950">
                    <div class="w-1.5 h-1.5 rounded-full bg-current"></div>
                </div>

                <div class="flex-grow -mt-1">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Task ${i}: ${task.name}</span>
                        <span class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">${bpjsTimeStr}</span>
                    </div>
                    <div class="grid grid-cols-2 gap-2 mt-2">
                        <div class="bg-slate-50 dark:bg-slate-900/40 p-2 rounded-xl border border-slate-200/40 dark:border-slate-800/40">
                            <span class="text-[9px] uppercase font-bold text-slate-400">SIMRS Real</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${formatTimeOnly(parsedReal)}</p>
                        </div>
                        <div class="bg-slate-50 dark:bg-slate-900/40 p-2 rounded-xl border border-slate-200/40 dark:border-slate-800/40">
                            <span class="text-[9px] uppercase font-bold text-slate-400">BPJS Sent</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${bpjsTimeStr}</p>
                        </div>
                    </div>
                    ${statusHTML}
                </div>
            </div>
        `;
    }

    // Render Panel Body
    content.innerHTML = `
        <div class="space-y-6">
            <!-- Identity Card -->
            <div class="bg-slate-50/50 dark:bg-slate-900/40 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl">
                <div class="flex items-start justify-between gap-2">
                    <div>
                        <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Nama Lengkap Pasien</span>
                        <h4 class="text-lg font-bold text-slate-900 dark:text-white mt-0.5 leading-snug">${patient.nm_pasien || '—'}</h4>
                    </div>
                    <span class="shrink-0 px-2.5 py-1 rounded-xl text-[10px] font-extrabold uppercase ${patient.stts === 'Batal' ? 'bg-rose-100 text-rose-600 dark:bg-rose-500/20 dark:text-rose-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/20 dark:text-emerald-400'}">${patient.stts || 'Aktif'}</span>
                </div>

                <div class="grid grid-cols-2 gap-x-4 gap-y-3 mt-4 pt-4 border-t border-slate-200/40 dark:border-slate-800/40">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No. Rekam Medis</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${patient.no_rkm_medis || '—'}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No. Registrasi / Rawat</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5 truncate" title="${patient.no_rawat || ''}">${patient.no_rawat || '—'}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">NIK / No. KTP</span>
                        <p class="text-xs font-bold ${patient.no_ktp ? 'text-slate-700 dark:text-slate-300' : 'text-slate-300 dark:text-slate-600'} mt-0.5">${patient.no_ktp || '—'}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No. Kartu BPJS</span>
                        <p class="text-xs font-bold ${patient.no_kartu_bpjs ? 'text-blue-600 dark:text-blue-400' : 'text-slate-300 dark:text-slate-600'} mt-0.5">${patient.no_kartu_bpjs || '—'}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tanggal Lahir</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${patient.tgl_lahir || '—'}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Tgl Daftar / Jam</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${patient.tgl_registrasi || '—'} ${patient.jam_reg ? '· '+patient.jam_reg : ''}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Poliklinik</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${patient.nm_poli || '—'}</p>
                    </div>
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dokter</span>
                        <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5 truncate" title="${patient.nm_dokter || ''}">${patient.nm_dokter || '—'}</p>
                    </div>
                </div>
            </div>

            <!-- Durations Summary -->
            <div class="space-y-2">
                <h4 class="text-xs uppercase font-extrabold text-slate-400 dark:text-slate-500 tracking-wider">Kalkulasi Durasi Antrean Resmi (BPJS)</h4>
                <div class="space-y-2.5">
                    ${durationItemsHTML || '<div class="text-xs text-slate-400 p-4 text-center bg-slate-50 dark:bg-slate-900 rounded-2xl">Tidak ada data pelayanan yang terhitung.</div>'}
                </div>
            </div>

            ${patient.anomaly_hints && patient.anomaly_hints.length > 0 ? `
            <!-- Anomaly Analysis Hints -->
            <div class="bg-amber-50/70 dark:bg-amber-500/5 border border-amber-200 dark:border-amber-500/20 rounded-2xl p-4">
                <h5 class="text-xs font-extrabold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-2 flex items-center gap-1.5">
                    <i class="fas fa-lightbulb"></i> Analisis Kemungkinan Penyebab Anomali
                </h5>
                <ul class="space-y-1.5">
                    ${patient.anomaly_hints.map(h => `<li class="text-xs text-amber-700 dark:text-amber-300 flex items-start gap-2"><i class="fas fa-angle-right mt-0.5 shrink-0"></i><span>${h}</span></li>`).join('')}
                </ul>
            </div>` : ''}

            <!-- Timeline Chronology -->
            <div class="space-y-4">
                <h4 class="text-xs uppercase font-extrabold text-slate-400 dark:text-slate-500 tracking-wider">Perbandingan Timestamp: SIMRS vs BPJS (Task 3–7)</h4>
                <div class="relative pr-2">
                    ${timelineHTML}
                </div>
            </div>
        </div>
    `;

    // Render Action Footer (Cross Verify)
    if (footer) {
        if (patient.has_booking) {
            footer.innerHTML = `
                <button onclick="crossVerifyBpjs('${patient.no_rawat}')" id="btn-verify-bpjs" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-3 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all shadow-md shadow-blue-500/20">
                    <i class="fas fa-shield-alt mr-1"></i> Verify dengan BPJS API
                </button>
                <button onclick="closeDetailPanel()" class="glass px-5 py-3 rounded-2xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Tutup</button>
            `;
        } else {
            footer.innerHTML = `
                <button onclick="closeDetailPanel()" class="glass px-5 py-3 rounded-2xl text-xs font-bold text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">Tutup</button>
            `;
        }
    }
}

function closeDetailPanel() {
    const panel = document.getElementById('slide-detail-panel');
    const overlay = document.getElementById('panel-overlay');

    if (panel) panel.classList.remove('open');
    if (overlay) {
        overlay.classList.add('opacity-0');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 400);
    }
}

function crossVerifyBpjs(noRawat) {
    const btn = document.getElementById('btn-verify-bpjs');
    if (!btn) return;
    const oldHTML = btn.innerHTML;

    btn.disabled = true;
    btn.innerHTML = `<i class="fas fa-circle-notch animate-spin mr-1"></i> Menghubungi BPJS...`;

    fetch(`/api/monitoring/verify/${encodeURIComponent(noRawat)}`)
        .then(res => res.json())
        .then(res => {
            btn.disabled = false;
            btn.innerHTML = oldHTML;

            if (res.success && res.data) {
                renderBpjsVerifiedTasks(res.data);
            } else {
                alert('Gagal mengambil data dari BPJS: ' + (res.metadata?.message || 'Error'));
            }
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHTML;
            alert('Terjadi kesalahan koneksi.');
        });
}

function renderBpjsVerifiedTasks(bpjsTasks) {
    const content = document.getElementById('panel-content');
    if (!content) return;

    let rows = '';
    (bpjsTasks || []).forEach(task => {
        const parsedWkt = parseJsDate(task.wakturs);
        rows += `
            <tr class="border-b border-slate-100/50 dark:border-slate-800/50">
                <td class="py-2 text-xs font-bold">Task ${task.taskid}</td>
                <td class="py-2 text-xs">${task.taskname}</td>
                <td class="py-2 text-xs text-right font-mono">${formatTimeOnly(parsedWkt)}</td>
            </tr>
        `;
    });

    const oldBox = document.getElementById('verified-bpjs-box');
    if (oldBox) oldBox.remove();

    const verifiedBox = document.createElement('div');
    verifiedBox.id = 'verified-bpjs-box';
    verifiedBox.className = "bg-emerald-500/5 border border-emerald-500/20 rounded-2xl p-5 mt-6 animate-in slide-in-from-bottom duration-300";
    verifiedBox.innerHTML = `
        <h5 class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 mb-3">
            <i class="fas fa-check-double"></i> Hasil Verifikasi Server Resmi BPJS
        </h5>
        <table class="w-full text-left">
            <thead>
                <tr class="text-[10px] text-slate-400 uppercase tracking-widest border-b border-slate-200/50 dark:border-slate-800/50 pb-2">
                    <th class="pb-1.5">Task</th>
                    <th class="pb-1.5">Nama Task</th>
                    <th class="pb-1.5 text-right">Waktu Server</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100/50 dark:divide-slate-800/50">
                ${rows || '<tr><td colspan="3" class="py-3 text-xs text-slate-400 text-center">Tidak ada data Task ID di server BPJS Kesehatan.</td></tr>'}
            </tbody>
        </table>
    `;
    content.appendChild(verifiedBox);
    verifiedBox.scrollIntoView({ behavior: 'smooth' });
}
