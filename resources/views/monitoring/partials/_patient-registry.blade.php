@php
    $grandTotalPatients = (int) ($analytics['summary']['total_patients'] ?? count($analytics['patients']));
    $initialPatients = array_slice($analytics['patients'] ?? [], 0, 25);
    $initialShown = count($initialPatients);
@endphp

<!-- Patients Registry List Table -->
<div class="glass rounded-[32px] overflow-hidden shadow-sm" id="patient-registry-card">
    <div
        class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h3 class="text-xl font-bold tracking-tight">Daftar Detail Waktu Kunjungan Pasien</h3>
            <p class="text-xs text-slate-400 mt-1 flex flex-wrap items-center gap-1.5">
                <span>Daftar lengkap pasien BPJS beserta durasi antar task pelayanan</span> &bull;
                <span id="patient-table-info-badge" class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200/50 dark:border-blue-800/50 shadow-sm">
                    <i class="fas fa-list-ol text-blue-500"></i>
                    <span>Menampilkan <strong id="header-info-range">1 - {{ $initialShown }}</strong> dari <strong id="header-info-total">{{ number_format($grandTotalPatients, 0, ',', '.') }}</strong> data pasien</span>
                    <span id="header-info-grand" class="text-slate-400 font-normal hidden"></span>
                </span>
            </p>
        </div>

        <!-- Filters Control -->
        <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
            <!-- Auto-Refresh Toggle -->
            <label
                class="inline-flex items-center gap-2 cursor-pointer bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm select-none">
                <input type="checkbox" id="auto-refresh-toggle"
                    class="rounded text-blue-600 focus:ring-blue-500">
                <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 flex items-center gap-1">
                    <i class="fas fa-sync text-blue-500" id="auto-refresh-icon"></i> Refresh (30s)
                </span>
            </label>

            <input type="text" id="search-patient" placeholder="Cari nama / RM..."
                class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none w-full md:w-48 transition-all">

            <select id="filter-clinic"
                class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500 text-slate-600 dark:text-slate-400">
                <option value="">Semua Klinik</option>
                @foreach (array_keys($analytics['clinic_stats']) as $c)
                <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </select>

            <select id="filter-status"
                class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500 text-slate-600 dark:text-slate-400">
                <option value="">Semua Status</option>
                <option value="Lengkap (3,4,5,6,7)">Lengkap (3,4,5,6,7)</option>
                <option value="Lengkap (3,4,5,6) - Farmasi Belum Selesai">Lengkap (3,4,5,6) - Farmasi Belum Selesai</option>
                <option value="Task 3,4,5">Task 3,4,5</option>
                <option value="Task 3,4">Task 3,4</option>
                <option value="Task 3">Task 3</option>
                <option value="Belum Terkirim">Belum Terkirim</option>
                <option value="Batal">Batal</option>
                <option value="anomali">Hanya Anomali Data</option>
            </select>

            <!-- Kirim Batch Task Normal Button -->
            <button type="button" onclick="openNormalBatchModal()"
                class="glass px-3.5 py-2 rounded-xl text-xs font-bold hover:bg-emerald-50 text-emerald-600 dark:hover:bg-emerald-950/40 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-900/60 transition-all shadow-sm flex items-center gap-1.5"
                title="Kirim Batch Task ID (3 s/d 7) untuk pasien normal via web tanpa menunggu cron job">
                <i class="fas fa-paper-plane text-emerald-500"></i>
                <span>Kirim Batch Task (Normal)</span>
            </button>

            <!-- Kirim Task 99 Pasien Batal Button -->
            <button type="button" onclick="openCancelTasksModal()"
                class="glass px-3.5 py-2 rounded-xl text-xs font-bold hover:bg-rose-50 text-rose-600 dark:hover:bg-rose-950/40 dark:text-rose-400 border border-rose-200 dark:border-rose-900/60 transition-all shadow-sm flex items-center gap-1.5"
                title="Kirim Task 99 khusus untuk seluruh pasien yang batal pada tanggal tertentu">
                <i class="fas fa-user-slash text-rose-500"></i>
                <span>Kirim Task 99 Pasien Batal</span>
            </button>

            <!-- Export to CSV Button -->
            <button onclick="exportToCSV()"
                class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl px-4 py-2 text-sm font-bold flex items-center gap-1.5 transition-all shadow-md shadow-emerald-500/10">
                <i class="fas fa-file-csv"></i> Ekspor CSV
            </button>
        </div>
    </div>

    <div class="overflow-x-auto select-none">
        <table class="w-full text-left border-collapse text-sm" id="patients-table">
            <thead>
                <tr
                    class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40">
                    <th class="px-4 py-4">Pasien</th>
                    <th class="px-4 py-4">Poliklinik / Dokter</th>
                    <th class="px-4 py-4 text-center">Tunggu Poli</th>
                    <th class="px-4 py-4 text-center">Layan Poli</th>
                    <th class="px-4 py-4 text-center">Tunggu Farm.</th>
                    <th class="px-4 py-4 text-center">Layan Farm.</th>
                    <th class="px-4 py-4 text-center">Total RS</th>
                    <th class="px-4 py-4 text-center">Status</th>
                    <th class="px-4 py-4 text-center w-48 min-w-[185px]">Aksi</th>
                </tr>
            </thead>
            <tbody id="patients-table-body" class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach ($initialPatients as $p)
                <tr class="patient-row hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors group cursor-pointer"
                    onclick="showPatientDetail('{{ $p['kode_booking'] }}')" data-rawat="{{ $p['no_rawat'] }}"
                    data-name="{{ strtolower($p['nm_pasien']) }}" data-rm="{{ $p['no_rkm_medis'] }}"
                    data-clinic="{{ $p['nm_poli'] }}" data-status="{{ $p['status'] }}"
                    data-has-anomali="{{ $p['has_anomalies'] ? 'true' : 'false' }}"
                    data-anomalies="{{ implode(',', $p['anomalies']) }}"
                    data-kodebooking="{{ $p['kode_booking'] }}">
                    <td class="px-4 py-3.5">
                        <div class="flex flex-col whitespace-normal">
                            <span
                                class="font-bold text-slate-800 dark:text-slate-200 flex flex-wrap items-center gap-1.5 leading-snug">
                                {{ $p['nm_pasien'] }}
                                @if ($p['has_anomalies'])
                                <span
                                    class="inline-flex px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[9px] font-bold tracking-wide uppercase"
                                    title="Memiliki Anomali Data">
                                    <i class="fas fa-exclamation-triangle mr-0.5"></i> ANOMALI
                                </span>
                                @endif
                            </span>
                            <span class="text-[11px] text-slate-400 font-semibold mt-1 flex flex-wrap items-center gap-1.5">
                                <span>RM: {{ $p['no_rkm_medis'] }}</span> &bull;
                                <span>Booking: <code class="font-mono font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/60 px-1.5 py-0.5 rounded border border-blue-200/50 dark:border-blue-800/50 text-[10px]">{{ $p['kode_booking'] }}</code></span> &bull;
                                <span>Jam Reg: {{ $p['jam_reg'] }}</span> &bull;
                                <span class="inline-flex px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase {{ $p['sumber'] === 'Mobile JKN' ? 'bg-blue-100 dark:bg-blue-900/40 text-blue-600 dark:text-blue-400' : 'bg-amber-100 dark:bg-amber-900/40 text-amber-600 dark:text-amber-400' }}">
                                    {{ $p['sumber'] }}
                                </span>
                            </span>
                        </div>
                    </td>
                    <td class="px-4 py-3.5">
                        <div class="flex flex-col whitespace-normal">
                            <span class="text-slate-700 dark:text-slate-300 font-bold text-xs leading-snug">{{
                                $p['nm_poli'] }}</span>
                            <span class="text-[11px] text-slate-400 font-semibold mt-1 leading-snug">{{
                                $p['nm_dokter'] }}</span>
                        </div>
                    </td>
                    @php
                    $wtp = $p['durations']['waktu_tunggu_poli'] ?? null;
                    $wlp = $p['durations']['waktu_layan_poli'] ?? null;
                    $wtf = $p['durations']['waktu_tunggu_farmasi'] ?? null;
                    $wlf = $p['durations']['waktu_layan_farmasi'] ?? null;
                    $twr = $p['durations']['total_waktu_rs'] ?? null;
                    @endphp
                    <td class="px-4 py-3.5 text-center text-blue-600 dark:text-blue-400 font-semibold">
                        @if($wtp !== null)
                        @if($wtp < 0) <span
                            class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs"
                            title="Anomali: Durasi Negatif">{{ round($wtp, 1) }}m</span>
                            @else
                            {{ round($wtp, 1) }}m
                            @endif
                            @else
                            -
                            @endif
                    </td>
                    <td class="px-4 py-3.5 text-center text-emerald-600 dark:text-emerald-400 font-semibold">
                        @if($wlp !== null)
                        @if($wlp < 0) <span
                            class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs"
                            title="Anomali: Durasi Negatif">{{ round($wlp, 1) }}m</span>
                            @else
                            {{ round($wlp, 1) }}m
                            @endif
                            @else
                            -
                            @endif
                    </td>
                    <td class="px-4 py-3.5 text-center text-indigo-600 dark:text-indigo-400 font-semibold">
                        @if($wtf !== null)
                        @if($wtf < 0) <span
                            class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs"
                            title="Anomali: Durasi Negatif">{{ round($wtf, 1) }}m</span>
                            @else
                            {{ round($wtf, 1) }}m
                            @endif
                            @else
                            -
                            @endif
                    </td>
                    <td class="px-4 py-3.5 text-center text-teal-600 dark:text-teal-400 font-semibold">
                        @if($wlf !== null)
                        @if($wlf < 0) <span
                            class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs"
                            title="Anomali: Durasi Negatif">{{ round($wlf, 1) }}m</span>
                            @else
                            {{ round($wlf, 1) }}m
                            @endif
                            @else
                            -
                            @endif
                    </td>
                    <td class="px-4 py-3.5 text-center text-purple-600 dark:text-purple-400 font-bold">
                        @if($twr !== null)
                        @if($twr < 0) <span
                            class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs"
                            title="Anomali: Durasi Negatif">{{ round($twr, 1) }}m</span>
                            @else
                            {{ round($twr, 1) }}m
                            @endif
                            @else
                            -
                            @endif
                    </td>
                    <td class="px-4 py-3.5 text-center">
                        <div class="flex flex-col gap-1 items-center justify-center">
                            @if ($p['status'] === 'Batal' || $p['status'] === 'Tidak Hadir / Batal')
                            <span class="status-badge status-badge-rose" title="Batal / Task 99">
                                <i class="fas fa-user-times mr-1"></i>Batal
                                @if(!empty($p['waktu_batal']))
                                <span class="text-[9px] opacity-75 ml-0.5">({{ \Carbon\Carbon::parse($p['waktu_batal'])->format('H:i') }})</span>
                                @endif
                            </span>
                            @elseif ($p['status'] === 'Lengkap (3,4,5,6,7)')
                            <span class="status-badge status-badge-green" title="Lengkap (3,4,5,6,7)">Lengkap</span>
                            @elseif ($p['status'] === 'Lengkap (3,4,5,6) - Farmasi Belum Selesai')
                            <span class="status-badge status-badge-blue" title="Lengkap (3,4,5,6) - Farmasi Belum Selesai">Lengkap (3-6)</span>
                            @elseif ($p['status'] === 'Task 3,4,5')
                            <span class="status-badge status-badge-purple" title="Task 3,4,5">Task 3,4,5</span>
                            @elseif ($p['status'] === 'Task 3,4')
                            <span class="status-badge status-badge-amber" title="Task 3,4">Task 3,4</span>
                            @elseif ($p['status'] === 'Task 3')
                            <span class="status-badge status-badge-amber" title="Task 3">Task 3</span>
                            @elseif ($p['status'] === 'Belum Terkirim')
                            <span class="status-badge status-badge-slate"><i class="fas fa-clock mr-1"></i>Belum Terkirim</span>
                            @elseif (strpos($p['status'], 'Task ') === 0)
                            <span class="status-badge status-badge-slate">{{ $p['status'] }}</span>
                            @else
                            <span class="status-badge status-badge-slate">{{ $p['status'] }}</span>
                            @endif
                            <span class="sync-status badge {{ $p['sync_status'] === 'synced' ? 'badge-green' : 'badge-slate' }} text-[9px]">
                                {{ $p['sync_status'] === 'synced' ? 'BPJS' : 'Pending' }}
                            </span>
                        </div>
                    </td>
                    <td class="px-3 py-3 text-center" onclick="event.stopPropagation()">
                        <div class="inline-flex items-center gap-1.5 p-1 rounded-2xl bg-slate-100/90 dark:bg-slate-800/90 border border-slate-200/70 dark:border-slate-700/70 shadow-xs">
                            <button onclick="showPatientDetail('{{ $p['kode_booking'] }}')"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold text-blue-600 dark:text-blue-400 bg-white dark:bg-slate-900 hover:bg-blue-600 hover:text-white dark:hover:bg-blue-600 dark:hover:text-white border border-slate-200/80 dark:border-slate-700 shadow-xs transition-all duration-150 transform active:scale-95 group"
                                title="Lihat Detail Pelayanan Pasien">
                                <i class="fas fa-search-plus text-[11px] opacity-80 group-hover:opacity-100"></i>
                                <span>Detail</span>
                            </button>
                            <button onclick="sendPatientTasksManual('{{ $p['kode_booking'] }}', '{{ $p['no_rawat'] }}', this)"
                                class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl text-xs font-bold {{ $p['status'] === 'Batal' ? 'text-rose-600 dark:text-rose-400 bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-600 hover:text-white dark:hover:bg-rose-600 dark:hover:text-white border border-rose-200/80 dark:border-rose-900/60' : ($p['status'] === 'Lengkap (3,4,5,6,7)' ? 'text-slate-600 dark:text-slate-300 bg-white dark:bg-slate-900 hover:bg-slate-700 hover:text-white dark:hover:bg-slate-700 dark:hover:text-white border border-slate-200/80 dark:border-slate-700' : 'text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/50 hover:bg-emerald-600 hover:text-white dark:hover:bg-emerald-600 dark:hover:text-white border border-emerald-200/80 dark:border-emerald-900/60') }} shadow-xs transition-all duration-150 transform active:scale-95 group"
                                title="{{ $p['status'] === 'Batal' ? 'Kirim Task 99 (Batal)' : 'Kirim Manual Task ID Pasien' }}">
                                <i class="fas {{ $p['status'] === 'Batal' ? 'fa-user-slash' : ($p['status'] === 'Lengkap (3,4,5,6,7)' ? 'fa-redo-alt' : 'fa-paper-plane') }} text-[11px]"></i>
                                <span>{{ $p['status'] === 'Batal' ? 'Kirim 99' : ($p['status'] === 'Lengkap (3,4,5,6,7)' ? 'Ulang' : 'Kirim') }}</span>
                            </button>
                        </div>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div class="px-8 py-5 border-t border-slate-200/50 dark:border-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/10"
        id="pagination-controls-bar">
        <div class="text-xs font-semibold text-slate-500" id="pagination-info-text">
            Menampilkan baris <span id="pagination-info-start"
                class="font-bold text-slate-700 dark:text-slate-300">{{ $initialShown > 0 ? 1 : 0 }}</span> - <span id="pagination-info-end"
                class="font-bold text-slate-700 dark:text-slate-300">{{ $initialShown }}</span> dari <span
                id="pagination-info-total" class="font-bold text-slate-700 dark:text-slate-300">{{ number_format($grandTotalPatients, 0, ',', '.') }}</span> data pasien
            <span id="pagination-info-grand-total" class="text-slate-400 font-normal hidden"></span>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="changePage(1)" id="btn-page-first"
                class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                title="Halaman Pertama">
                <i class="fas fa-angle-double-left"></i>
            </button>
            <button onclick="changePage(currentPage - 1)" id="btn-page-prev"
                class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-angle-left"></i> Prev
            </button>
            <div id="pagination-pages" class="flex items-center gap-1.5">
                <!-- JS renders page buttons here -->
            </div>
            <button onclick="changePage(currentPage + 1)" id="btn-page-next"
                class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                Next <i class="fas fa-angle-right"></i>
            </button>
            <button onclick="changePage(totalPages)" id="btn-page-last"
                class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed"
                title="Halaman Terakhir">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-400">Tampilkan:</span>
            <select id="pagination-page-size" onchange="changePageSize(this.value)"
                class="bg-slate-100 dark:bg-slate-850 border border-slate-200/60 dark:border-slate-800 rounded-xl px-2.5 py-1.5 text-xs font-bold outline-none text-slate-600 dark:text-slate-400">
                <option value="10">10 data / hal</option>
                <option value="25" selected>25 data / hal</option>
                <option value="50">50 data / hal</option>
                <option value="100">100 data / hal</option>
            </select>
        </div>
    </div>

    <div id="no-patients-found"
        class="hidden px-8 py-20 text-center space-y-4 border-t border-slate-100 dark:border-slate-800">
        <div
            class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto text-slate-400">
            <i class="fas fa-users-slash text-3xl"></i>
        </div>
        <div>
            <h4 class="text-lg font-bold">Pasien Tidak Ditemukan</h4>
            <p class="text-sm text-slate-500">Silakan sesuaikan filter pencarian atau klinik Anda.</p>
        </div>
    </div>
