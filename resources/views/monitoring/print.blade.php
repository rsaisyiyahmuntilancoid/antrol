<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Analisis Antrean Pelayanan - RSU Aisyiyah Muntilan</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Plus Jakarta Sans', 'sans-serif'],
                    },
                }
            }
        }
    </script>
    
    <style>
        @media print {
            @page {
                size: A4 portrait;
                margin: 0; /* Hides browser default header and footer (date, title, URL, page numbers) */
            }
            body {
                background: white !important;
                color: black !important;
                font-size: 9px;
                padding: 15mm 15mm 20mm 15mm !important;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
            tr {
                page-break-inside: avoid !important;
            }
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f8fafc;
        }
        
        table {
            border-collapse: collapse;
            width: 100%;
        }
        
        th, td {
            border: 1px solid #94a3b8;
            padding: 6px 8px;
            text-align: left;
            font-size: 9px;
        }
        
        th {
            background-color: #f1f5f9;
            font-weight: 700;
            color: #0f172a;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="text-slate-900 antialiased p-0 md:p-6">

    <!-- Container -->
    <div class="max-w-[850px] mx-auto bg-white p-6 md:p-10 shadow-md print:shadow-none print:p-0 rounded-none md:rounded-xl">
        
        <!-- Print Trigger Bar (Screen only) -->
        <div class="no-print mb-6 flex justify-between items-center bg-slate-50 border border-slate-200 p-4 rounded-xl">
            <span class="text-xs font-semibold text-slate-500">Pratinjau Dokumen Cetak Laporan Resmi</span>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="bg-rose-600 hover:bg-rose-700 text-white font-bold px-4 py-2 rounded-lg text-xs transition-all shadow-md flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                    Cetak Sekarang
                </button>
                <button onclick="window.close()" class="bg-slate-200 hover:bg-slate-300 text-slate-700 font-semibold px-4 py-2 rounded-lg text-xs transition-all">
                    Tutup
                </button>
            </div>
        </div>

        <!-- HEADER KOP SURAT -->
        <div class="flex items-center gap-6 border-b-4 border-double border-slate-900 pb-4">
            <img src="{{ asset('LOGORS.png') }}" class="w-16 h-16 object-contain shrink-0" alt="Logo RSU Aisyiyah Muntilan">
            <div class="grow text-left">
                <h1 class="text-lg font-bold tracking-tight text-slate-900 uppercase leading-none">RUMAH SAKIT UMUM AISYIYAH MUNTILAN</h1>
                <p class="text-[10px] font-semibold text-slate-600 mt-1">Jln. KH A. Dahlan No. 24 Muntilan, Magelang 56414</p>
                <p class="text-[10px] font-semibold text-slate-600">Telp : (0293) 587372, 587723 (hunting) | Website : www.rsaisyiyah-muntilan.com</p>
            </div>
        </div>

        <!-- REPORT TITLE -->
        <div class="mt-6 text-center">
            <h2 class="text-md font-bold uppercase tracking-wide text-slate-900">Laporan Analisis Antrean Pelayanan & Waktu Tunggu</h2>
            <p class="text-xs font-semibold text-slate-500 mt-1">Periode: {{ \Carbon\Carbon::parse($dateFrom)->format('d-m-Y') }} s/d {{ \Carbon\Carbon::parse($dateTo)->format('d-m-Y') }}</p>
            <p class="text-[9px] text-slate-400 mt-0.5">Dicetak pada: {{ now()->translatedFormat('d F Y, H:i') }} WIB</p>
        </div>

        <!-- SECTION 1: RINGKASAN KPI (KPI SUMMARY TABLE) -->
        <div class="mt-8">
            <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-700 mb-3 border-l-4 border-rose-600 pl-2">I. Ringkasan Kinerja Pelayanan (KPI)</h3>
            <table class="w-full text-xs">
                <thead>
                    <tr>
                        <th class="w-2/3">Indikator Mutu Waktu Tunggu (Kinerja Real SIMRS)</th>
                        <th class="w-1/3 text-right">Nilai Capaian (Median)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="font-medium">Total Kunjungan Pasien (Selesai Pelayanan)</td>
                        <td class="text-right font-bold">{{ $analytics['summary']['total_patients'] }} pasien</td>
                    </tr>
                    <tr>
                        <td class="font-medium">Pasien Batal / Tidak Datang</td>
                        <td class="text-right font-bold text-rose-600">{{ $analytics['summary']['batal_patients'] }} pasien</td>
                    </tr>
                    <tr>
                        <td class="font-medium">1. Waktu Tunggu Poliklinik (Admisi &rarr; Mulai Pemeriksaan)</td>
                        <td class="text-right font-bold">
                            {{ $analytics['global_stats']['waktu_tunggu_poli']['median'] }} menit
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">2. Waktu Pelayanan Poliklinik (Durasi Konsultasi & Tindakan Dokter)</td>
                        <td class="text-right font-bold">
                            {{ $analytics['global_stats']['waktu_layan_poli']['median'] }} menit
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">3. Waktu Tunggu Farmasi (Pengiriman Resep &rarr; Obat Siap)</td>
                        <td class="text-right font-bold">
                            {{ $analytics['global_stats']['waktu_tunggu_farmasi']['median'] }} menit
                        </td>
                    </tr>
                    <tr>
                        <td class="font-medium">4. Waktu Pelayanan Farmasi (Peracikan & Penyerahan KIE Obat)</td>
                        <td class="text-right font-bold">
                            {{ $analytics['global_stats']['waktu_layan_farmasi']['median'] }} menit
                        </td>
                    </tr>
                    <tr class="bg-slate-50 font-semibold border-t-2 border-slate-400">
                        <td class="font-bold text-slate-900">Total Durasi Pelayanan Rumah Sakit (Waktu Respons)</td>
                        <td class="text-right font-extrabold text-rose-600">
                            {{ $analytics['global_stats']['total_waktu_rs']['median'] }} menit
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- SECTION 2: STATISTIK KINERJA POLIKLINIK -->
        <div class="mt-8">
            <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-700 mb-3 border-l-4 border-rose-600 pl-2">II. Statistik Kinerja Durasi per Poliklinik</h3>
            <table class="w-full text-[9px]">
                <thead>
                    <tr>
                        <th>Poliklinik</th>
                        <th class="text-center">Pasien</th>
                        <th class="text-center">Tunggu Poli</th>
                        <th class="text-center">Layan Poli</th>
                        <th class="text-center">Tunggu Farm.</th>
                        <th class="text-center">Layan Farm.</th>
                        <th class="text-center">Total RS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['clinic_stats'] as $clinic => $stats)
                    <tr>
                        <td class="font-bold text-slate-800">{{ $clinic }}</td>
                        <td class="text-center font-semibold">{{ $stats['patient_count'] }}</td>
                        <td class="text-center">{{ $stats['waktu_tunggu_poli']['count'] > 0 ? $stats['waktu_tunggu_poli']['median'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $stats['waktu_layan_poli']['count'] > 0 ? $stats['waktu_layan_poli']['median'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $stats['waktu_tunggu_farmasi']['count'] > 0 ? $stats['waktu_tunggu_farmasi']['median'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $stats['waktu_layan_farmasi']['count'] > 0 ? $stats['waktu_layan_farmasi']['median'] . 'm' : '—' }}</td>
                        <td class="text-center font-bold text-rose-600">{{ $stats['total_waktu_rs']['count'] > 0 ? $stats['total_waktu_rs']['median'] . 'm' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- SECTION 3: BEBAN KERJA DOKTER -->
        <div class="mt-8 page-break">
            <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-700 mb-3 border-l-4 border-rose-600 pl-2">III. Analisis Beban Kerja & Kinerja Dokter</h3>
            <table class="w-full text-[9px]">
                <thead>
                    <tr>
                        <th>Nama Dokter Pelayan</th>
                        <th class="text-center">Jumlah Pasien</th>
                        <th class="text-center">Tunggu Poli</th>
                        <th class="text-center">Layan Poli</th>
                        <th class="text-center">Tunggu Farm.</th>
                        <th class="text-center">Layan Farm.</th>
                        <th class="text-center">Total RS</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($analytics['doctor_stats'] as $doctor => $stats)
                    <tr>
                        <td class="font-bold text-slate-800">{{ $doctor }}</td>
                        <td class="text-center font-semibold">{{ $stats['patient_count'] }}</td>
                        <td class="text-center">{{ $stats['waktu_tunggu_poli']['count'] > 0 ? $stats['waktu_tunggu_poli']['median'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $stats['waktu_layan_poli']['count'] > 0 ? $stats['waktu_layan_poli']['median'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $stats['waktu_tunggu_farmasi']['count'] > 0 ? $stats['waktu_tunggu_farmasi']['median'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $stats['waktu_layan_farmasi']['count'] > 0 ? $stats['waktu_layan_farmasi']['median'] . 'm' : '—' }}</td>
                        <td class="text-center font-bold text-rose-600">{{ $stats['total_waktu_rs']['count'] > 0 ? $stats['total_waktu_rs']['median'] . 'm' : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- SECTION 4: REGISTRY DETAIL PASIEN -->
        <div class="mt-8 page-break">
            <h3 class="text-[10px] font-bold uppercase tracking-wider text-slate-700 mb-3 border-l-4 border-rose-600 pl-2">IV. Log Rincian Durasi Pelayanan Pasien</h3>
            <table class="w-full text-[8px]">
                <thead>
                    <tr>
                        <th class="w-6 text-center">No</th>
                        <th>Kode Booking / No. Rawat</th>
                        <th>No. RM / Nama Pasien</th>
                        <th>Poliklinik</th>
                        <th class="text-center">Tunggu Poli</th>
                        <th class="text-center">Layan Poli</th>
                        <th class="text-center">Tunggu Farm.</th>
                        <th class="text-center">Layan Farm.</th>
                        <th class="text-center">Total RS</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @php $no = 1; @endphp
                    @foreach($analytics['patients'] as $patient)
                    <tr>
                        <td class="text-center">{{ $no++ }}</td>
                        <td>
                            <div class="font-bold text-slate-900">{{ $patient['kode_booking'] }}</div>
                            <div class="text-slate-500 text-[7px]">{{ $patient['no_rawat'] }}</div>
                        </td>
                        <td>
                            <div class="font-bold text-slate-900">{{ $patient['no_rkm_medis'] }}</div>
                            <div class="text-slate-700">{{ $patient['nm_pasien'] }}</div>
                        </td>
                        <td>{{ $patient['nm_poli'] }}</td>
                        <td class="text-center">{{ $patient['durations']['waktu_tunggu_poli'] !== null ? $patient['durations']['waktu_tunggu_poli'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $patient['durations']['waktu_layan_poli'] !== null ? $patient['durations']['waktu_layan_poli'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $patient['durations']['waktu_tunggu_farmasi'] !== null ? $patient['durations']['waktu_tunggu_farmasi'] . 'm' : '—' }}</td>
                        <td class="text-center">{{ $patient['durations']['waktu_layan_farmasi'] !== null ? $patient['durations']['waktu_layan_farmasi'] . 'm' : '—' }}</td>
                        <td class="text-center font-bold text-slate-800">{{ $patient['durations']['total_waktu_rs'] !== null ? $patient['durations']['total_waktu_rs'] . 'm' : '—' }}</td>
                        <td class="text-center font-semibold text-[7px]">{{ $patient['status'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- SIGNATURE SECTION -->
        <div class="mt-12 flex justify-end text-xs">
            <div class="text-center w-64">
                <p class="text-slate-500">Muntilan, {{ now()->translatedFormat('d F Y') }}</p>
                <p class="font-semibold mt-1">Kepala Bidang Pelayanan Medis,</p>
                <div class="h-16"></div>
                <p class="font-bold underline text-slate-950">......................................................</p>
                <p class="text-[9px] text-slate-500">RSU Aisyiyah Muntilan</p>
            </div>
        </div>

    </div>

    <!-- Auto Print Script -->
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            // Auto open print dialog if in print mode/view
            setTimeout(() => {
                window.print();
            }, 800);
        });
    </script>
</body>
</html>
