<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Antrol System | Dashboard</title>

    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Styles -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
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
        body {
            background: radial-gradient(circle at top right, #f8fafc, #eff6ff);
        }
        .dark body {
            background: radial-gradient(circle at top right, #0f172a, #020617);
        }
        .glass {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        .dark .glass {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-900 dark:text-slate-100 transition-colors duration-300">
    <div class="min-h-full flex flex-col">
        <!-- Navigation -->
        <nav class="glass sticky top-0 z-50 px-6 py-4">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <div class="flex items-center space-x-3">
                    <div class="bg-blue-600 p-2 rounded-xl text-white">
                        <i class="fas fa-hospital-user text-xl"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Antrol System</span>
                </div>

                <div class="flex items-center space-x-4">
                    <button onclick="document.documentElement.classList.toggle('dark')" class="p-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:block text-amber-400"></i>
                    </button>
                    @if (Route::has('login'))
                        <div class="hidden sm:flex items-center space-x-3">
                            @auth
                                <a href="{{ url('/dashboard') }}" class="text-sm font-semibold hover:text-blue-600 transition-colors">Dashboard</a>
                            @else
                                <a href="{{ route('login') }}" class="text-sm font-semibold hover:text-blue-600 transition-colors">Log in</a>
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="bg-blue-600 text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-blue-700 transition-all shadow-lg shadow-blue-500/20">Register</a>
                                @endif
                            @endauth
                        </div>
                    @endif
                </div>
            </div>
        </nav>

        <main class="flex-grow max-w-7xl mx-auto w-full px-6 py-12">
            <!-- Hero Section -->
            <div class="text-center mb-16 space-y-4">
                <h1 class="text-4xl sm:text-5xl lg:text-6xl font-extrabold tracking-tight">
                    Integrated Hospital <span class="text-blue-600">Antrean</span> System
                </h1>
                <p class="text-lg text-slate-600 dark:text-slate-400 max-w-2xl mx-auto">
                    A comprehensive platform for managing BPJS integration, patient registrations, and real-time operational logs.
                </p>
            </div>

            <!-- Menu Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <!-- Reg Periksa -->
                <a href="{{ route('regperiksa.index') }}" class="glass card-hover group p-8 rounded-3xl space-y-6">
                    <div class="w-14 h-14 rounded-2xl bg-blue-500/10 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-user-plus text-2xl"></i>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold tracking-tight">Pendaftaran Pasien</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            Monitor real-time patient registrations and manage hospital visitation status seamlessly.
                        </p>
                    </div>
                    <div class="flex items-center text-blue-600 font-semibold text-sm pt-4">
                        Explore Module <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

                <!-- Referensi MJKN -->
                <a href="{{ route('referensi.pendafataran') }}" class="glass card-hover group p-8 rounded-3xl space-y-6">
                    <div class="w-14 h-14 rounded-2xl bg-teal-500/10 flex items-center justify-center text-teal-600 group-hover:bg-teal-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-mobile-screen-button text-2xl"></i>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold tracking-tight">Referensi MJKN</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            Sync and manage Mobile JKN registration data directly with the hospital information system.
                        </p>
                    </div>
                    <div class="flex items-center text-teal-600 font-semibold text-sm pt-4">
                        Manage Sync <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

                <!-- Task ID Logs -->
                <a href="{{ route('taskid.logs') }}" class="glass card-hover group p-8 rounded-3xl space-y-6">
                    <div class="w-14 h-14 rounded-2xl bg-indigo-500/10 flex items-center justify-center text-indigo-600 group-hover:bg-indigo-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-list-check text-2xl"></i>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold tracking-tight">Task ID Logs</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            Track detailed antrean status changes and maintain transparency in BPJS task tracking.
                        </p>
                    </div>
                    <div class="flex items-center text-indigo-600 font-semibold text-sm pt-4">
                        View Logs <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

                <!-- BPJS Webservice Logs -->
                <a href="{{ route('bpjs-logs.index') }}" class="glass card-hover group p-8 rounded-3xl space-y-6">
                    <div class="w-14 h-14 rounded-2xl bg-rose-500/10 flex items-center justify-center text-rose-600 group-hover:bg-rose-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-server text-2xl"></i>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold tracking-tight">API Communications</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            Deep-dive into BPJS webservice logs to debug and ensure stable connectivity.
                        </p>
                    </div>
                    <div class="flex items-center text-rose-600 font-semibold text-sm pt-4">
                        Debug Services <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

                <!-- Flow Analytics & Monitoring -->
                <a href="{{ route('monitoring.index') }}" class="glass card-hover group p-8 rounded-3xl space-y-6">
                    <div class="w-14 h-14 rounded-2xl bg-blue-600/10 flex items-center justify-center text-blue-600 group-hover:bg-blue-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-chart-line text-2xl"></i>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold tracking-tight">Flow Analytics & Monitoring</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            Monitor real-time task durations, audit JKN timelines, analyze clinic performance, and identify operational bottlenecks.
                        </p>
                    </div>
                    <div class="flex items-center text-blue-600 font-semibold text-sm pt-4">
                        Open Monitoring <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>

                <!-- BPJS Command Runner -->
                <a href="{{ route('command.index') }}" class="glass card-hover group p-8 rounded-3xl space-y-6">
                    <div class="w-14 h-14 rounded-2xl bg-amber-500/10 flex items-center justify-center text-amber-600 group-hover:bg-amber-600 group-hover:text-white transition-all duration-300">
                        <i class="fas fa-terminal text-2xl"></i>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-xl font-bold tracking-tight">BPJS Command Terminal</h3>
                        <p class="text-slate-500 dark:text-slate-400 text-sm leading-relaxed">
                            Execute administrative commands to update master data, doctor schedules, and antrean settings.
                        </p>
                    </div>
                    <div class="flex items-center text-amber-600 font-semibold text-sm pt-4">
                        Open Terminal <i class="fas fa-arrow-right ml-2 group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>
            </div>
        </main>

        <footer class="mt-auto border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 py-12 px-6">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="flex items-center space-x-2 opacity-50">
                    <i class="fas fa-shield-halved"></i>
                    <span class="text-sm font-medium tracking-wide">ANTROL SYSTEM CORE v1.0</span>
                </div>
                <div class="text-slate-500 text-sm">
                    &copy; {{ date('Y') }} crofean |  Antrol System. All rights reserved.
                </div>
                <div class="flex space-x-6 text-slate-400">
                    <a href="https://laravel.com" class="hover:text-rose-500 transition-colors"><i class="fab fa-laravel text-lg"></i></a>
                    <a href="#" class="hover:text-blue-500 transition-colors"><i class="fab fa-github text-lg"></i></a>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
