@extends('layouts.main')

@section('title', 'Flow Analytics & Monitoring')

@section('content')
<div class="max-w-7xl mx-auto px-6 py-12 relative overflow-hidden">
    @if(session('warning'))
    <div class="bg-amber-500/10 border border-amber-500/20 text-amber-700 dark:text-amber-400 p-4 rounded-2xl mb-6 font-semibold text-sm flex items-center gap-3">
        <i class="fas fa-exclamation-circle text-lg"></i>
        {{ session('warning') }}
    </div>
    @endif

    <!-- Header Section -->
    <div class="glass rounded-3xl p-8 mb-8 space-y-6">
        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div>
                <h1 class="text-4xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-3">
                    <span class="bg-blue-600 p-2.5 rounded-2xl text-white text-lg flex items-center justify-center shadow-lg shadow-blue-500/20">
                        <i class="fas fa-chart-line"></i>
                    </span>
                    Flow Analytics & Monitoring
                </h1>
                <p class="text-slate-500 dark:text-slate-400 mt-2">
                    Monitoring Waktu Pelayanan dan Sinkronisasi Task ID Pasien BPJS
                </p>
            </div>

            <!-- Tab Switcher -->
            <div class="flex bg-slate-100 dark:bg-slate-800 p-1.5 rounded-2xl border border-slate-200/50 dark:border-slate-700/50">
                <button onclick="switchTab('simrs')" id="btn-tab-simrs" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400">
                    <i class="fas fa-database mr-2"></i>Dashboard SIMRS
                </button>
                <button onclick="switchTab('bpjs')" id="btn-tab-bpjs" class="px-5 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white">
                    <i class="fas fa-globe mr-2"></i>Rapor Resmi BPJS
                </button>
            </div>
        </div>

        <!-- Filters Block -->
        <div class="flex flex-wrap items-center justify-between gap-4 pt-4 border-t border-slate-200/60 dark:border-slate-800/60">
            <!-- Date Filter (Shared/SIMRS) -->
            <div id="simrs-date-filter" class="flex flex-wrap items-center gap-3">
                <form method="GET" action="{{ route('monitoring.index') }}" class="flex items-center gap-3">
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2">
                        <label class="text-xs font-bold text-slate-400 uppercase">From</label>
                        <input type="date" name="date_from" value="{{ $dateFrom }}" class="bg-transparent border-none text-sm font-semibold focus:ring-0 outline-none text-slate-700 dark:text-slate-300">
                    </div>
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2">
                        <label class="text-xs font-bold text-slate-400 uppercase">To</label>
                        <input type="date" name="date_to" value="{{ $dateTo }}" class="bg-transparent border-none text-sm font-semibold focus:ring-0 outline-none text-slate-700 dark:text-slate-300">
                    </div>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-5 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-blue-500/10 flex items-center gap-2">
                        <i class="fas fa-search"></i> Apply
                    </button>
                </form>

                <div class="h-6 w-px bg-slate-200 dark:bg-slate-800 hidden md:block"></div>

                <div class="flex gap-2">
                    <a href="{{ route('monitoring.index', ['date_from' => \Carbon\Carbon::parse($dateFrom)->subDay()->format('Y-m-d'), 'date_to' => \Carbon\Carbon::parse($dateTo)->subDay()->format('Y-m-d')]) }}" class="glass px-3.5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <a href="{{ route('monitoring.index', ['date_from' => \Carbon\Carbon::parse($dateFrom)->addDay()->format('Y-m-d'), 'date_to' => \Carbon\Carbon::parse($dateTo)->addDay()->format('Y-m-d')]) }}" class="glass px-3.5 py-2.5 rounded-xl text-sm font-semibold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <a href="{{ route('monitoring.index') }}" class="glass px-4 py-2.5 rounded-xl text-sm font-bold hover:bg-slate-100 dark:hover:bg-slate-800 transition-all">
                        Today
                    </a>
                </div>
            </div>

            <!-- Dashboard BPJS Filters (Initially Hidden) -->
            <div id="bpjs-dashboard-filter" class="hidden flex-wrap items-center gap-3 w-full md:w-auto">
                <div class="flex items-center gap-3 w-full md:w-auto">
                    <!-- Daily selector -->
                    <div class="flex items-center gap-2 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2">
                        <input type="date" id="bpjs-date" value="{{ $dateFrom }}" class="bg-transparent border-none text-sm font-semibold focus:ring-0 outline-none text-slate-700 dark:text-slate-300">
                    </div>
                    <button onclick="fetchBpjsDailyReport()" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-teal-500/10 flex items-center gap-2">
                        <i class="fas fa-calendar-day"></i> Rapor Harian
                    </button>

                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-800"></div>

                    <!-- Monthly selector -->
                    <div class="flex items-center gap-2">
                        <select id="bpjs-month" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-teal-500 text-slate-700 dark:text-slate-300">
                            @for ($m = 1; $m <= 12; $m++)
                                <option value="{{ sprintf('%02d', $m) }}" {{ $m == date('m') ? 'selected' : '' }}>{{ date('F', mktime(0, 0, 0, $m, 1)) }}</option>
                            @endfor
                        </select>
                        <select id="bpjs-year" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-teal-500 text-slate-700 dark:text-slate-300">
                            @for ($y = date('Y') - 2; $y <= date('Y'); $y++)
                                <option value="{{ $y }}" {{ $y == date('Y') ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>
                        <button onclick="fetchBpjsMonthlyReport()" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2.5 rounded-xl text-sm font-bold transition-all shadow-md shadow-indigo-500/10 flex items-center gap-2">
                            <i class="fas fa-calendar-alt"></i> Rapor Bulanan
                        </button>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="flex items-center gap-2">
                <button onclick="window.location.reload()" class="glass p-2.5 rounded-xl text-slate-600 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors" title="Refresh Page">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- TAB 1: INTERNAL SIMRS DASHBOARD -->
    <div id="tab-simrs-content" class="space-y-8 animate-in fade-in duration-300">
        <!-- KPI Cards Grid -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-6">
            <!-- Card 1: Total Patients -->
            <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-user-friends"></i>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Kunjungan</h3>
                <p class="text-3xl font-bold text-slate-900 dark:text-white mt-3">{{ $analytics['summary']['total_patients'] }}</p>
                <div class="mt-2 text-xs font-semibold flex items-center text-slate-500">
                    <span class="text-rose-500 font-bold mr-1">{{ $analytics['summary']['batal_patients'] }}</span> batal / tidak hadir
                </div>
            </div>

            <!-- Card 2: Median Tunggu Poli -->
            <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-clock"></i>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Tunggu Poli</h3>
                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-3">
                    {{ $analytics['global_stats']['waktu_tunggu_poli']['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                </p>
                <div class="mt-2 text-xs font-semibold text-slate-500">
                    Task 3 &rarr; Task 4 (Admisi-Poli)
                </div>
            </div>

            <!-- Card 3: Median Layan Poli -->
            <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-user-md"></i>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Layan Poli</h3>
                <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-3">
                    {{ $analytics['global_stats']['waktu_layan_poli']['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                </p>
                <div class="mt-2 text-xs font-semibold text-slate-500">
                    Task 4 &rarr; Task 5 (Pemeriksaan)
                </div>
            </div>

            <!-- Card 4: Median Tunggu Farmasi -->
            <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-prescription-bottle-alt"></i>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Tunggu Farmasi</h3>
                <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-3">
                    {{ $analytics['global_stats']['waktu_tunggu_farmasi']['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                </p>
                <div class="mt-2 text-xs font-semibold text-slate-500">
                    Task 5 &rarr; Task 6 (Buat Resep)
                </div>
            </div>

            <!-- Card 5: Median Total Waktu RS -->
            <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
                <div class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
                    <i class="fas fa-hospital"></i>
                </div>
                <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Total RS</h3>
                <p class="text-3xl font-bold text-purple-600 dark:text-purple-400 mt-3">
                    {{ $analytics['global_stats']['total_waktu_rs']['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                </p>
                <div class="mt-2 text-xs font-semibold text-slate-500">
                    Task 3 &rarr; Task 7 (Admisi-Selesai)
                </div>
            </div>
        </div>

        <!-- Anomaly Alert Banner -->
        @if ($analytics['anomalies']['total_anomalies'] > 0)
        <div class="bg-rose-500/10 dark:bg-rose-500/5 border border-rose-500/20 rounded-3xl p-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
            <div class="flex items-start gap-4">
                <div class="w-12 h-12 bg-rose-500 rounded-2xl flex items-center justify-center text-white text-xl shadow-lg shadow-rose-500/20 shrink-0">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div>
                    <h4 class="text-lg font-bold text-rose-600 dark:text-rose-400">Terdeteksi {{ $analytics['anomalies']['total_anomalies'] }} Anomali Data Kunjungan</h4>
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">
                        Ditemukan data transaksi antrean yang tidak wajar atau melanggar aturan sinkronisasi Task ID BPJS.
                    </p>
                    <div class="flex flex-wrap gap-x-4 gap-y-1.5 mt-3 text-xs font-semibold text-slate-600 dark:text-slate-400">
                        <span onclick="filterPatientByAnomaly('timestamp_buatan')" class="flex items-center cursor-pointer hover:text-rose-600 transition-colors"><span class="w-2.5 h-2.5 bg-rose-500 rounded-full mr-2"></span> {{ count($analytics['anomalies']['timestamp_buatan']) }} Timestamp Buatan</span>
                        <span onclick="filterPatientByAnomaly('durasi_negatif')" class="flex items-center cursor-pointer hover:text-amber-600 transition-colors"><span class="w-2.5 h-2.5 bg-amber-500 rounded-full mr-2"></span> {{ count($analytics['anomalies']['durasi_negatif']) }} Durasi Negatif</span>
                        <span onclick="filterPatientByAnomaly('farmasi_10_menit')" class="flex items-center cursor-pointer hover:text-teal-600 transition-colors"><span class="w-2.5 h-2.5 bg-teal-500 rounded-full mr-2"></span> {{ count($analytics['anomalies']['farmasi_10_menit']) }} Farmasi Tepat 10 Menit</span>
                        <span onclick="filterPatientByAnomaly('belum_terkirim')" class="flex items-center cursor-pointer hover:text-slate-600 dark:hover:text-white transition-colors"><span class="w-2.5 h-2.5 bg-slate-500 rounded-full mr-2"></span> {{ count($analytics['anomalies']['belum_terkirim']) }} Belum Terkirim</span>
                    </div>
                </div>
            </div>
            <button onclick="scrollToPatientTableWithAnomalies()" class="bg-rose-600 hover:bg-rose-700 text-white px-5 py-3 rounded-2xl text-xs font-bold uppercase tracking-wider transition-all shadow-md shadow-rose-500/20 shrink-0">
                Lihat Detail Anomali &rarr;
            </button>
        </div>
        @endif

        <!-- Visual Flow Timeline -->
        <div class="glass rounded-[32px] p-8">
            <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-8">
                <i class="fas fa-project-diagram text-blue-600"></i> Visualisasi Alur Layanan & Median Waktu Pelayanan
            </h2>

            <div class="grid grid-cols-1 md:grid-cols-9 gap-6 items-center">
                <!-- Step 3 -->
                <div class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-blue-500/20 mb-3">3</div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Check-in</h4>
                    <p class="text-[10px] text-slate-400 font-semibold mt-1">Admisi Selesai</p>
                </div>
                <!-- Connector 3->4 -->
                <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
                    <div class="h-0.5 w-full bg-blue-500/20 hidden md:block"></div>
                    <span class="flow-duration px-3 py-1.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-bold text-xs shadow-sm mt-2 md:mt-0">
                        {{ $analytics['global_stats']['waktu_tunggu_poli']['median'] }} m
                    </span>
                    <span class="text-[9px] text-slate-400 font-semibold mt-1">Tunggu Poli</span>
                </div>

                <!-- Step 4 -->
                <div class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-indigo-500/20 mb-3">4</div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Mulai Poli</h4>
                    <p class="text-[10px] text-slate-400 font-semibold mt-1">Perawat Masuk</p>
                </div>
                <!-- Connector 4->5 -->
                <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
                    <div class="h-0.5 w-full bg-emerald-500/20 hidden md:block"></div>
                    <span class="flow-duration px-3 py-1.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 font-bold text-xs shadow-sm mt-2 md:mt-0">
                        {{ $analytics['global_stats']['waktu_layan_poli']['median'] }} m
                    </span>
                    <span class="text-[9px] text-slate-400 font-semibold mt-1">Layanan Poli</span>
                </div>

                <!-- Step 5 -->
                <div class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-emerald-500/20 mb-3">5</div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Selesai Dokter</h4>
                    <p class="text-[10px] text-slate-400 font-semibold mt-1">Pemeriksaan Done</p>
                </div>
                <!-- Connector 5->6 -->
                <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
                    <div class="h-0.5 w-full bg-indigo-500/20 hidden md:block"></div>
                    <span class="flow-duration px-3 py-1.5 rounded-full bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-bold text-xs shadow-sm mt-2 md:mt-0">
                        {{ $analytics['global_stats']['waktu_tunggu_farmasi']['median'] }} m
                    </span>
                    <span class="text-[9px] text-slate-400 font-semibold mt-1">Tunggu Farmasi</span>
                </div>

                <!-- Step 6 -->
                <div class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-purple-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-purple-500/20 mb-3">6</div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Mulai Farmasi</h4>
                    <p class="text-[10px] text-slate-400 font-semibold mt-1">Input Resep</p>
                </div>
                <!-- Connector 6->7 -->
                <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
                    <div class="h-0.5 w-full bg-purple-500/20 hidden md:block"></div>
                    <span class="flow-duration px-3 py-1.5 rounded-full bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300 font-bold text-xs shadow-sm mt-2 md:mt-0">
                        {{ $analytics['global_stats']['waktu_layan_farmasi']['median'] }} m
                    </span>
                    <span class="text-[9px] text-slate-400 font-semibold mt-1">Layanan Obat</span>
                </div>

                <!-- Step 7 -->
                <div class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
                    <div class="w-10 h-10 rounded-full bg-slate-900 dark:bg-white dark:text-slate-900 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-slate-500/10 mb-3">7</div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Selesai</h4>
                    <p class="text-[10px] text-slate-400 font-semibold mt-1">Serah Obat</p>
                </div>
            </div>
        </div>

        <!-- Charts Grid Row -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Left Chart: Clinic Performance (2 cols width) -->
            <div class="glass p-8 rounded-[32px] lg:col-span-2 shadow-sm">
                <h3 class="text-lg font-bold tracking-tight mb-6 flex items-center gap-2">
                    <i class="fas fa-chart-bar text-blue-600"></i> Kinerja Waktu Layanan & Tunggu per Poliklinik (Menit)
                </h3>
                <div class="relative min-h-[300px]">
                    <canvas id="clinicChart"></canvas>
                </div>
            </div>

            <!-- Right Chart: Flow Completeness (1 col width) -->
            <div class="glass p-8 rounded-[32px] lg:col-span-1 shadow-sm flex flex-col">
                <h3 class="text-lg font-bold tracking-tight mb-6 flex items-center gap-2">
                    <i class="fas fa-chart-pie text-indigo-600"></i> Kelengkapan Flow Kunjungan
                </h3>
                <div class="relative flex-grow flex items-center justify-center min-h-[220px]">
                    <canvas id="statusChart"></canvas>
                </div>
                <div class="mt-4 grid grid-cols-2 gap-2 text-xs font-semibold text-slate-500">
                    <div class="flex items-center"><span class="w-3 h-3 bg-emerald-500 rounded-full mr-2"></span> Lengkap 3-7</div>
                    <div class="flex items-center"><span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span> Lengkap 3-6</div>
                    <div class="flex items-center"><span class="w-3 h-3 bg-indigo-400 rounded-full mr-2"></span> Lengkap 3-5</div>
                    <div class="flex items-center"><span class="w-3 h-3 bg-amber-500 rounded-full mr-2"></span> Belum Lengkap</div>
                </div>
            </div>
        </div>

        <!-- Summary Tables per Clinic & Doctor -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Clinics Summary Table -->
            <div class="glass rounded-[32px] overflow-hidden shadow-sm">
                <div class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold tracking-tight flex items-center gap-2">
                        <i class="fas fa-clinic-medical text-blue-600"></i> Ringkasan Kinerja Poliklinik
                    </h3>
                </div>
                <div class="overflow-x-auto max-h-[320px] overflow-y-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40">
                                <th class="px-6 py-4">Poliklinik</th>
                                <th class="px-6 py-4 text-center">Pasien</th>
                                <th class="px-6 py-4 text-center">Med. Tunggu</th>
                                <th class="px-6 py-4 text-center">Med. Layan</th>
                                <th class="px-6 py-4 text-center">Med. Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($analytics['clinic_stats'] as $clinic => $stats)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors font-medium">
                                    <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">{{ $clinic }}</td>
                                    <td class="px-6 py-4 text-center">{{ $stats['patient_count'] }}</td>
                                    <td class="px-6 py-4 text-center text-blue-600 dark:text-blue-400 font-semibold">{{ $stats['waktu_tunggu_poli']['median'] }}m</td>
                                    <td class="px-6 py-4 text-center text-emerald-600 dark:text-emerald-400 font-semibold">{{ $stats['waktu_layan_poli']['median'] }}m</td>
                                    <td class="px-6 py-4 text-center text-purple-600 dark:text-purple-400 font-bold">{{ $stats['total_waktu_rs']['median'] }}m</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Doctors Load Summary Table -->
            <div class="glass rounded-[32px] overflow-hidden shadow-sm">
                <div class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex justify-between items-center">
                    <h3 class="text-lg font-bold tracking-tight flex items-center gap-2">
                        <i class="fas fa-user-md text-emerald-600"></i> Beban Kerja & Median Durasi Dokter
                    </h3>
                </div>
                <div class="overflow-x-auto max-h-[320px] overflow-y-auto">
                    <table class="w-full text-left border-collapse text-sm">
                        <thead>
                            <tr class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40">
                                <th class="px-6 py-4">Nama Dokter</th>
                                <th class="px-6 py-4 text-center">Pasien</th>
                                <th class="px-6 py-4 text-center">Med. Layan Poli</th>
                                <th class="px-6 py-4 text-center">Med. Total RS</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($analytics['doctor_stats'] as $doctor => $stats)
                                <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors font-medium">
                                    <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">{{ $doctor }}</td>
                                    <td class="px-6 py-4 text-center">{{ $stats['patient_count'] }}</td>
                                    <td class="px-6 py-4 text-center text-emerald-600 dark:text-emerald-400 font-semibold">{{ $stats['waktu_layan_poli']['median'] }}m</td>
                                    <td class="px-6 py-4 text-center text-purple-600 dark:text-purple-400 font-bold">{{ $stats['total_waktu_rs']['median'] }}m</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Patients Registry List Table -->
        <div class="glass rounded-[32px] overflow-hidden shadow-sm" id="patient-registry-card">
            <div class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <h3 class="text-xl font-bold tracking-tight">Daftar Detail Waktu Kunjungan Pasien</h3>
                    <p class="text-xs text-slate-400 mt-1">Daftar lengkap pasien BPJS beserta durasi antar task pelayanan</p>
                </div>

                <!-- Filters Control -->
                <div class="flex flex-wrap items-center gap-3 w-full md:w-auto">
                    <!-- Auto-Refresh Toggle -->
                    <label class="inline-flex items-center gap-2 cursor-pointer bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm select-none">
                        <input type="checkbox" id="auto-refresh-toggle" class="rounded text-blue-600 focus:ring-blue-500">
                        <span class="text-xs font-semibold text-slate-600 dark:text-slate-400 flex items-center gap-1">
                            <i class="fas fa-sync text-blue-500" id="auto-refresh-icon"></i> Refresh (30s)
                        </span>
                    </label>

                    <input type="text" id="search-patient" placeholder="Cari nama / RM..." class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-4 py-2 text-sm focus:ring-2 focus:ring-blue-500 outline-none w-full md:w-48 transition-all">
                    
                    <select id="filter-clinic" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500 text-slate-600 dark:text-slate-400">
                        <option value="">Semua Klinik</option>
                        @foreach (array_keys($analytics['clinic_stats']) as $c)
                            <option value="{{ $c }}">{{ $c }}</option>
                        @endforeach
                    </select>

                    <select id="filter-status" class="bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-xl px-3 py-2 text-sm font-semibold outline-none focus:ring-2 focus:ring-blue-500 text-slate-600 dark:text-slate-400">
                        <option value="">Semua Status</option>
                        <option value="Lengkap (3,4,5,6,7)">Lengkap 3-7</option>
                        <option value="Lengkap (3,4,5,6)">Lengkap 3-6</option>
                        <option value="Lengkap (3,4,5)">Lengkap 3-5</option>
                        <option value="Belum Lengkap">Belum Lengkap</option>
                        <option value="Tidak Hadir / Batal">Tidak Hadir / Batal</option>
                        <option value="anomali">Hanya Anomali Data</option>
                    </select>

                    <!-- Export to CSV Button -->
                    <button onclick="exportToCSV()" class="bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl px-4 py-2 text-sm font-bold flex items-center gap-1.5 transition-all shadow-md shadow-emerald-500/10">
                        <i class="fas fa-file-csv"></i> Ekspor CSV
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full min-w-[1200px] text-left border-collapse text-sm" id="patients-table">
                    <thead>
                        <tr class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40">
                            <th class="px-8 py-4 w-[280px]">Pasien</th>
                            <th class="px-8 py-4 w-[260px]">Poliklinik / Dokter</th>
                            <th class="px-8 py-4 text-center w-[120px]">Tunggu Poli</th>
                            <th class="px-8 py-4 text-center w-[120px]">Layan Poli</th>
                            <th class="px-8 py-4 text-center w-[120px]">Tunggu Farm.</th>
                            <th class="px-8 py-4 text-center w-[120px]">Total RS</th>
                            <th class="px-8 py-4 text-center w-[130px]">Sync BPJS</th>
                            <th class="px-8 py-4 text-center w-[100px]">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-medium">
                        @foreach ($analytics['patients'] as $p)
                            <tr class="patient-row hover:bg-blue-50/30 dark:hover:bg-blue-900/10 transition-colors group cursor-pointer" 
                                onclick="showPatientDetail('{{ $p['no_rawat'] }}')"
                                data-name="{{ strtolower($p['nm_pasien']) }}" 
                                data-rm="{{ $p['no_rkm_medis'] }}"
                                data-clinic="{{ $p['nm_poli'] }}"
                                data-status="{{ $p['status'] }}"
                                data-has-anomali="{{ $p['has_anomalies'] ? 'true' : 'false' }}"
                                data-anomalies="{{ implode(',', $p['anomalies']) }}">
                                <td class="px-8 py-5">
                                    <div class="flex flex-col whitespace-normal">
                                        <span class="font-bold text-slate-800 dark:text-slate-200 flex flex-wrap items-center gap-1.5 leading-snug">
                                            {{ $p['nm_pasien'] }}
                                            @if ($p['has_anomalies'])
                                                <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 text-[9px] font-bold tracking-wide uppercase" title="Memiliki Anomali Data">
                                                    <i class="fas fa-exclamation-triangle mr-0.5"></i> ANOMALI
                                                </span>
                                            @endif
                                        </span>
                                        <span class="text-[11px] text-slate-400 font-semibold mt-1">RM: {{ $p['no_rkm_medis'] }} &bull; Jam Reg: {{ $p['jam_reg'] }}</span>
                                    </div>
                                </td>
                                <td class="px-8 py-5">
                    <div class="flex flex-col whitespace-normal">
                                        <span class="text-slate-700 dark:text-slate-300 font-bold text-xs leading-snug">{{ $p['nm_poli'] }}</span>
                                        <span class="text-[11px] text-slate-400 font-semibold mt-1 leading-snug">{{ $p['nm_dokter'] }}</span>
                                    </div>
                                </td>
                                 @php
                                     $wtp = $p['durations']['waktu_tunggu_poli'];
                                     $wlp = $p['durations']['waktu_layan_poli'];
                                     $wtf = $p['durations']['waktu_tunggu_farmasi'];
                                     $twr = $p['durations']['total_waktu_rs'];
                                 @endphp
                                 <td class="px-8 py-5 text-center text-blue-600 dark:text-blue-400 font-semibold">
                                     @if($wtp !== null)
                                         @if($wtp < 0)
                                             <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs" title="Anomali: Durasi Negatif">{{ round($wtp, 1) }}m</span>
                                         @else
                                             {{ round($wtp, 1) }}m
                                         @endif
                                     @else
                                         -
                                     @endif
                                 </td>
                                 <td class="px-8 py-5 text-center text-emerald-600 dark:text-emerald-400 font-semibold">
                                     @if($wlp !== null)
                                         @if($wlp < 0)
                                             <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs" title="Anomali: Durasi Negatif">{{ round($wlp, 1) }}m</span>
                                         @else
                                             {{ round($wlp, 1) }}m
                                         @endif
                                     @else
                                         -
                                     @endif
                                 </td>
                                 <td class="px-8 py-5 text-center text-indigo-600 dark:text-indigo-400 font-semibold">
                                     @if($wtf !== null)
                                         @if($wtf < 0)
                                             <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs" title="Anomali: Durasi Negatif">{{ round($wtf, 1) }}m</span>
                                         @else
                                             {{ round($wtf, 1) }}m
                                         @endif
                                     @else
                                         -
                                     @endif
                                 </td>
                                 <td class="px-8 py-5 text-center text-purple-600 dark:text-purple-400 font-bold">
                                     @if($twr !== null)
                                         @if($twr < 0)
                                             <span class="inline-flex px-1.5 py-0.5 rounded bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400 font-bold text-xs" title="Anomali: Durasi Negatif">{{ round($twr, 1) }}m</span>
                                         @else
                                             {{ round($twr, 1) }}m
                                         @endif
                                     @else
                                         -
                                     @endif
                                 </td>
                                <td class="px-8 py-5 text-center">
                                    @if ($p['status'] === 'Tidak Hadir / Batal')
                                        <span class="px-2 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider bg-rose-100 text-rose-700 dark:bg-rose-500/20 dark:text-rose-400">BATAL</span>
                                    @elseif ($p['has_booking'])
                                        @php
                                            $completeTask = 0;
                                            $sentArray = array_filter($p['timestamps_sent']);
                                            $completeTask = count($sentArray);
                                        @endphp
                                        <div class="flex flex-col items-center gap-1">
                                            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 flex items-center gap-1">
                                                <i class="fas fa-check-circle text-emerald-500"></i> {{ $completeTask }}/5 Task
                                            </span>
                                            <div class="w-12 h-1 bg-slate-100 dark:bg-slate-800 rounded-full overflow-hidden">
                                                <div class="h-full bg-emerald-500 rounded-full" style="width: {{ ($completeTask / 5) * 100 }}%"></div>
                                            </div>
                                        </div>
                                    @else
                                        <span class="px-2 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-400">NON-MJKN</span>
                                    @endif
                                </td>
                                <td class="px-8 py-5 text-center" onclick="event.stopPropagation()">
                                    <button onclick="showPatientDetail('{{ $p['no_rawat'] }}')" class="p-2 bg-blue-600 text-white rounded-xl hover:bg-blue-700 transition-all shadow-md shadow-blue-500/10">
                                        <i class="fas fa-search-plus text-xs"></i> Detail
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination Controls -->
            <div class="px-8 py-5 border-t border-slate-200/50 dark:border-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/10" id="pagination-controls-bar">
                <div class="text-xs font-semibold text-slate-500">
                    Menampilkan <span id="pagination-info-start" class="font-bold text-slate-700 dark:text-slate-300">1</span> - <span id="pagination-info-end" class="font-bold text-slate-700 dark:text-slate-300">25</span> dari <span id="pagination-info-total" class="font-bold text-slate-700 dark:text-slate-300">0</span> pasien
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <button onclick="changePage(1)" id="btn-page-first" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-double-left"></i>
                    </button>
                    <button onclick="changePage(currentPage - 1)" id="btn-page-prev" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-left"></i> Prev
                    </button>
                    <div id="pagination-pages" class="flex items-center gap-1.5">
                        <!-- JS renders page buttons here -->
                    </div>
                    <button onclick="changePage(currentPage + 1)" id="btn-page-next" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        Next <i class="fas fa-angle-right"></i>
                    </button>
                    <button onclick="changePage(totalPages)" id="btn-page-last" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                        <i class="fas fa-angle-double-right"></i>
                    </button>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs font-semibold text-slate-400">Baris per halaman:</span>
                    <select id="pagination-page-size" onchange="changePageSize(this.value)" class="bg-slate-100 dark:bg-slate-850 border-none rounded-xl px-2.5 py-1.5 text-xs font-bold outline-none text-slate-600 dark:text-slate-400">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
            </div>

            <div id="no-patients-found" class="hidden px-8 py-20 text-center space-y-4 border-t border-slate-100 dark:border-slate-800">
                <div class="w-20 h-20 bg-slate-100 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto text-slate-400">
                    <i class="fas fa-users-slash text-3xl"></i>
                </div>
                <div>
                    <h4 class="text-lg font-bold">Pasien Tidak Ditemukan</h4>
                    <p class="text-sm text-slate-500">Silakan sesuaikan filter pencarian atau klinik Anda.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB 2: OFFICIAL BPJS DASHBOARD REPORT -->
    <div id="tab-bpjs-content" class="hidden space-y-8 animate-in fade-in duration-300">
        <div class="glass p-8 rounded-[32px] min-h-[400px]">
            <h2 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-6">
                <i class="fas fa-globe text-teal-600"></i> Rapor Resmi Kinerja Waktu Tunggu Server BPJS Kesehatan
            </h2>
            <p class="text-slate-500 dark:text-slate-400 text-sm max-w-2xl mb-8">
                Data di bawah ini ditarik secara real-time langsung dari web service BPJS Kesehatan (Dashboard Waktu Antrean). Ini adalah nilai agregat resmi yang diakui oleh BPJS.
            </p>

            <div id="bpjs-report-loading" class="hidden flex-col items-center justify-center py-20 space-y-4">
                <div class="w-12 h-12 border-4 border-teal-600/20 border-t-teal-600 rounded-full animate-spin"></div>
                <p class="text-sm font-semibold text-slate-500">Menghubungi server BPJS Kesehatan...</p>
            </div>

            <div id="bpjs-report-empty" class="flex flex-col items-center justify-center py-20 text-center space-y-4">
                <div class="w-24 h-24 bg-teal-50 dark:bg-teal-500/10 rounded-[35px] flex items-center justify-center text-teal-600 text-4xl shadow-xl shadow-teal-500/5">
                    <i class="fas fa-network-wired"></i>
                </div>
                <div>
                    <h4 class="text-xl font-bold">Menunggu Permintaan Data</h4>
                    <p class="text-sm text-slate-500 mt-2 max-w-sm">
                        Gunakan tombol filter di atas ("Rapor Harian" atau "Rapor Bulanan") untuk memicu penarikan data dari API BPJS.
                    </p>
                </div>
            </div>

            <div id="bpjs-report-container" class="hidden space-y-8">
                <!-- Aggregate Stats Cards (Premium HSL Style) -->
                <div class="grid grid-cols-1 md:grid-cols-5 gap-6">
                    <!-- Card 1: Total Queue -->
                    <div class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-teal-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Total Antrean Resmi</span>
                                <p class="text-3xl font-extrabold text-teal-600 dark:text-teal-400 mt-2 leading-none" id="bpjs-stat-queues">0</p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center text-xs">
                                <i class="fas fa-users"></i>
                            </span>
                        </div>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Terverifikasi pada server BPJS</p>
                    </div>

                    <!-- Card 2: Tunggu Poli -->
                    <div class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-blue-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata Tunggu Poli</span>
                                <p class="text-3xl font-extrabold text-blue-600 dark:text-blue-400 mt-2 leading-none" id="bpjs-stat-wait-poli">0.0m</p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center text-xs">
                                <i class="fas fa-clock"></i>
                            </span>
                        </div>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Task 3: Menunggu panggilan dokter</p>
                    </div>

                    <!-- Card 3: Layan Poli -->
                    <div class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-emerald-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata Layan Poli</span>
                                <p class="text-3xl font-extrabold text-emerald-600 dark:text-emerald-400 mt-2 leading-none" id="bpjs-stat-poli">0.0m</p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center text-xs">
                                <i class="fas fa-stethoscope"></i>
                            </span>
                        </div>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Task 4: Pemeriksaan dokter</p>
                    </div>

                    <!-- Card 4: Layan Farmasi -->
                    <div class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-purple-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata Layan Farmasi</span>
                                <p class="text-3xl font-extrabold text-purple-600 dark:text-purple-400 mt-2 leading-none" id="bpjs-stat-farmasi">0.0m</p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-purple-500/10 text-purple-600 dark:text-purple-400 flex items-center justify-center text-xs">
                                <i class="fas fa-capsules"></i>
                            </span>
                        </div>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Task 6: Penyiapan obat resep</p>
                    </div>

                    <!-- Card 5: Rerata Total Waktu -->
                    <div class="bg-slate-50/30 dark:bg-slate-900/20 border-l-4 border-l-amber-500 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl shadow-sm hover:scale-[1.02] transition-transform duration-300">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-widest">Rerata Total Pelayanan</span>
                                <p class="text-3xl font-extrabold text-amber-600 dark:text-amber-400 mt-2 leading-none" id="bpjs-stat-total-time">0.0m</p>
                            </div>
                            <span class="w-8 h-8 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-xs">
                                <i class="fas fa-history"></i>
                            </span>
                        </div>
                        <p class="text-[10px] font-medium text-slate-400 dark:text-slate-500 mt-3.5">Total akumulasi Task 1 s/d 6</p>
                    </div>
                </div>

                <!-- Quick Insights Row -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6" id="bpjs-insights-row">
                    <!-- Insight 1: Highest Queue -->
                    <div class="bg-teal-500/5 border border-teal-500/10 dark:border-teal-500/20 p-4 rounded-2xl flex items-center gap-4">
                        <span class="w-10 h-10 rounded-xl bg-teal-500/10 text-teal-600 dark:text-teal-400 flex items-center justify-center text-sm shrink-0">
                            <i class="fas fa-fire"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Poliklinik Terpadat</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200 mt-1" id="bpjs-insight-busiest">-</p>
                        </div>
                    </div>
                    <!-- Insight 2: Longest Wait -->
                    <div class="bg-amber-500/5 border border-amber-500/10 dark:border-amber-500/20 p-4 rounded-2xl flex items-center gap-4">
                        <span class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-600 dark:text-amber-400 flex items-center justify-center text-sm shrink-0">
                            <i class="fas fa-hourglass-half"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Antrean Poli Terlama</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200 mt-1" id="bpjs-insight-longest-wait">-</p>
                        </div>
                    </div>
                    <!-- Insight 3: Longest Layan -->
                    <div class="bg-rose-500/5 border border-rose-500/10 dark:border-rose-500/20 p-4 rounded-2xl flex items-center gap-4">
                        <span class="w-10 h-10 rounded-xl bg-rose-500/10 text-rose-600 dark:text-rose-400 flex items-center justify-center text-sm shrink-0">
                            <i class="fas fa-user-md"></i>
                        </span>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Pelayanan Dokter Terlama</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-200 mt-1" id="bpjs-insight-longest-layan">-</p>
                        </div>
                    </div>
                </div>

                <!-- BPJS Charts Row -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6" id="bpjs-charts-row">
                    <!-- Chart 1: Queue Volume per Poliklinik -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 p-6 rounded-3xl shadow-sm">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-4">Volume Antrean per Poliklinik (Top 7)</h4>
                        <div class="h-64 relative">
                            <canvas id="bpjs-chart-volume"></canvas>
                        </div>
                    </div>
                    <!-- Chart 2: Wait vs Service Times -->
                    <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 p-6 rounded-3xl shadow-sm">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200 text-xs mb-4">Perbandingan Waktu Tunggu & Layan Dokter (Top 7 Menit)</h4>
                        <div class="h-64 relative">
                            <canvas id="bpjs-chart-times"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Detailed Table -->
                <div class="bg-white dark:bg-slate-900 border border-slate-200/50 dark:border-slate-800 rounded-3xl overflow-hidden shadow-sm">
                    <div class="px-8 py-5 bg-slate-50/50 dark:bg-slate-800/10 border-b border-slate-200/40 dark:border-slate-800/40 flex flex-col md:flex-row justify-between items-center gap-4">
                        <h4 class="font-bold text-slate-800 dark:text-slate-200 text-sm">Rapor Detail Waktu per Poliklinik (Kalkulasi Server BPJS)</h4>
                        <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto">
                            <!-- Search input -->
                            <div class="relative w-full sm:w-64">
                                <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400">
                                    <i class="fas fa-search text-xs"></i>
                                </span>
                                <input type="text" id="bpjs-search-input" onkeyup="handleBpjsSearch()" placeholder="Cari poliklinik atau tanggal..." class="w-full pl-9 pr-4 py-2 text-xs font-semibold rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-colors">
                            </div>
                            <!-- Page size select -->
                            <select id="bpjs-page-size" onchange="changeBpjsPageSize(this.value)" class="px-3 py-2 text-xs font-bold rounded-xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:outline-none focus:ring-2 focus:ring-teal-500/20 focus:border-teal-500 transition-colors">
                                <option value="10">10 Baris</option>
                                <option value="25" selected>25 Baris</option>
                                <option value="50">50 Baris</option>
                                <option value="100">100 Baris</option>
                            </select>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm" id="bpjs-report-table">
                            <thead>
                                <tr class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40">
                                    <th class="px-6 py-4">Tanggal</th>
                                    <th class="px-6 py-4">Poliklinik BPJS</th>
                                    <th class="px-6 py-4 text-center">Jumlah Antrean</th>
                                    <th class="px-6 py-4 text-center">Tunggu Admisi</th>
                                    <th class="px-6 py-4 text-center">Layan Admisi</th>
                                    <th class="px-6 py-4 text-center">Tunggu Poli</th>
                                    <th class="px-6 py-4 text-center">Layan Poli</th>
                                    <th class="px-6 py-4 text-center">Tunggu Farmasi</th>
                                    <th class="px-6 py-4 text-center">Layan Farmasi</th>
                                    <th class="px-6 py-4 text-center text-teal-600 dark:text-teal-400">Total Waktu</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800 font-semibold">
                                <!-- Ajax populated -->
                            </tbody>
                        </table>
                    </div>

                    <!-- BPJS Pagination Controls -->
                    <div class="px-8 py-5 border-t border-slate-200/50 dark:border-slate-800/50 flex flex-col sm:flex-row justify-between items-center gap-4 bg-slate-50/50 dark:bg-slate-800/10" id="bpjs-pagination-controls-bar">
                        <div class="text-xs font-semibold text-slate-500">
                            Menampilkan <span id="bpjs-pagination-info-start" class="font-bold text-slate-700 dark:text-slate-300">0</span> - <span id="bpjs-pagination-info-end" class="font-bold text-slate-700 dark:text-slate-300">0</span> dari <span id="bpjs-pagination-info-total" class="font-bold text-slate-700 dark:text-slate-300">0</span> baris
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <button onclick="changeBpjsPage(1)" id="btn-bpjs-page-first" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-angle-double-left"></i>
                            </button>
                            <button onclick="changeBpjsPage(bpjsCurrentPage - 1)" id="btn-bpjs-page-prev" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-angle-left"></i> Prev
                            </button>
                            <div id="bpjs-pagination-pages" class="flex items-center gap-1.5">
                                <!-- JS renders page buttons here -->
                            </div>
                            <button onclick="changeBpjsPage(bpjsCurrentPage + 1)" id="btn-bpjs-page-next" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                Next <i class="fas fa-angle-right"></i>
                            </button>
                            <button onclick="changeBpjsPage(bpjsTotalPages)" id="btn-bpjs-page-last" class="glass px-3 py-2 rounded-xl text-xs font-bold hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-angle-double-right"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- SLIDE-OVER DETAIL PANEL (AJAX LOADED) -->
<div id="slide-detail-panel" class="slide-panel fixed top-0 right-0 h-full w-full md:w-[480px] bg-white dark:bg-slate-950 shadow-2xl z-[80] translate-x-full border-l border-slate-200 dark:border-slate-800 flex flex-col">
    <!-- Header -->
    <div class="px-8 py-6 border-b border-slate-200 dark:border-slate-800 flex justify-between items-center bg-blue-600/5 dark:bg-blue-900/10">
        <div>
            <h3 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Detail Kunjungan & Timeline</h3>
            <p class="text-xs text-slate-400 mt-1">SIMRS vs BPJS Timestamp Analysis</p>
        </div>
        <button onclick="closeDetailPanel()" class="w-8 h-8 rounded-full bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 flex items-center justify-center text-slate-500 dark:text-slate-300 transition-colors">
            <i class="fas fa-times"></i>
        </button>
    </div>

    <!-- Content Area (Scrollable) -->
    <div class="flex-grow overflow-y-auto p-8 space-y-6" id="panel-content">
        <!-- JS dynamically renders this -->
    </div>

    <!-- Panel Actions Footer -->
    <div class="p-6 border-t border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-900/50 flex justify-end gap-3" id="panel-footer">
        <!-- JS dynamically renders verify and cancel buttons -->
    </div>
</div>

<!-- Slide-over Backdrop Overlay -->
<div id="panel-overlay" class="fixed inset-0 bg-slate-900/50 backdrop-blur-xs z-[70] hidden transition-opacity opacity-0" onclick="closeDetailPanel()"></div>
@endsection

@push('styles')
<style>
    /* CSS slide-panel transition handles slide-in animation */
    .slide-panel {
        transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .slide-panel.open {
        transform: translateX(0);
    }
    
    /* Animation pulse for duration badges */
    .flow-duration {
        animation: duration-pulse 3s infinite ease-in-out;
    }
    @keyframes duration-pulse {
        0%, 100% {
            opacity: 1;
            transform: scale(1);
        }
        50% {
            opacity: 0.9;
            transform: scale(1.03);
        }
    }
    
    /* Vertical line for patient chronological list */
    .patient-timeline-line {
        position: absolute;
        left: 20px;
        top: 24px;
        bottom: 24px;
        width: 2px;
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Global Dashboard data parsed from Laravel
    const analytics = @json($analytics);
    let activeTab = 'simrs';
    
    // Chart instances
    let clinicChartObj = null;
    let statusChartObj = null;

    let bpjsRawList = [];
    let bpjsFilteredList = [];
    let bpjsCurrentPage = 1;
    let bpjsPageSize = 25;
    let bpjsTotalPages = 1;
    let bpjsVolumeChartObj = null;
    let bpjsTimesChartObj = null;

    document.addEventListener('DOMContentLoaded', function () {
        initCharts();
        setupFilters();
    });

    function switchTab(tabName) {
        activeTab = tabName;
        
        const tabSimrsBtn = document.getElementById('btn-tab-simrs');
        const tabBpjsBtn = document.getElementById('btn-tab-bpjs');
        const tabSimrsContent = document.getElementById('tab-simrs-content');
        const tabBpjsContent = document.getElementById('tab-bpjs-content');
        const simrsDateFilter = document.getElementById('simrs-date-filter');
        const bpjsDateFilter = document.getElementById('bpjs-dashboard-filter');

        if (tabName === 'simrs') {
            // UI Button states
            tabSimrsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-900 shadow-sm text-blue-600 dark:text-blue-400";
            tabBpjsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";
            
            // Content displays
            tabSimrsContent.classList.remove('hidden');
            tabBpjsContent.classList.add('hidden');
            simrsDateFilter.classList.remove('hidden');
            bpjsDateFilter.classList.add('hidden');
        } else {
            // UI Button states
            tabBpjsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all bg-white dark:bg-slate-900 shadow-sm text-teal-600 dark:text-teal-400";
            tabSimrsBtn.className = "px-5 py-2.5 rounded-xl text-sm font-bold transition-all text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white";
            
            // Content displays
            tabBpjsContent.classList.remove('hidden');
            tabSimrsContent.classList.add('hidden');
            bpjsDateFilter.classList.remove('hidden');
            simrsDateFilter.classList.add('hidden');
        }
    }

    function initCharts() {
        // --- 1. Bar Chart (Clinic Performance) ---
        const ctxClinic = document.getElementById('clinicChart').getContext('2d');
        const clinicLabels = Object.keys(analytics.clinic_stats).slice(0, 10); // show top 10
        const waitTimes = clinicLabels.map(c => analytics.clinic_stats[c].waktu_tunggu_poli.median);
        const layanTimes = clinicLabels.map(c => analytics.clinic_stats[c].waktu_layan_poli.median);

        clinicChartObj = new Chart(ctxClinic, {
            type: 'bar',
            data: {
                labels: clinicLabels,
                datasets: [
                    {
                        label: 'Median Waktu Tunggu (3->4)',
                        data: waitTimes,
                        backgroundColor: 'rgba(59, 130, 246, 0.85)', // Blue
                        borderRadius: 8,
                    },
                    {
                        label: 'Median Waktu Layan (4->5)',
                        data: layanTimes,
                        backgroundColor: 'rgba(16, 185, 129, 0.85)', // Emerald
                        borderRadius: 8,
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
                            font: { family: 'Plus Jakarta Sans', weight: '600' }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: { border: { dash: [4, 4] } }
                }
            }
        });

        // --- 2. Doughnut Chart (Flow Status Counts) ---
        const ctxStatus = document.getElementById('statusChart').getContext('2d');
        const statusKeys = ['Lengkap (3,4,5,6,7)', 'Lengkap (3,4,5,6)', 'Lengkap (3,4,5)', 'Belum Lengkap'];
        const statusValues = statusKeys.map(k => analytics.status_counts[k] || 0);

        statusChartObj = new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusKeys.map(k => k.replace('Lengkap ', 'Task ')),
                datasets: [{
                    data: statusValues,
                    backgroundColor: [
                        '#10b981', // emerald
                        '#3b82f6', // blue
                        '#818cf8', // indigo
                        '#f59e0b', // amber
                    ],
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
    }

    // Pagination variables
    let currentPage = 1;
    let pageSize = 25;
    let filteredRows = [];

    function setupFilters() {
        const searchInput = document.getElementById('search-patient');
        const selectClinic = document.getElementById('filter-clinic');
        const selectStatus = document.getElementById('filter-status');

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
            filteredRows[i].classList.remove('hidden');
        }

        // Show/hide empty state
        const emptyAlert = document.getElementById('no-patients-found');
        const paginationBar = document.getElementById('pagination-controls-bar');
        
        if (totalItems === 0) {
            emptyAlert.classList.remove('hidden');
            paginationBar.classList.add('hidden');
        } else {
            emptyAlert.classList.add('hidden');
            paginationBar.classList.remove('hidden');
        }

        // Update pagination numbers info
        document.getElementById('pagination-info-start').textContent = totalItems === 0 ? 0 : startIdx + 1;
        document.getElementById('pagination-info-end').textContent = endIdx;
        document.getElementById('pagination-info-total').textContent = totalItems;

        // Button disabled states
        document.getElementById('btn-page-first').disabled = currentPage === 1;
        document.getElementById('btn-page-prev').disabled = currentPage === 1;
        document.getElementById('btn-page-next').disabled = currentPage === totalPages;
        document.getElementById('btn-page-last').disabled = currentPage === totalPages;

        // Page buttons rendering
        const pagesContainer = document.getElementById('pagination-pages');
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

    function changePage(page) {
        const totalItems = filteredRows.length;
        const totalPages = Math.ceil(totalItems / pageSize) || 1;
        
        if (page < 1) page = 1;
        if (page > totalPages) page = totalPages;
        
        currentPage = page;
        paginate();

        // Scroll back to table header
        document.getElementById('patient-registry-card').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    function changePageSize(size) {
        pageSize = parseInt(size, 10);
        currentPage = 1;
        paginate();
    }

    function scrollToPatientTableWithAnomalies() {
        document.getElementById('filter-status').value = 'anomali';
        document.getElementById('filter-status').dispatchEvent(new Event('change'));
        
        document.getElementById('patient-registry-card').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    // Helper to safely parse different date formats in JS
    function parseJsDate(val) {
        if (!val) return null;
        const sVal = String(val).trim();
        // If it is a 13-digit timestamp
        if (/^\d{13}$/.test(sVal)) {
            return new Date(parseInt(sVal, 10));
        }
        // If it is a 10-digit timestamp
        if (/^\d{10}$/.test(sVal)) {
            return new Date(parseInt(sVal, 10) * 1000);
        }
        // If it is a standard string datetime
        const d = new Date(sVal);
        return isNaN(d.getTime()) ? null : d;
    }

    // Format Date to Time string
    function formatTimeOnly(dateObj) {
        if (!dateObj) return '--:--:--';
        return dateObj.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    }

    // --- Slide-Over Patient Detail Chronology ---
    function showPatientDetail(noRawat) {
        const panel = document.getElementById('slide-detail-panel');
        const overlay = document.getElementById('panel-overlay');
        const content = document.getElementById('panel-content');
        const footer = document.getElementById('panel-footer');

        // Loading indicator
        content.innerHTML = `
            <div class="flex flex-col items-center justify-center py-20 space-y-4">
                <div class="w-10 h-10 border-4 border-blue-600/20 border-t-blue-600 rounded-full animate-spin"></div>
                <p class="text-sm font-semibold text-slate-500">Mengambil data rekam medis...</p>
            </div>
        `;
        footer.innerHTML = '';

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

        // Render durasi internal
        let durationItemsHTML = '';
        const durLabels = {
            'waktu_tunggu_poli': 'Tunggu Poli (Task 3 &rarr; 4)',
            'waktu_layan_poli': 'Layanan Poli (Task 4 &rarr; 5)',
            'waktu_tunggu_farmasi': 'Tunggu Farmasi (Task 5 &rarr; 6)',
            'waktu_layan_farmasi': 'Layanan Farmasi (Task 6 &rarr; 7)',
            'total_waktu_rs': 'Total Pelayanan RS (Task 3 &rarr; 7)'
        };

        for (const [key, val] of Object.entries(patient.durations)) {
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
                    if (key === 'total_waktu_rs') {
                        boxClass = 'bg-blue-600/5 border border-blue-600/10 dark:border-blue-600/20';
                        badgeClass = 'text-blue-600 dark:text-blue-400 font-extrabold';
                    }
                }
                
                durationItemsHTML += `
                    <div class="flex justify-between items-center p-3.5 rounded-2xl ${boxClass}">
                        <span class="text-xs font-semibold text-slate-500">${durLabels[key]}</span>
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
            const realT = patient.timestamps_real[i];
            const sentT = patient.timestamps_sent[i];
            const task = taskDetails[i];

            const parsedReal = parseJsDate(realT);
            const parsedSent = parseJsDate(sentT);

            let statusHTML = '';
            let lineActive = parsedReal ? 'border-blue-500 dark:border-blue-600' : 'border-slate-200 dark:border-slate-800';
            let circleColor = parsedReal ? 'bg-blue-600 text-white' : 'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-600';

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

            timelineHTML += `
                <div class="relative pl-8 pb-6 border-l-2 ${lineActive} last:border-l-0 ml-3.5">
                    <!-- Circle indicator -->
                    <div class="absolute -left-[9px] top-0 w-4 h-4 rounded-full flex items-center justify-center ${circleColor} z-10 shadow-sm border-2 border-white dark:border-slate-950">
                        <div class="w-1.5 h-1.5 rounded-full bg-current"></div>
                    </div>
                    
                    <div class="flex-grow -mt-1">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200">Task ${i}: ${task.name}</span>
                        <div class="grid grid-cols-2 gap-2 mt-2">
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-2 rounded-xl border border-slate-200/40 dark:border-slate-800/40">
                                <span class="text-[9px] uppercase font-bold text-slate-400">SIMRS Real</span>
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${formatTimeOnly(parsedReal)}</p>
                            </div>
                            <div class="bg-slate-50 dark:bg-slate-900/40 p-2 rounded-xl border border-slate-200/40 dark:border-slate-800/40">
                                <span class="text-[9px] uppercase font-bold text-slate-400">BPJS Sent</span>
                                <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${formatTimeOnly(parsedSent)}</p>
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
                <!-- Identity Card (Outfit Style) -->
                <div class="bg-slate-50/50 dark:bg-slate-900/40 border border-slate-200/40 dark:border-slate-800/40 p-5 rounded-2xl">
                    <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">Nama Lengkap Pasien</span>
                    <h4 class="text-lg font-bold text-slate-900 dark:text-white mt-1 leading-snug">${patient.nm_pasien}</h4>
                    
                    <div class="grid grid-cols-2 gap-x-4 gap-y-3 mt-4 pt-4 border-t border-slate-200/40 dark:border-slate-800/40">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No. Rekam Medis</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${patient.no_rkm_medis}</p>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">No. Registrasi / Rawat</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5 truncate" title="${patient.no_rawat}">${patient.no_rawat}</p>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Poliklinik</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5">${patient.nm_poli}</p>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider">Dokter Spesialis</span>
                            <p class="text-xs font-bold text-slate-700 dark:text-slate-300 mt-0.5 truncate" title="${patient.nm_dokter}">${patient.nm_dokter}</p>
                        </div>
                    </div>
                </div>

                <!-- Durations Summary -->
                <div class="space-y-2">
                    <h4 class="text-xs uppercase font-extrabold text-slate-400 dark:text-slate-500 tracking-wider">Kalkulasi Durasi Internal</h4>
                    <div class="space-y-2.5">
                        ${durationItemsHTML || '<div class="text-xs text-slate-400 p-4 text-center bg-slate-50 dark:bg-slate-900 rounded-2xl">Tidak ada data pelayanan yang terhitung.</div>'}
                    </div>
                </div>

                <!-- Timeline Chronology -->
                <div class="space-y-4">
                    <h4 class="text-xs uppercase font-extrabold text-slate-400 dark:text-slate-500 tracking-wider">Perbandingan Log Kronologi (Task 3-7)</h4>
                    <div class="relative pr-2">
                        ${timelineHTML}
                    </div>
                </div>
            </div>
        `;

        // Render Action Footer (Cross Verify)
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

    function closeDetailPanel() {
        const panel = document.getElementById('slide-detail-panel');
        const overlay = document.getElementById('panel-overlay');

        panel.classList.remove('open');
        overlay.classList.add('opacity-0');
        setTimeout(() => {
            overlay.classList.add('hidden');
        }, 400);
    }

    // --- On-Demand BPJS Cross Verification API Call ---
    function crossVerifyBpjs(noRawat) {
        const btn = document.getElementById('btn-verify-bpjs');
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
        // Append verification results to detail panel dynamically
        const content = document.getElementById('panel-content');
        
        let rows = '';
        bpjsTasks.forEach(task => {
            const parsedWkt = parseJsDate(task.wakturs);
            rows += `
                <tr class="border-b border-slate-100/50 dark:border-slate-800/50">
                    <td class="py-2 text-xs font-bold">Task ${task.taskid}</td>
                    <td class="py-2 text-xs">${task.taskname}</td>
                    <td class="py-2 text-xs text-right font-mono">${formatTimeOnly(parsedWkt)}</td>
                </tr>
            `;
        });

        // Remove old verified box if exists
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

    // --- Tab 2: Fetch Official BPJS Dashboard Reports ---
    function fetchBpjsDailyReport() {
        const dateVal = document.getElementById('bpjs-date').value;
        if (!dateVal) {
            alert('Silakan pilih tanggal terlebih dahulu.');
            return;
        }

        toggleBpjsLoading(true);

        fetch(`/api/monitoring/bpjs-dashboard/tanggal?tanggal=${dateVal}`)
            .then(res => res.json())
            .then(res => {
                toggleBpjsLoading(false);
                if (res.success && res.data) {
                    renderBpjsReportData(res.data, `Rapor Resmi Tanggal: ${dateVal}`);
                } else {
                    renderBpjsReportError(res.metadata?.message || 'Data tidak ditemukan atau API Error.');
                }
            })
            .catch(err => {
                toggleBpjsLoading(false);
                renderBpjsReportError('Gagal terhubung ke web service RS/BPJS.');
            });
    }

    function fetchBpjsMonthlyReport() {
        const month = document.getElementById('bpjs-month').value;
        const year = document.getElementById('bpjs-year').value;

        toggleBpjsLoading(true);

        fetch(`/api/monitoring/bpjs-dashboard/bulan?bulan=${month}&tahun=${year}`)
            .then(res => res.json())
            .then(res => {
                toggleBpjsLoading(false);
                if (res.success && res.data) {
                    renderBpjsReportData(res.data, `Rapor Resmi Bulan: ${month} - ${year}`);
                } else {
                    renderBpjsReportError(res.metadata?.message || 'Data tidak ditemukan atau API Error.');
                }
            })
            .catch(err => {
                toggleBpjsLoading(false);
                renderBpjsReportError('Gagal terhubung ke web service RS/BPJS.');
            });
    }

    function toggleBpjsLoading(isLoading) {
        const loader = document.getElementById('bpjs-report-loading');
        const empty = document.getElementById('bpjs-report-empty');
        const container = document.getElementById('bpjs-report-container');

        if (isLoading) {
            loader.classList.remove('hidden');
            empty.classList.add('hidden');
            container.classList.add('hidden');
        } else {
            loader.classList.add('hidden');
        }
    }

    function renderBpjsReportData(bpjsData, title) {
        const container = document.getElementById('bpjs-report-container');
        container.classList.remove('hidden');

        // Populate card statistics
        const list = bpjsData.list || [];
        
        let totalQueues = 0;
        let totalPoliWait = 0;
        let totalPoliLayan = 0;
        let totalFarmasiLayan = 0;
        let totalAllTasks = 0;
        let numRecords = list.length;

        // Quick Insights Variables
        let busiestPoli = null;
        let maxQueue = -1;
        let longestWaitPoli = null;
        let maxWait = -1;
        let longestLayanPoli = null;
        let maxLayan = -1;

        list.forEach(item => {
            const q = parseInt(item.jumlah_antrean || 0);
            const w3 = parseFloat(item.avg_waktu_task3 || 0);
            const l4 = parseFloat(item.avg_waktu_task4 || 0);
            const name = item.namapoli || item.kodepoli || 'N/A';

            totalQueues += q;
            totalPoliWait += w3;
            totalPoliLayan += l4;
            totalFarmasiLayan += parseFloat(item.avg_waktu_task6 || 0);

            // Sum of all tasks 1-6
            const sumSeconds = parseFloat(item.avg_waktu_task1 || 0) +
                               parseFloat(item.avg_waktu_task2 || 0) +
                               parseFloat(item.avg_waktu_task3 || 0) +
                               parseFloat(item.avg_waktu_task4 || 0) +
                               parseFloat(item.avg_waktu_task5 || 0) +
                               parseFloat(item.avg_waktu_task6 || 0);
            totalAllTasks += sumSeconds;

            // Compute Busiest & Longest
            if (q > maxQueue) {
                maxQueue = q;
                busiestPoli = `${name} (${q} Antrean)`;
            }
            if (w3 > maxWait) {
                maxWait = w3;
                longestWaitPoli = `${name} (${(w3 / 60).toFixed(1)}m)`;
            }
            if (l4 > maxLayan) {
                maxLayan = l4;
                longestLayanPoli = `${name} (${(l4 / 60).toFixed(1)}m)`;
            }
        });

        // Convert seconds to minutes for average cards
        const avgPoliWait = numRecords ? ((totalPoliWait / numRecords) / 60).toFixed(1) : 0;
        const avgPoliLayan = numRecords ? ((totalPoliLayan / numRecords) / 60).toFixed(1) : 0;
        const avgFarmasiLayan = numRecords ? ((totalFarmasiLayan / numRecords) / 60).toFixed(1) : 0;
        const avgTotalTime = numRecords ? ((totalAllTasks / numRecords) / 60).toFixed(1) : 0;

        document.getElementById('bpjs-stat-queues').textContent = totalQueues;
        document.getElementById('bpjs-stat-wait-poli').textContent = `${avgPoliWait}m`;
        document.getElementById('bpjs-stat-poli').textContent = `${avgPoliLayan}m`;
        document.getElementById('bpjs-stat-farmasi').textContent = `${avgFarmasiLayan}m`;
        document.getElementById('bpjs-stat-total-time').textContent = `${avgTotalTime}m`;

        // Update Quick Insights text
        document.getElementById('bpjs-insight-busiest').textContent = busiestPoli || 'Tidak Ada';
        document.getElementById('bpjs-insight-longest-wait').textContent = longestWaitPoli || 'Tidak Ada';
        document.getElementById('bpjs-insight-longest-layan').textContent = longestLayanPoli || 'Tidak Ada';

        // Render BPJS Charts
        renderBpjsCharts(list);

        // Store to global state for pagination
        bpjsRawList = [...list];
        // Sort chronologically by date then by clinic name
        bpjsRawList.sort((a, b) => {
            const dateA = a.tanggal || '';
            const dateB = b.tanggal || '';
            if (dateA !== dateB) return dateA.localeCompare(dateB);
            const nameA = a.namapoli || a.kodepoli || '';
            const nameB = b.namapoli || b.kodepoli || '';
            return nameA.localeCompare(nameB);
        });

        bpjsFilteredList = [...bpjsRawList];
        bpjsCurrentPage = 1;
        
        // Reset search input
        document.getElementById('bpjs-search-input').value = '';

        paginateBpjsTable();
    }

    function renderBpjsCharts(list) {
        // Group by clinic name and sum/average to have clean poliklinik-level statistics
        const poliMap = {};
        list.forEach(item => {
            const name = item.namapoli || item.kodepoli || 'N/A';
            if (!poliMap[name]) {
                poliMap[name] = {
                    name: name,
                    totalQueues: 0,
                    totalWaitPoli: 0,
                    totalLayanPoli: 0,
                    count: 0
                };
            }
            poliMap[name].totalQueues += parseInt(item.jumlah_antrean || 0);
            poliMap[name].totalWaitPoli += parseFloat(item.avg_waktu_task3 || 0);
            poliMap[name].totalLayanPoli += parseFloat(item.avg_waktu_task4 || 0);
            poliMap[name].count += 1;
        });

        const aggregatedPoli = Object.values(poliMap);

        // Sort for charts: Top 7 by queue volume
        const sortedForCharts = aggregatedPoli.sort((a, b) => b.totalQueues - a.totalQueues).slice(0, 7);
        
        const labels = sortedForCharts.map(item => item.name);
        const queueData = sortedForCharts.map(item => item.totalQueues);
        const waitData = sortedForCharts.map(item => (item.totalWaitPoli / item.count / 60).toFixed(1));
        const layanData = sortedForCharts.map(item => (item.totalLayanPoli / item.count / 60).toFixed(1));

        // Render/Update Volume Chart
        if (bpjsVolumeChartObj) bpjsVolumeChartObj.destroy();
        const ctxVolume = document.getElementById('bpjs-chart-volume').getContext('2d');
        bpjsVolumeChartObj = new Chart(ctxVolume, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Total Antrean',
                    data: queueData,
                    backgroundColor: 'rgba(20, 184, 166, 0.7)',
                    borderColor: 'rgb(20, 184, 166)',
                    borderWidth: 1.5,
                    borderRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(226, 232, 240, 0.05)' } },
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                }
            }
        });

        // Render/Update Times Chart
        if (bpjsTimesChartObj) bpjsTimesChartObj.destroy();
        const ctxTimes = document.getElementById('bpjs-chart-times').getContext('2d');
        bpjsTimesChartObj = new Chart(ctxTimes, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Tunggu Poli (T3)',
                        data: waitData,
                        backgroundColor: 'rgba(59, 130, 246, 0.7)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1.5,
                        borderRadius: 6
                    },
                    {
                        label: 'Layan Poli (T4)',
                        data: layanData,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 1.5,
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { size: 10 } } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(226, 232, 240, 0.05)' } },
                    x: { ticks: { font: { size: 10 } }, grid: { display: false } }
                }
            }
        });
    }

    function paginateBpjsTable() {
        const tbody = document.querySelector('#bpjs-report-table tbody');
        tbody.innerHTML = '';

        bpjsPageSize = parseInt(document.getElementById('bpjs-page-size').value, 10);
        bpjsTotalPages = Math.ceil(bpjsFilteredList.length / bpjsPageSize) || 1;

        if (bpjsCurrentPage < 1) bpjsCurrentPage = 1;
        if (bpjsCurrentPage > bpjsTotalPages) bpjsCurrentPage = bpjsTotalPages;

        const startIdx = (bpjsCurrentPage - 1) * bpjsPageSize;
        const endIdx = startIdx + bpjsPageSize;
        const pageItems = bpjsFilteredList.slice(startIdx, endIdx);

        if (pageItems.length === 0) {
            tbody.innerHTML = `<tr><td colspan="10" class="py-10 text-center text-slate-400">Tidak ada laporan pelayanan tercatat di server BPJS Kesehatan.</td></tr>`;
            updateBpjsPaginationControls();
            return;
        }

        // Helper to format date
        const formatDateStr = (dateStr) => {
            if (!dateStr) return '-';
            try {
                const parts = dateStr.split('-');
                if (parts.length === 3) {
                    return `${parts[2]}-${parts[1]}-${parts[0]}`;
                }
                return dateStr;
            } catch (e) {
                return dateStr;
            }
        };

        pageItems.forEach(item => {
            // Calculate total service time (seconds to minutes)
            const sumSeconds = parseFloat(item.avg_waktu_task1 || 0) +
                               parseFloat(item.avg_waktu_task2 || 0) +
                               parseFloat(item.avg_waktu_task3 || 0) +
                               parseFloat(item.avg_waktu_task4 || 0) +
                               parseFloat(item.avg_waktu_task5 || 0) +
                               parseFloat(item.avg_waktu_task6 || 0);
            const totalMinutes = (sumSeconds / 60).toFixed(1);

            tbody.innerHTML += `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/10 transition-colors border-b border-slate-100 dark:border-slate-800/50">
                    <td class="px-6 py-4 text-slate-500 font-mono text-xs">${formatDateStr(item.tanggal)}</td>
                    <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-200">${item.namapoli || item.kodepoli || 'N/A'}</td>
                    <td class="px-6 py-4 text-center font-bold text-slate-600 dark:text-slate-400">${item.jumlah_antrean || 0}</td>
                    <td class="px-6 py-4 text-center text-blue-600 font-semibold">${(parseFloat(item.avg_waktu_task1 || 0) / 60).toFixed(1)}m</td>
                    <td class="px-6 py-4 text-center text-indigo-600 font-semibold">${(parseFloat(item.avg_waktu_task2 || 0) / 60).toFixed(1)}m</td>
                    <td class="px-6 py-4 text-center text-amber-600 font-semibold">${(parseFloat(item.avg_waktu_task3 || 0) / 60).toFixed(1)}m</td>
                    <td class="px-6 py-4 text-center text-emerald-600 font-semibold">${(parseFloat(item.avg_waktu_task4 || 0) / 60).toFixed(1)}m</td>
                    <td class="px-6 py-4 text-center text-pink-600 font-semibold">${(parseFloat(item.avg_waktu_task5 || 0) / 60).toFixed(1)}m</td>
                    <td class="px-6 py-4 text-center text-purple-600 font-bold">${(parseFloat(item.avg_waktu_task6 || 0) / 60).toFixed(1)}m</td>
                    <td class="px-6 py-4 text-center text-teal-600 dark:text-teal-400 font-extrabold">${totalMinutes}m</td>
                </tr>
            `;
        });

        updateBpjsPaginationControls();
    }

    function updateBpjsPaginationControls() {
        const start = bpjsFilteredList.length === 0 ? 0 : (bpjsCurrentPage - 1) * bpjsPageSize + 1;
        const end = Math.min(bpjsCurrentPage * bpjsPageSize, bpjsFilteredList.length);
        const total = bpjsFilteredList.length;

        document.getElementById('bpjs-pagination-info-start').textContent = start;
        document.getElementById('bpjs-pagination-info-end').textContent = end;
        document.getElementById('bpjs-pagination-info-total').textContent = total;

        // Button disabled states
        document.getElementById('btn-bpjs-page-first').disabled = (bpjsCurrentPage === 1);
        document.getElementById('btn-bpjs-page-prev').disabled = (bpjsCurrentPage === 1);
        document.getElementById('btn-bpjs-page-next').disabled = (bpjsCurrentPage === bpjsTotalPages);
        document.getElementById('btn-bpjs-page-last').disabled = (bpjsCurrentPage === bpjsTotalPages);

        // Render page number buttons (max 5)
        const pagesContainer = document.getElementById('bpjs-pagination-pages');
        pagesContainer.innerHTML = '';

        let startPage = Math.max(1, bpjsCurrentPage - 2);
        let endPage = Math.min(bpjsTotalPages, startPage + 4);
        if (endPage - startPage < 4) {
            startPage = Math.max(1, endPage - 4);
        }

        for (let i = startPage; i <= endPage; i++) {
            const btn = document.createElement('button');
            btn.onclick = () => changeBpjsPage(i);
            btn.textContent = i;
            if (i === bpjsCurrentPage) {
                btn.className = "px-3 py-1.5 rounded-xl text-xs font-extrabold bg-teal-600 text-white shadow-sm shadow-teal-500/20";
            } else {
                btn.className = "glass px-3 py-1.5 rounded-xl text-xs font-bold text-slate-600 hover:bg-slate-100 dark:hover:bg-slate-800 dark:text-slate-400";
            }
            pagesContainer.appendChild(btn);
        }
    }

    function handleBpjsSearch() {
        const query = document.getElementById('bpjs-search-input').value.toLowerCase().trim();
        if (query === '') {
            bpjsFilteredList = [...bpjsRawList];
        } else {
            bpjsFilteredList = bpjsRawList.filter(item => {
                const poli = (item.namapoli || item.kodepoli || '').toLowerCase();
                const tgl = (item.tanggal || '').toLowerCase();
                return poli.includes(query) || tgl.includes(query);
            });
        }
        bpjsCurrentPage = 1;
        paginateBpjsTable();
    }

    function changeBpjsPage(page) {
        if (page < 1 || page > bpjsTotalPages) return;
        bpjsCurrentPage = page;
        paginateBpjsTable();
    }

    function changeBpjsPageSize(size) {
        bpjsPageSize = parseInt(size, 10);
        bpjsCurrentPage = 1;
        paginateBpjsTable();
    }

    function renderBpjsReportError(message) {
        const container = document.getElementById('bpjs-report-container');
        const empty = document.getElementById('bpjs-report-empty');
        
        container.classList.add('hidden');
        empty.classList.remove('hidden');

        empty.innerHTML = `
            <div class="w-24 h-24 bg-rose-50 dark:bg-rose-500/10 rounded-[35px] flex items-center justify-center text-rose-600 text-4xl shadow-xl shadow-rose-500/5 mx-auto">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h4 class="text-xl font-bold text-rose-600">Gagal Mengambil Rapor BPJS</h4>
                <p class="text-sm text-slate-500 mt-2 max-w-sm mx-auto">
                    ${message}
                </p>
            </div>
        `;
    }

    // Phase 2: Anomaly Filter from Warning Banner
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

    // Phase 2: Export to CSV
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
        
        const dateFrom = document.getElementById('date_from')?.value || 'data';
        const dateTo = document.getElementById('date_to')?.value || 'data';
        link.setAttribute("download", `rekap_monitoring_antrean_${dateFrom}_to_${dateTo}.csv`);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }

    // Phase 2: Auto Refresh Logic
    const autoRefreshToggle = document.getElementById('auto-refresh-toggle');
    const autoRefreshIcon = document.getElementById('auto-refresh-icon');
    let autoRefreshTimer = null;

    if (autoRefreshToggle) {
        const isAutoRefreshActive = localStorage.getItem('auto_refresh_monitoring') === 'true';
        autoRefreshToggle.checked = isAutoRefreshActive;

        if (isAutoRefreshActive) {
            autoRefreshIcon?.classList.add('fa-spin');
            autoRefreshTimer = setTimeout(() => {
                window.location.reload();
            }, 30000);
        }

        autoRefreshToggle.addEventListener('change', function(e) {
            if (e.target.checked) {
                localStorage.setItem('auto_refresh_monitoring', 'true');
                autoRefreshIcon?.classList.add('fa-spin');
                autoRefreshTimer = setTimeout(() => {
                    window.location.reload();
                }, 30000);
            } else {
                localStorage.setItem('auto_refresh_monitoring', 'false');
                autoRefreshIcon?.classList.remove('fa-spin');
                if (autoRefreshTimer) {
                    clearTimeout(autoRefreshTimer);
                    autoRefreshTimer = null;
                }
            }
        });
    }
</script>
@endpush
