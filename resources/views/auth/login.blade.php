<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OPPKPKE Sistem Pengentasan Kemiskinan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { height: 100%; }
        body { margin: 0; overflow: hidden; }

        /* ── Slideshow background: 3 foto crossfade + zoom halus (Ken Burns) ── */
        .bg-slide {
            position: absolute; inset: 0;
            background-size: cover;
            background-position: center;
            opacity: 0;
            animation: bgFade 18s infinite;
            will-change: opacity, transform;
        }
        .bg-slide:nth-child(1) { background-image: url('{{ asset('background_kotabaru.png') }}'); animation-delay: 0s; }
        .bg-slide:nth-child(2) { background-image: url('{{ asset('kantor_bupati_kotabaru.jpg') }}'); animation-delay: 6s; }
        .bg-slide:nth-child(3) { background-image: url('{{ asset('samber_gelap_kotabaru.jpeg') }}'); animation-delay: 12s; }

        @keyframes bgFade {
            0%   { opacity: 0; transform: scale(1); }
            4%   { opacity: 1; }
            28%  { opacity: 1; transform: scale(1.06); }
            33%  { opacity: 0; transform: scale(1.08); }
            100% { opacity: 0; }
        }

        /* ── Masuk halus untuk konten (logo, judul, kartu login) ── */
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .fade-up { animation: fadeUp 0.7s ease-out both; }
    </style>
</head>
<body class="relative min-h-screen w-full text-white">

    {{-- ── SLIDESHOW BACKGROUND ── --}}
    <div class="absolute inset-0 z-0 bg-slate-800">
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
        <div class="bg-slide"></div>
    </div>

    {{-- ── OVERLAY GELAP (biar teks & kartu tetap kebaca) ── --}}
    <div class="absolute inset-0 z-10" style="background: linear-gradient(120deg, rgba(15,23,42,.75) 0%, rgba(15,23,42,.55) 45%, rgba(15,23,42,.35) 100%);"></div>

    {{-- ── KONTEN ── --}}
    <div class="relative z-20 min-h-screen flex flex-col lg:flex-row items-center justify-between gap-10 px-6 sm:px-10 lg:px-20 py-10 overflow-y-auto">

        {{-- Kiri: Logo + Judul --}}
        <div class="fade-up max-w-lg text-center lg:text-left" style="animation-delay:.05s">
            <img src="{{ asset('Logo_app_new.png') }}" alt="Logo OPPKPKE" class="w-36 sm:w-40 h-auto mx-auto lg:mx-0 mb-5 drop-shadow-lg">
            <h1 class="text-4xl sm:text-5xl font-extrabold leading-tight tracking-tight">
                SISTEM INFORMASI<br>PENGENTASAN KEMISKINAN
            </h1>
            <p class="text-slate-200 text-lg sm:text-xl mt-3 font-medium">Kabupaten Kotabaru</p>
        </div>

        {{-- Kanan: Kartu Login (glass) --}}
        <div class="fade-up w-full max-w-md" style="animation-delay:.15s">
            <div class="bg-white/10 border border-white/20 backdrop-blur-xl rounded-2xl shadow-2xl p-7 sm:p-8">
                <h2 class="text-2xl font-bold text-white">Login</h2>
                <p class="text-slate-300 text-sm mt-1 mb-6">Gunakan akun yang terdaftar di sistem OPPKPKE.</p>

                {{-- Flash Success --}}
                @if (session('success'))
                    <div class="mb-4 bg-green-500/15 border border-green-400/40 text-green-200 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                        <i class="fas fa-check-circle"></i>
                        {{ session('success') }}
                    </div>
                @endif

                {{-- Error Global --}}
                @if ($errors->any())
                    <div class="mb-4 bg-red-500/15 border border-red-400/40 text-red-200 px-4 py-3 rounded-lg text-sm">
                        <div class="flex items-center gap-2 font-medium mb-1">
                            <i class="fas fa-exclamation-circle"></i>
                            Login gagal
                        </div>
                        @foreach ($errors->all() as $error)
                            <p class="text-red-200/90">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                    @csrf

                    {{-- Email --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-200 mb-1.5">Email</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   placeholder="Masukkan Email"
                                   autocomplete="email"
                                   required
                                   class="w-full pl-11 pr-4 py-2.5 bg-white/10 border border-white/20 rounded-lg text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition text-sm @error('email') border-red-400 @enderror">
                        </div>
                    </div>

                    {{-- Password --}}
                    <div>
                        <label class="block text-sm font-medium text-slate-200 mb-1.5">Password</label>
                        <div class="relative">
                            <i class="fas fa-lock absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="password"
                                   id="password"
                                   name="password"
                                   placeholder="Masukkan Password"
                                   autocomplete="current-password"
                                   required
                                   class="w-full pl-11 pr-11 py-2.5 bg-white/10 border border-white/20 rounded-lg text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-400 focus:border-blue-400 transition text-sm">
                            <button type="button"
                                    onclick="togglePassword()"
                                    class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white transition">
                                <i class="fas fa-eye" id="eye-icon"></i>
                            </button>
                        </div>
                    </div>

                    {{-- Remember Me --}}
                    <div class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" class="w-4 h-4 rounded border-white/30 bg-white/10 text-blue-500 focus:ring-blue-400">
                        <label for="remember" class="ml-2 text-sm text-slate-300">Ingat saya</label>
                    </div>

                    {{-- Submit --}}
                    <button type="submit"
                            class="w-full text-white font-semibold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl hover:-translate-y-0.5"
                            style="background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 55%, #0ea5e9 100%);">
                        <i class="fas fa-arrow-right-to-bracket"></i>
                        Masuk
                    </button>
                </form>
            </div>

            <p class="text-center text-slate-300 text-xs mt-4">
                Pastikan Anda menggunakan perangkat tepercaya. Jangan bagikan kredensial Anda.
            </p>
        </div>
    </div>

    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon  = document.getElementById('eye-icon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        }
    </script>
</body>
</html>
