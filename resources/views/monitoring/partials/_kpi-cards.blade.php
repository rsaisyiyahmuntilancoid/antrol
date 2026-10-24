<!-- KPI Cards Grid -->
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-5">
    <!-- Card 1: Total Patients -->
    <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
        <div
            class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
            <i class="fas fa-user-friends"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Total Kunjungan</h3>
        <p class="text-3xl font-bold text-slate-900 dark:text-white mt-3" id="kpi-total-patients">{{
            $analytics['summary']['total_patients'] }}</p>
        <div class="mt-2 text-xs font-semibold flex items-center text-slate-500">
            <span class="text-rose-500 font-bold mr-1" id="kpi-batal-patients">{{
                $analytics['summary']['batal_patients'] }}</span> batal / tidak hadir
        </div>
    </div>

    <!-- Card 2: Median Tunggu Poli -->
    <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
        <div
            class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
            <i class="fas fa-clock"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Tunggu Poli</h3>
        <p class="text-3xl font-bold text-blue-600 dark:text-blue-400 mt-3" id="kpi-waktu-tunggu-poli">
            @php $wtp = $analytics['global_stats']['waktu_tunggu_poli']; @endphp
            @if($wtp['count'] > 0)
            @if($wtp['median'] < 0) <span class="text-rose-500">{{ $wtp['median'] }}</span><span
                    class="text-sm font-semibold ml-0.5 text-rose-400">m</span>
                @else
                {{ $wtp['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                @endif
                @else
                <span class="text-slate-300 dark:text-slate-600">—</span>
                @endif
        </p>
        <div class="mt-2 text-xs font-semibold text-slate-500 flex items-center gap-2">
            <span>Task 3 &rarr; 4 (Admisi-Poli)</span>
            <span class="text-slate-400" id="kpi-count-waktu-tunggu-poli">@if($wtp['count'] > 0)n={{ $wtp['count'] }}@endif</span>
        </div>
    </div>

    <!-- Card 3: Median Layan Poli -->
    <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
        <div
            class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
            <i class="fas fa-user-md"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Layan Poli</h3>
        <p class="text-3xl font-bold text-emerald-600 dark:text-emerald-400 mt-3" id="kpi-waktu-layan-poli">
            @php $wlp = $analytics['global_stats']['waktu_layan_poli']; @endphp
            @if($wlp['count'] > 0)
            @if($wlp['median'] < 0) <span class="text-rose-500">{{ $wlp['median'] }}</span><span
                    class="text-sm font-semibold ml-0.5 text-rose-400">m</span>
                @else
                {{ $wlp['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                @endif
                @else
                <span class="text-slate-300 dark:text-slate-600">—</span>
                @endif
        </p>
        <div class="mt-2 text-xs font-semibold text-slate-500 flex items-center gap-2">
            <span>Task 4 &rarr; 5 (Pemeriksaan)</span>
            <span class="text-slate-400" id="kpi-count-waktu-layan-poli">@if($wlp['count'] > 0)n={{ $wlp['count'] }}@endif</span>
        </div>
    </div>

    <!-- Card 4: Median Tunggu Farmasi -->
    <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
        <div
            class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
            <i class="fas fa-prescription-bottle-alt"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Tunggu Farmasi</h3>
        <p class="text-3xl font-bold text-indigo-600 dark:text-indigo-400 mt-3" id="kpi-waktu-tunggu-farmasi">
            @php $wtf = $analytics['global_stats']['waktu_tunggu_farmasi']; @endphp
            @if($wtf['count'] > 0)
            @if($wtf['median'] < 0) <span class="text-rose-500">{{ $wtf['median'] }}</span><span
                    class="text-sm font-semibold ml-0.5 text-rose-400">m</span>
                @else
                {{ $wtf['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                @endif
                @else
                <span class="text-slate-300 dark:text-slate-600">—</span>
                @endif
        </p>
        <div class="mt-2 text-xs font-semibold text-slate-500 flex items-center gap-2">
            <span>Task 5 &rarr; 6 (Buat Resep)</span>
            <span class="text-slate-400" id="kpi-count-waktu-tunggu-farmasi">@if($wtf['count'] > 0)n={{ $wtf['count'] }}@endif</span>
        </div>
    </div>

    <!-- Card 5: Median Layan Farmasi -->
    <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
        <div
            class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
            <i class="fas fa-capsules"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Layan Farmasi</h3>
        <p class="text-3xl font-bold text-teal-600 dark:text-teal-400 mt-3" id="kpi-waktu-layan-farmasi">
            @php $wlf = $analytics['global_stats']['waktu_layan_farmasi']; @endphp
            @if($wlf['count'] > 0)
            @if($wlf['median'] < 0) <span class="text-rose-500">{{ $wlf['median'] }}</span><span
                    class="text-sm font-semibold ml-0.5 text-rose-400">m</span>
                @else
                {{ $wlf['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                @endif
                @else
                <span class="text-slate-300 dark:text-slate-600">—</span>
                @endif
        </p>
        <div class="mt-2 text-xs font-semibold text-slate-500 flex items-center gap-2">
            <span>Task 6 &rarr; 7 (Racik Obat)</span>
            <span class="text-slate-400" id="kpi-count-waktu-layan-farmasi">@if($wlf['count'] > 0)n={{ $wlf['count'] }}@endif</span>
        </div>
    </div>

    <!-- Card 6: Median Total Waktu RS -->
    <div class="glass p-6 rounded-3xl card-hover relative overflow-hidden group">
        <div
            class="absolute -right-4 -bottom-4 text-slate-200/20 dark:text-slate-800/10 text-7xl group-hover:scale-110 transition-transform duration-300">
            <i class="fas fa-hospital"></i>
        </div>
        <h3 class="text-xs font-bold uppercase tracking-wider text-slate-400">Med. Total RS</h3>
        <p class="text-3xl font-bold text-purple-600 dark:text-purple-400 mt-3" id="kpi-total-waktu-rs">
            @php $twr = $analytics['global_stats']['total_waktu_rs']; @endphp
            @if($twr['count'] > 0)
            @if($twr['median'] < 0) <span class="text-rose-500">{{ $twr['median'] }}</span><span
                    class="text-sm font-semibold ml-0.5 text-rose-400">m</span>
                @else
                {{ $twr['median'] }}<span class="text-sm font-semibold ml-0.5 text-slate-400">m</span>
                @endif
                @else
                <span class="text-slate-300 dark:text-slate-600">—</span>
                @endif
        </p>
        <div class="mt-2 text-xs font-semibold text-slate-500 flex items-center gap-2">
            <span>Task 3 &rarr; 7 (Admisi-Selesai)</span>
            <span class="text-slate-400" id="kpi-count-total-waktu-rs">@if($twr['count'] > 0)n={{ $twr['count'] }}@endif</span>
        </div>
    </div>
</div>
