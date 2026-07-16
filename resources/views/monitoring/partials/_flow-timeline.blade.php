<!-- Visual Flow Timeline -->
<div class="glass rounded-[32px] p-8">
    <h2 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-8">
        <i class="fas fa-project-diagram text-blue-600"></i> Visualisasi Alur Layanan & Median Waktu Pelayanan
    </h2>

    <div class="grid grid-cols-1 md:grid-cols-9 gap-6 items-center">
        <!-- Step 3 -->
        <div
            class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
            <div
                class="w-10 h-10 rounded-full bg-blue-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-blue-500/20 mb-3">
                3</div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Check-in</h4>
            <p class="text-[10px] text-slate-400 font-semibold mt-1">Admisi Selesai</p>
        </div>
        <!-- Connector 3->4 -->
        <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
            <div class="h-0.5 w-full bg-blue-500/20 hidden md:block"></div>
            @php $v = $analytics['global_stats']['waktu_tunggu_poli']; @endphp
            <span
                class="flow-duration px-3 py-1.5 rounded-full {{ $v['count'] > 0 && $v['median'] < 0 ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300' : 'bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300' }} font-bold text-xs shadow-sm mt-2 md:mt-0">
                {{ $v['count'] > 0 ? $v['median'].' m' : 'N/A' }}
            </span>
            <span class="text-[9px] text-slate-400 font-semibold mt-1">Tunggu Poli</span>
        </div>

        <!-- Step 4 -->
        <div
            class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
            <div
                class="w-10 h-10 rounded-full bg-indigo-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-indigo-500/20 mb-3">
                4</div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Mulai Poli</h4>
            <p class="text-[10px] text-slate-400 font-semibold mt-1">Perawat Masuk</p>
        </div>
        <!-- Connector 4->5 -->
        <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
            <div class="h-0.5 w-full bg-emerald-500/20 hidden md:block"></div>
            @php $v = $analytics['global_stats']['waktu_layan_poli']; @endphp
            <span
                class="flow-duration px-3 py-1.5 rounded-full {{ $v['count'] > 0 && $v['median'] < 0 ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300' : 'bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300' }} font-bold text-xs shadow-sm mt-2 md:mt-0">
                {{ $v['count'] > 0 ? $v['median'].' m' : 'N/A' }}
            </span>
            <span class="text-[9px] text-slate-400 font-semibold mt-1">Layanan Poli</span>
        </div>

        <!-- Step 5 -->
        <div
            class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
            <div
                class="w-10 h-10 rounded-full bg-emerald-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-emerald-500/20 mb-3">
                5</div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Selesai Dokter</h4>
            <p class="text-[10px] text-slate-400 font-semibold mt-1">Pemeriksaan Done</p>
        </div>
        <!-- Connector 5->6 -->
        <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
            <div class="h-0.5 w-full bg-indigo-500/20 hidden md:block"></div>
            @php $v = $analytics['global_stats']['waktu_tunggu_farmasi']; @endphp
            <span
                class="flow-duration px-3 py-1.5 rounded-full {{ $v['count'] > 0 && $v['median'] < 0 ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300' : 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' }} font-bold text-xs shadow-sm mt-2 md:mt-0">
                {{ $v['count'] > 0 ? $v['median'].' m' : 'N/A' }}
            </span>
            <span class="text-[9px] text-slate-400 font-semibold mt-1">Tunggu Farmasi</span>
        </div>

        <!-- Step 6 -->
        <div
            class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
            <div
                class="w-10 h-10 rounded-full bg-purple-600 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-purple-500/20 mb-3">
                6</div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Mulai Farmasi</h4>
            <p class="text-[10px] text-slate-400 font-semibold mt-1">Input Resep</p>
        </div>
        <!-- Connector 6->7 -->
        <div class="md:col-span-1 flex flex-col items-center justify-center text-center">
            <div class="h-0.5 w-full bg-purple-500/20 hidden md:block"></div>
            @php $v = $analytics['global_stats']['waktu_layan_farmasi']; @endphp
            <span
                class="flow-duration px-3 py-1.5 rounded-full {{ $v['count'] > 0 && $v['median'] < 0 ? 'bg-rose-100 dark:bg-rose-900/40 text-rose-700 dark:text-rose-300' : 'bg-purple-100 dark:bg-purple-900/40 text-purple-700 dark:text-purple-300' }} font-bold text-xs shadow-sm mt-2 md:mt-0">
                {{ $v['count'] > 0 ? $v['median'].' m' : 'N/A' }}
            </span>
            <span class="text-[9px] text-slate-400 font-semibold mt-1">Layanan Obat</span>
        </div>

        <!-- Step 7 -->
        <div
            class="md:col-span-1 bg-white dark:bg-slate-900 border border-slate-200/60 dark:border-slate-800 p-5 rounded-2xl text-center flex flex-col items-center">
            <div
                class="w-10 h-10 rounded-full bg-slate-900 dark:bg-white dark:text-slate-900 text-white font-bold flex items-center justify-center text-sm shadow-md shadow-slate-500/10 mb-3">
                7</div>
            <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Selesai</h4>
            <p class="text-[10px] text-slate-400 font-semibold mt-1">Serah Obat</p>
        </div>
    </div>
</div>
