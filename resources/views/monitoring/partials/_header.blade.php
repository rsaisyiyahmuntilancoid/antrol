<!-- KOP SURAT (PRINT ONLY) -->
<div class="hidden print:block mb-8 bg-white text-black p-4">
    <div class="flex items-center gap-6 border-b-4 border-double border-slate-900 pb-4">
        <img src="{{ asset('logo-aisyiyah.png') }}" class="w-20 h-20 object-contain shrink-0" alt="Logo RSU Aisyiyah Muntilan">
        <div class="grow text-left">
            <h1 class="text-xl font-bold tracking-tight text-slate-900 uppercase leading-none">RUMAH SAKIT UMUM AISYIYAH MUNTILAN</h1>
            <p class="text-xs font-semibold text-slate-700 mt-1">Jln. KH A. Dahlan No. 24 Muntilan, Magelang 56414</p>
            <p class="text-xs font-semibold text-slate-700">Telp : (0293) 587372, 587723 (hunting)</p>
            <p class="text-xs font-semibold text-slate-700">Website : www.rsaisyiyah-muntilan.com</p>
        </div>
    </div>

    <!-- Printed Report Title -->
    <div class="mt-6 text-center">
        <h2 class="text-lg font-bold uppercase tracking-wide text-slate-900">Laporan Analisis Antrean Pelayanan & Waktu Tunggu</h2>
        <p class="text-xs font-bold text-slate-600 mt-1">Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->format('d-m-Y') }}</p>
    </div>
</div>

@if(session('warning'))
<div
    class="bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 p-4 rounded-2xl mb-6 font-semibold text-sm flex items-center gap-3">
    <i class="fas fa-exclamation-circle text-lg"></i>
    {{ session('warning') }}
</div>
@endif