</div>


<!-- Modal Khusus Kirim Task 99 Pasien Batal -->
<div id="modal-cancel-tasks" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-4xl w-full shadow-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden transform transition-all animate-fadeIn">
        <!-- Header Modal -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-rose-50/50 dark:bg-rose-950/20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-rose-100 dark:bg-rose-900/50 text-rose-600 dark:text-rose-400 flex items-center justify-center font-bold text-lg shadow-sm">
                    <i class="fas fa-user-slash"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Kirim Task 99 Pasien Batal</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Kirim Task 99 ke BPJS khusus pasien batal tanpa menunggu jadwal cron harian</p>
                </div>
            </div>
            <button type="button" onclick="closeCancelTasksModal()" class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body Modal -->
        <div class="p-6 space-y-6">
            <!-- Filter Tanggal & Action -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/60 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Pilih Tanggal:</span>
                    <input type="date" id="cancel-task-date-from" value="{{ $dateFrom ?? date('Y-m-d') }}"
                        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500 text-slate-700 dark:text-slate-200 shadow-sm"
                        title="Tanggal Awal">
                    <span class="text-xs font-bold text-slate-400">s/d</span>
                    <input type="date" id="cancel-task-date-to" value="{{ $dateTo ?? date('Y-m-d') }}"
                        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500 text-slate-700 dark:text-slate-200 shadow-sm"
                        title="Tanggal Akhir">
                    <button type="button" onclick="fetchCancelledPatientsPreview()" id="btn-check-cancelled"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fas fa-search text-xs"></i> Cek Pasien Batal
                    </button>
                </div>

                <!-- Stats Badges -->
                <div class="flex items-center gap-2 flex-wrap text-xs font-semibold">
                    <span class="px-3 py-1.5 rounded-xl bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Total: <strong id="modal-stat-total" class="font-bold">0</strong>
                    </span>
                    <span class="px-3 py-1.5 rounded-xl bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-400">
                        Belum Kirim: <strong id="modal-stat-belum" class="font-bold">0</strong>
                    </span>
                    <span class="px-3 py-1.5 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400">
                        Sudah Kirim: <strong id="modal-stat-sudah" class="font-bold">0</strong>
                    </span>
                </div>
            </div>

            <!-- Table Preview -->
            <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 font-bold sticky top-0 border-b border-slate-200 dark:border-slate-700/60 backdrop-blur-sm">
                            <tr>
                                <th class="px-4 py-3">Tgl Periksa</th>
                                <th class="px-4 py-3">No. Rawat & Booking</th>
                                <th class="px-4 py-3">Nama Pasien</th>
                                <th class="px-4 py-3">Poliklinik</th>
                                <th class="px-4 py-3 text-center">Status SIMRS</th>
                                <th class="px-4 py-3 text-center">Status Task 99</th>
                            </tr>
                        </thead>
                        <tbody id="cancel-tasks-tbody" class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-semibold">
                                    <i class="fas fa-calendar-day mr-1"></i> Silakan pilih tanggal dan klik <strong>Cek Pasien Batal</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Force Checkbox Note -->
            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" id="cancel-task-force" class="rounded text-rose-600 focus:ring-rose-500">
                    <span class="font-semibold">Kirim ulang juga pasien yang sudah berstatus terkirim (Force resend)</span>
                </label>
                <span class="text-[11px] text-slate-400"><i class="fas fa-info-circle mr-1"></i>Hanya memproses Task 99 tanpa menyentuh pasien lain</span>
            </div>
        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span id="cancel-task-status-text" class="text-xs font-semibold text-slate-500">Siap memproses data.</span>
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button type="button" onclick="closeCancelTasksModal()"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    Tutup
                </button>
                <button type="button" onclick="executeBatchCancelTasks()" id="btn-execute-cancel"
                    class="px-5 py-2.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-md shadow-rose-600/20 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane text-xs"></i>
                    <span>Kirim Semua Task 99 Sekarang</span>
                </button>
            </div>
        </div>
    </div>
