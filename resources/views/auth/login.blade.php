<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Antrol System</title>

    <!-- Google Fonts & Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <!-- Styles -->
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
        body {
            background: radial-gradient(circle at top right, #0f172a, #020617);
            overflow: hidden;
        }
        .glass {
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.08);
        }
        .input-glow:focus {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.5);
            border-color: rgba(59, 130, 246, 0.8);
        }
        .animate-fade-in {
            animation: fadeIn 0.8s ease-out forwards;
        }
        .animate-float {
            animation: float 6s ease-in-out infinite;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes float {
            0% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-15px) rotate(3deg); }
            100% { transform: translateY(0px) rotate(0deg); }
        }
    </style>
</head>
<body class="h-full font-sans antialiased text-slate-100 flex items-center justify-center relative p-4">
    <!-- Decorative background elements -->
    <div class="absolute w-96 h-96 bg-blue-600/10 rounded-full blur-3xl -top-20 -left-20 animate-float" style="animation-delay: -2s;"></div>
    <div class="absolute w-[500px] h-[500px] bg-indigo-600/15 rounded-full blur-3xl -bottom-32 -right-32 animate-float"></div>

    <div class="w-full max-w-md animate-fade-in z-10">
        <!-- Logo and Title -->
        <div class="text-center mb-8">
            <div class="inline-flex bg-blue-600 p-3.5 rounded-2xl text-white mb-4 shadow-lg shadow-blue-500/20 animate-float">
                <i class="fas fa-hospital-user text-3xl"></i>
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">Antrol System</h1>
            <p class="text-sm text-slate-400 mt-2">Masuk untuk mengakses monitoring dan integrasi BPJS</p>
        </div>

        <!-- Login Card -->
        <div class="glass p-8 rounded-3xl shadow-2xl relative overflow-hidden">
            <!-- Glass gloss line -->
            <div class="absolute top-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-white/20 to-transparent"></div>

            @if(session('success'))
                <div class="mb-6 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm flex items-start space-x-2">
                    <i class="fas fa-check-circle mt-0.5"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="mb-6 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm">
                    <div class="flex items-start space-x-2">
                        <i class="fas fa-exclamation-circle mt-0.5"></i>
                        <span class="font-medium">Login Gagal:</span>
                    </div>
                    <ul class="list-disc list-inside mt-2 space-y-1 pl-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf

                <!-- Username/NIK Input -->
                <div class="space-y-2">
                    <label for="username" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Username / NIK</label>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <input type="text" name="username" id="username" value="{{ old('username') }}" required autofocus
                            class="w-full bg-slate-900/60 border border-slate-700/60 rounded-xl py-3 pl-10 pr-4 text-sm text-slate-200 placeholder-slate-500 focus:outline-none input-glow transition-all duration-300"
                            placeholder="Masukkan NIK atau username">
                    </div>
                </div>

                <!-- Password Input -->
                <div class="space-y-2">
                    <div class="flex justify-between items-center">
                        <label for="password" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Password</label>
                    </div>
                    <div class="relative">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                            <i class="fas fa-key"></i>
                        </div>
                        <input type="password" name="password" id="password" required
                            class="w-full bg-slate-900/60 border border-slate-700/60 rounded-xl py-3 pl-10 pr-4 text-sm text-slate-200 placeholder-slate-500 focus:outline-none input-glow transition-all duration-300"
                            placeholder="••••••••">
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit"
                    class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-semibold py-3 px-4 rounded-xl shadow-lg shadow-blue-500/25 hover:shadow-blue-500/35 transition-all duration-300 transform active:scale-[0.98] flex items-center justify-center space-x-2">
                    <span>Masuk Aplikasi</span>
                    <i class="fas fa-arrow-right text-xs"></i>
                </button>
            </form>
        </div>

        <!-- Footer credit -->
        <p class="text-center text-xs text-slate-600 mt-8">
            &copy; {{ date('Y') }} crofean | Antrol System. All rights reserved.
        </p>
    </div>
</body>
</html>