<!-- Header Section -->
<div class="glass rounded-3xl p-8 mb-8 space-y-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
        <div>
            <h1 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                <span
                    class="bg-blue-600 p-2.5 rounded-2xl text-white text-lg flex items-center justify-center shadow-lg shadow-blue-500/20">
                    <i class="fas fa-heartbeat"></i>
                </span>
                Flow Analytics & Monitoring BPJS
            </h1>
            <p class="text-slate-500 dark:text-slate-400 mt-2 flex items-center gap-2">
                <span
                    class="inline-flex items-center gap-1 bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-400 px-2 py-1 rounded-full text-xs font-semibold">
                    <i class="fas fa-database"></i>
                    Sumber: BPJS (Cached)
                </span>
                Monitoring Waktu Pelayanan dan Task ID Pasien BPJS
            </p>
        </div>

        <!-- Tab Switcher -->
        <div
            class="flex bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl border border-slate-200/50 dark:border-slate-700/50">
            <button onclick="switchTab('simrs')" id="btn-tab-simrs"
                class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400">
                <i class="fas fa-database mr-2"></i>Dashboard SIMRS
            </button>
            <button onclick="switchTab('bpjs')" id="btn-tab-bpjs"
                class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                <i class="fas fa-globe mr-2"></i>Rapor Resmi BPJS
            </button>
        </div>
    </div>

    <!-- Filters Block -->
    <div
        class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-200/60 dark:border-slate-800/60">
        <!-- Date Filter (Shared/SIMRS) -->
        <div id="simrs-date-filter" class="flex flex-wrap items-center gap-3">
            <form method="GET" action="{{ route('monitoring.index') }}" class="flex items-center gap-3">
                <div
                    class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2">
                    <label class="text-xs font-bold text-slate-400 uppercase">From</label>
                    <input type="date" name="date_from" value="{{ $dateFrom }}"
                        class="bg-transparent border-none text-sm font-semibold focus:ring-0 outline-none text-slate-700 dark:text-slate-300">
                </div>
                <div
                    class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2">
                    <label class="text-xs font-bold text-slate-400 uppercase">To</label>
                    <input type="date" name="date_to" value="{{ $dateTo }}"
                        class="bg-transparent border-none text-sm font-semibold focus:ring-0 outline-none text-slate-700 dark:text-slate-300">
                </div>
                <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-blue-500/10 flex items-center gap-2">
                    <i class="fas fa-search"></i> Apply
                </button>
            </form>

            <div class="h-6 w-px bg-slate-200 dark:bg-slate-800 hidden md:block"></div>

            <div class="flex gap-2">
                <a href="{{ route('monitoring.index', ['date_from' => \Carbon\Carbon::parse($dateFrom)->subDay()->format('Y-m-d'), 'date_to' => \Carbon\Carbon::parse($dateTo)->subDay()->format('Y-m-d')]) }}"
                    class="glass px-3.5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    <i class="fas fa-chevron-left"></i>
                </a>
                <a href="{{ route('monitoring.index', ['date_from' => \Carbon\Carbon::parse($dateFrom)->addDay()->format('Y-m-d'), 'date_to' => \Carbon\Carbon::parse($dateTo)->addDay()->format('Y-m-d')]) }}"
                    class="glass px-3.5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    <i class="fas fa-chevron-right"></i>
                </a>
                <a href="{{ route('monitoring.index') }}"
                    class="glass px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                    Today
                </a>
            </div>
        </div>

        <!-- Dashboard BPJS Filters (Initially Hidden) -->
        <div id="bpjs-dashboard-filter" class="hidden flex-wrap items-center gap-3 w-full md:w-auto">
            <div class="flex items-center gap-3 w-full md:w-auto">
                <!-- Daily selector -->
                <div
                    class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2">
                    <input type="date" id="bpjs-date" value="{{ $dateFrom }}"
                        class="bg-transparent border-none text-sm font-semibold focus:ring-0 outline-none text-slate-700 dark:text-slate-300">
                </div>
                <button onclick="fetchBpjsDailyReport()"
                    class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-teal-500/10 flex items-center gap-2">
                    <i class="fas fa-calendar-day"></i> Rapor Harian
                </button>

                <div class="h-6 w-px bg-slate-200 dark:bg-slate-800"></div>

                <!-- Monthly selector -->
                <div class="flex items-center gap-2">
                    <select id="bpjs-month"
                        class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-teal-500 text-slate-700 dark:text-slate-300">
                        @for ($m = 1; $m <= 12; $m++) <option value="{{ sprintf('%02d', $m) }}" {{ $m==date('m')
                            ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                    </select>
                    <select id="bpjs-year"
                        class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-teal-500 text-slate-700 dark:text-slate-300">
                        @for ($y = date('Y') - 2; $y <= date('Y'); $y++) <option value="{{ $y }}" {{ $y==date('Y')
                            ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                    </select>
                    <button onclick="fetchBpjsMonthlyReport()"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-indigo-500/10 flex items-center gap-2">
                        <i class="fas fa-calendar-alt"></i> Rapor Bulanan
                    </button>
                </div>
            </div>
        </div>

        <!-- Quick Links & Sync -->
        <div class="flex items-center gap-2">
            <button id="btn-sync-today" onclick="triggerSyncToday()"
                class="glass px-4 py-2.5 rounded-xl text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 transition-all font-semibold flex items-center gap-2 border border-slate-200 dark:border-slate-700">
                <i class="fas fa-sync-alt" id="sync-today-icon"></i>
                <span>Sync Sekarang</span>
            </button>
            <button onclick="window.open('{{ route('monitoring.print') }}?date_from=' + dateFrom + '&date_to=' + dateTo, '_blank')"
                class="bg-rose-600 hover:bg-rose-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-rose-500/10 flex items-center gap-2"
                title="Cetak/Download PDF Laporan">
                <i class="fas fa-file-pdf"></i>
                <span>Cetak PDF</span>
            </button>
            <button onclick="window.location.reload()"
                class="glass p-2.5 rounded-xl text-slate-600 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors"
                title="Refresh Halaman">
                <i class="fas fa-redo"></i>
            </button>
        </div>
    </div>

    <!-- Redesign Date-Range Sync Banner -->
    <div id="range-sync-banner" class="mt-4">
        @if($analytics['days_with_registrations'] > 0 && $analytics['days_with_data'] < $analytics['days_with_registrations'])
            @if($analytics['days_with_data'] > 0)
                <!-- Warning/Yellow Banner for partial data -->
                <div class="p-4 bg-amber-500/10 dark:bg-amber-500/5 border border-amber-500/20 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="flex items-center gap-3">
                        <span class="bg-amber-500 p-2.5 rounded-xl text-white flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle"></i>
                        </span>
                        <div>
                            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Data Tidak Lengkap</h4>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Data hanya tersedia untuk <strong>{{ $analytics['days_with_data'] }}</strong> dari <strong>{{ $analytics['days_with_registrations'] }}</strong> hari kunjungan.
                                Ada <strong>{{ $analytics['days_with_registrations'] - $analytics['days_with_data'] }} hari</strong> yang belum disinkronkan.
                            </p>
                        </div>
                    </div>
                    <button id="btn-sync-missing" onclick="triggerRangeSync()" class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                        <i class="fas fa-sync-alt" id="sync-range-icon"></i>
                        <span>Sync Hari Kosong</span>
                    </button>
                </div>
            @else
                <!-- Completely empty state banner -->
                <div class="p-6 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-3xl flex flex-col items-center text-center justify-center gap-4">
                    <span class="bg-blue-500/10 p-4 rounded-full text-blue-600 dark:text-blue-400 text-2xl flex items-center justify-center">
                        <i class="fas fa-database"></i>
                    </span>
                    <div>
                        <h4 class="text-lg font-bold text-slate-800 dark:text-slate-200">Belum Ada Data BPJS</h4>
                        <p class="text-sm text-slate-500 mt-1 max-w-md">
                            Belum ada data kunjungan yang disinkronkan dari server BPJS Kesehatan untuk rentang tanggal
                            <strong>{{ $dateFrom }}</strong> s/d <strong>{{ $dateTo }}</strong>.
                        </p>
                    </div>
                    <button id="btn-sync-range-empty" onclick="triggerRangeSync()" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all flex items-center gap-2 shadow-lg shadow-blue-500/20">
                        <i class="fas fa-sync-alt" id="sync-range-empty-icon"></i>
                        <span>Jadwalkan Sync Range Ini</span>
                    </button>
                </div>
            @endif
        @endif
    </div>

    <!-- Queue Progress Polling Container -->
    <div id="sync-progress-container" class="hidden mt-4">
        <div class="glass p-4 rounded-2xl border border-blue-500/20">
            <div class="flex items-center justify-between mb-2">
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-300 flex items-center gap-2">
                    <i class="fas fa-sync-alt fa-spin text-blue-600 dark:text-blue-400"></i>
                    <span id="sync-progress-title">Sedang disinkronkan...</span>
                </span>
                <span id="sync-progress-text" class="text-sm font-semibold text-blue-600 dark:text-blue-400">0%</span>
            </div>
            <div class="w-full bg-slate-200 dark:bg-slate-700 h-2.5 rounded-full overflow-hidden">
                <div id="sync-progress-bar" class="bg-gradient-to-r from-blue-500 to-indigo-600 h-full rounded-full transition-all duration-500" style="width: 0%"></div>
            </div>
        </div>
    </div>
</div>
