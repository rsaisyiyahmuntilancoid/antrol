<!-- Patients Registry List Table -->
<div class="glass rounded-[32px] overflow-hidden shadow-sm" id="patient-registry-card">
    <div
        class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h3 class="text-xl font-bold tracking-tight">Daftar Detail Waktu Kunjungan Pasien</h3>
            <p class="text-xs text-slate-400 mt-1">Daftar lengkap pasien BPJS beserta durasi antar task
                pelayanan</p>
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
                    <th class="px-4 py-4 text-center">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                @foreach ($analytics['patients'] as $p)
                <tr class="patient-row hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors group cursor-pointer"
                    onclick="showPatientDetail('{{ $p['no_rawat'] }}')" data-rawat="{{ $p['no_rawat'] }}"
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
                            <span class="text-[11px] text-slate-400 font-semibold mt-1">RM: {{
                                $p['no_rkm_medis'] }} &bull; Jam Reg: {{ $p['jam_reg'] }}</span>
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
                    <td class="px-4 py-3.5 text-center" onclick="event.stopPropagation()">
                        <button onclick="showPatientDetail('{{ $p['no_rawat'] }}')"
                            class="p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all shadow-md shadow-blue-500/10">
                            <i class="fas fa-search-plus text-xs"></i> Detail
                        </button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Pagination Controls -->
    <div class="px-8 py-5 border-t border-slate-200/50 dark:border-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/10"
        id="pagination-controls-bar">
        <div class="text-xs font-semibold text-slate-500">
            Menampilkan <span id="pagination-info-start"
                class="font-bold text-slate-700 dark:text-slate-300">1</span> - <span id="pagination-info-end"
                class="font-bold text-slate-700 dark:text-slate-300">25</span> dari <span
                id="pagination-info-total" class="font-bold text-slate-700 dark:text-slate-300">0</span> pasien
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <button onclick="changePage(1)" id="btn-page-first"
                class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
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
                class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                <i class="fas fa-angle-double-right"></i>
            </button>
        </div>
        <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-slate-400">Baris per halaman:</span>
            <select id="pagination-page-size" onchange="changePageSize(this.value)"
                class="bg-slate-100 dark:bg-slate-850 border-none rounded-xl px-2.5 py-1.5 text-xs font-bold outline-none text-slate-600 dark:text-slate-400">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="100">100</option>
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