</div>


<!-- Modal Khusus Kirim Batch Task Normal (Task 3-7) -->
<div id="modal-normal-batch-tasks" class="fixed inset-0 z-50 hidden overflow-y-auto bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white dark:bg-slate-900 rounded-3xl max-w-4xl w-full shadow-2xl border border-slate-200/80 dark:border-slate-800 overflow-hidden transform transition-all animate-fadeIn">
        <!-- Header Modal -->
        <div class="px-6 py-5 border-b border-slate-100 dark:border-slate-800/80 flex items-center justify-between bg-emerald-50/50 dark:bg-emerald-950/20">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-emerald-100 dark:bg-emerald-900/50 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold text-lg shadow-sm">
                    <i class="fas fa-paper-plane"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">Kirim Batch Task ID (Pasien Normal)</h3>
                    <p class="text-xs text-slate-500 dark:text-slate-400">Alternatif kirim massal Task ID 3 s/d 7 via Web langsung tanpa menunggu jadwal cron server</p>
                </div>
            </div>
            <button type="button" onclick="closeNormalBatchModal()" class="w-8 h-8 rounded-xl text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 flex items-center justify-center transition-all">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Body Modal -->
        <div class="p-6 space-y-6">
            <!-- Filter Tanggal & Action -->
            <div class="p-4 rounded-2xl bg-slate-50 dark:bg-slate-850/60 border border-slate-200/60 dark:border-slate-800 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-xs font-bold text-slate-700 dark:text-slate-300">Pilih Tanggal:</span>
                    <input type="date" id="normal-batch-date-from" value="{{ $dateFrom ?? date('Y-m-d') }}"
                        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500 text-slate-700 dark:text-slate-200 shadow-sm"
                        title="Tanggal Awal">
                    <span class="text-xs font-bold text-slate-400">s/d</span>
                    <input type="date" id="normal-batch-date-to" value="{{ $dateTo ?? date('Y-m-d') }}"
                        class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-1.5 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500 text-slate-700 dark:text-slate-200 shadow-sm"
                        title="Tanggal Akhir">
                    <button type="button" onclick="fetchNormalBatchPreview()" id="btn-check-normal-batch"
                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition-all flex items-center gap-1.5">
                        <i class="fas fa-search text-xs"></i> Cek Pasien Normal
                    </button>
                </div>

                <!-- Stats Badges -->
                <div class="flex items-center gap-2 flex-wrap text-xs font-semibold">
                    <span class="px-3 py-1.5 rounded-xl bg-slate-200/70 dark:bg-slate-800 text-slate-700 dark:text-slate-300">
                        Total Normal: <strong id="normal-stat-total" class="font-bold">0</strong>
                    </span>
                    <span class="px-3 py-1.5 rounded-xl bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400">
                        Belum Lengkap: <strong id="normal-stat-belum" class="font-bold">0</strong>
                    </span>
                    <span class="px-3 py-1.5 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400">
                        Sudah Lengkap: <strong id="normal-stat-lengkap" class="font-bold">0</strong>
                    </span>
                </div>
            </div>

            <!-- Progress Bar (Initially Hidden) -->
            <div id="normal-batch-progress-container" class="hidden p-4 rounded-2xl bg-emerald-50/50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/60 space-y-2">
                <div class="flex items-center justify-between text-xs font-bold text-slate-700 dark:text-slate-300">
                    <span id="normal-batch-progress-status" class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400">
                        <i class="fas fa-circle-notch fa-spin"></i> Sedang memproses...
                    </span>
                    <span id="normal-batch-progress-percent">0%</span>
                </div>
                <div class="w-full bg-slate-200 dark:bg-slate-800 rounded-full h-2.5 overflow-hidden">
                    <div id="normal-batch-progress-bar" class="bg-gradient-to-r from-emerald-500 to-teal-500 h-2.5 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
                    <span id="normal-batch-progress-counts">Proses: 0 / 0</span>
                    <span id="normal-batch-progress-stats">Sukses: 0 | Gagal: 0</span>
                </div>
            </div>

            <!-- Table Preview -->
            <div class="border border-slate-200/80 dark:border-slate-800 rounded-2xl overflow-hidden shadow-sm">
                <div class="max-h-72 overflow-y-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-100/80 dark:bg-slate-800/80 text-slate-600 dark:text-slate-400 font-bold sticky top-0 border-b border-slate-200 dark:border-slate-700/60 backdrop-blur-sm">
                            <tr>
                                <th class="px-4 py-3">Tgl Periksa</th>
                                <th class="px-4 py-3">No. Rawat & Booking</th>
                                <th class="px-4 py-3">Nama Pasien</th>
                                <th class="px-4 py-3">Poliklinik</th>
                                <th class="px-4 py-3 text-center">Status SIMRS</th>
                                <th class="px-4 py-3 text-center">Status Task Saat Ini</th>
                            </tr>
                        </thead>
                        <tbody id="normal-batch-tbody" class="divide-y divide-slate-100 dark:divide-slate-800 text-slate-700 dark:text-slate-300">
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-slate-400 font-semibold">
                                    <i class="fas fa-calendar-alt mr-1"></i> Silakan pilih tanggal dan klik <strong>Cek Pasien Normal</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Force Checkbox Note -->
            <div class="flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 flex-wrap gap-2">
                <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" id="normal-batch-force" class="rounded text-emerald-600 focus:ring-emerald-500">
                    <span class="font-semibold">Kirim ulang juga pasien yang task-nya sudah lengkap (Force resend)</span>
                </label>
                <span class="text-[11px] text-slate-400"><i class="fas fa-shield-alt mr-1"></i>Diproses aman bertahap via web chunking tanpa memicu timeout</span>
            </div>
        </div>

        <!-- Footer Modal -->
        <div class="px-6 py-4 border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-900/50 flex flex-col sm:flex-row items-center justify-between gap-3">
            <span id="normal-batch-status-text" class="text-xs font-semibold text-slate-500">Siap memproses pengiriman batch.</span>
            <div class="flex items-center gap-2 w-full sm:w-auto justify-end">
                <button type="button" onclick="closeNormalBatchModal()" id="btn-close-normal-batch"
                    class="px-4 py-2 rounded-xl text-xs font-bold text-slate-600 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    Tutup
                </button>
                <button type="button" onclick="startNormalBatchProcessing()" id="btn-execute-normal-batch"
                    class="px-5 py-2.5 rounded-xl text-xs font-bold bg-emerald-600 hover:bg-emerald-700 text-white shadow-md shadow-emerald-600/20 transition-all flex items-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fas fa-paper-plane text-xs"></i>
                    <span>Kirim Batch Task ID Sekarang</span>
                </button>
                <button type="button" onclick="stopNormalBatchProcessing()" id="btn-stop-normal-batch"
                    class="hidden px-4 py-2.5 rounded-xl text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-md transition-all flex items-center gap-1.5">
                    <i class="fas fa-stop text-xs"></i>
                    <span>Hentikan</span>
                </button>
            </div>
        </div>
    </div>
</div>
