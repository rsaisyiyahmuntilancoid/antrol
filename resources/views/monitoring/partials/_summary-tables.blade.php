<!-- Summary Tables per Clinic & Doctor -->
<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">
    <!-- Clinics Summary Table -->
    <div class="glass rounded-[32px] overflow-hidden shadow-sm">
        <div
            class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex justify-between items-center">
            <h3 class="text-lg font-bold tracking-tight flex items-center gap-2">
                <i class="fas fa-clinic-medical text-blue-600"></i> Ringkasan Kinerja Poliklinik
            </h3>
            <span class="text-xs text-slate-400 font-semibold">Klik baris untuk detail</span>
        </div>
        <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr
                        class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40 text-[11px] uppercase tracking-wider">
                        <th class="px-4 py-3">Poliklinik</th>
                        <th class="px-3 py-3 text-center">Pasien</th>
                        <th class="px-3 py-3 text-center">Tunggu Poli</th>
                        <th class="px-3 py-3 text-center">Layan Poli</th>
                        <th class="px-3 py-3 text-center">Tunggu Farm.</th>
                        <th class="px-3 py-3 text-center">Layan Farm.</th>
                        <th class="px-3 py-3 text-center">Total RS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($analytics['clinic_stats'] as $clinic => $stats)
                    <tr class="hover:bg-blue-50/40 dark:hover:bg-blue-900/10 transition-colors font-medium cursor-pointer"
                        onclick="showClinicDetail('{{ addslashes($clinic) }}', '{{ $dateFrom }}', '{{ $dateTo }}')">
                        <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200 text-xs">{{ $clinic }}</td>
                        <td class="px-3 py-3 text-center text-xs">{{ $stats['patient_count'] }}</td>
                        @foreach (['waktu_tunggu_poli' => 'blue', 'waktu_layan_poli' => 'emerald', 'waktu_tunggu_farmasi' => 'indigo', 'waktu_layan_farmasi' => 'teal', 'total_waktu_rs' => 'purple'] as $metricKey => $color)
                        <td class="px-3 py-3 text-center font-semibold text-xs">
                            @if($stats[$metricKey]['count'] > 0)
                                @if($stats[$metricKey]['median'] < 0)
                                    <span class="text-rose-500 font-bold">{{ $stats[$metricKey]['median'] }}m</span>
                                @else
                                    <span class="text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $stats[$metricKey]['median'] }}m</span>
                                @endif
                            @else
                                <span class="text-slate-300">&mdash;</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Doctors Load Summary Table -->
    <div class="glass rounded-[32px] overflow-hidden shadow-sm">
        <div
            class="px-8 py-6 border-b border-slate-200/50 dark:border-slate-800/50 flex justify-between items-center">
            <h3 class="text-lg font-bold tracking-tight flex items-center gap-2">
                <i class="fas fa-user-md text-emerald-600"></i> Beban Kerja & Median Durasi Dokter
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
            <table class="w-full text-left border-collapse text-sm">
                <thead>
                    <tr
                        class="bg-slate-50/50 dark:bg-slate-800/20 text-slate-400 font-semibold border-b border-slate-200/40 dark:border-slate-800/40 text-[11px] uppercase tracking-wider">
                        <th class="px-4 py-3">Nama Dokter</th>
                        <th class="px-3 py-3 text-center">Pasien</th>
                        <th class="px-3 py-3 text-center">Tunggu Poli</th>
                        <th class="px-3 py-3 text-center">Layan Poli</th>
                        <th class="px-3 py-3 text-center">Tunggu Farm.</th>
                        <th class="px-3 py-3 text-center">Layan Farm.</th>
                        <th class="px-3 py-3 text-center">Total RS</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($analytics['doctor_stats'] as $doctor => $stats)
                    <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-colors font-medium">
                        <td class="px-4 py-3 font-bold text-slate-800 dark:text-slate-200 text-xs">{{ $doctor }}</td>
                        <td class="px-3 py-3 text-center text-xs">{{ $stats['patient_count'] }}</td>
                        @foreach (['waktu_tunggu_poli' => 'blue', 'waktu_layan_poli' => 'emerald', 'waktu_tunggu_farmasi' => 'indigo', 'waktu_layan_farmasi' => 'teal', 'total_waktu_rs' => 'purple'] as $metricKey => $color)
                        <td class="px-3 py-3 text-center font-semibold text-xs">
                            @if($stats[$metricKey]['count'] > 0)
                                @if($stats[$metricKey]['median'] < 0)
                                    <span class="text-rose-500 font-bold">{{ $stats[$metricKey]['median'] }}m</span>
                                @else
                                    <span class="text-{{ $color }}-600 dark:text-{{ $color }}-400">{{ $stats[$metricKey]['median'] }}m</span>
                                @endif
                            @else
                                <span class="text-slate-300">&mdash;</span>
                            @endif
                        </td>
                        @endforeach
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
