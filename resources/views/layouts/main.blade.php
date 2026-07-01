<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Antrol System') | Antrol System</title>
    
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
            background: rgba(15, 23, 42, 0.8);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1);
        }
        
        /* Custom scrollbar for better look in dark mode */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        ::-webkit-scrollbar-thumb {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
        }
        .dark ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }
    </style>
    @stack('styles')
</head>
<body class="h-full font-sans antialiased text-slate-900 dark:text-slate-100 transition-colors duration-300">
    <div class="min-h-full flex flex-col">
        <!-- Navigation -->
        <nav class="glass sticky top-0 z-50 px-6 py-4">
            <div class="max-w-7xl mx-auto flex justify-between items-center">
                <a href="{{ url('/') }}" class="flex items-center space-x-3 group">
                    <div class="bg-blue-600 p-2 rounded-xl text-white group-hover:scale-110 transition-transform">
                        <i class="fas fa-hospital-user text-xl"></i>
                    </div>
                    <span class="text-xl font-bold tracking-tight bg-gradient-to-r from-blue-600 to-indigo-600 bg-clip-text text-transparent">Antrol System</span>
                </a>
                
                <div class="flex items-center space-x-2 md:space-x-4">
                    <!-- Quick Links (Desktop) -->
                    <div class="hidden lg:flex items-center space-x-1">
                        <a href="{{ route('regperiksa.index') }}" class="px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors {{ request()->routeIs('regperiksa.*') ? 'text-blue-600 dark:text-blue-400' : '' }}">Pasien</a>
                        <a href="{{ route('referensi.pendafataran') }}" class="px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors {{ request()->routeIs('referensi.*') ? 'text-blue-600 dark:text-blue-400' : '' }}">Sinkronisasi</a>
                        <a href="{{ route('taskid.logs') }}" class="px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors {{ request()->routeIs('taskid.*') ? 'text-blue-600 dark:text-blue-400' : '' }}">Logs</a>
                        <a href="{{ route('monitoring.index') }}" class="px-4 py-2 rounded-xl text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors {{ request()->routeIs('monitoring.*') ? 'text-blue-600 dark:text-blue-400' : '' }}">Monitoring</a>
                    </div>

                    <div class="h-6 w-px bg-slate-200 dark:bg-slate-700 hidden lg:block"></div>

                    <button onclick="document.documentElement.classList.toggle('dark')" class="p-2.5 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-colors">
                        <i class="fas fa-moon dark:hidden"></i>
                        <i class="fas fa-sun hidden dark:block text-amber-400"></i>
                    </button>
                    
                    @auth
                        <div class="hidden sm:flex items-center space-x-3">
                             <a href="{{ url('/dashboard') }}" class="text-sm font-semibold hover:text-blue-600 transition-colors">Dashboard</a>
                        </div>
                    @endauth
                </div>
            </div>
        </nav>

        <main class="flex-grow">
            @yield('content')
        </main>

        <footer class="border-t border-slate-200 dark:border-slate-800 bg-white/50 dark:bg-slate-900/50 py-12 px-6">
            <div class="max-w-7xl mx-auto flex flex-col md:flex-row justify-between items-center space-y-4 md:space-y-0">
                <div class="flex items-center space-x-2 opacity-50">
                    <i class="fas fa-shield-halved"></i>
                    <span class="text-sm font-medium tracking-wide uppercase">Antrol System Core</span>
                </div>
                <div class="text-slate-500 text-sm">
                    &copy; {{ date('Y') }} crofean |  Antrol System. All rights reserved.
                </div>
                <div class="flex space-x-6 text-slate-400">
                    <a href="#" class="hover:text-blue-500 transition-colors"><i class="fab fa-github text-lg"></i></a>
                </div>
            </div>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script>
        // Set axios default headers for CSRF
        axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
        let token = document.head.querySelector('meta[name="csrf-token"]');
        if (token) {
            axios.defaults.headers.common['X-CSRF-TOKEN'] = token.content;
        }
    </script>
    @stack('scripts')
</body>
</html>
