<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="referrer" content="strict-origin-when-cross-origin">
    <meta name="robots" content="noindex, nofollow">
    <title>Login - OPPKPKE Sistem Pengentasan Kemiskinan</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        html, body { min-height: 100%; }
        /* overflow-x disembunyikan (Ken Burns zoom), tapi vertikal boleh scroll di mobile */
        body { margin: 0; overflow-x: hidden; }
        /* Honeypot anti-bot: tak terlihat pengguna & pembaca layar, tapi diisi bot */
        .hp-field { position: absolute !important; left: -9999px !important; top: -9999px !important;
                    width: 1px; height: 1px; opacity: 0; pointer-events: none; }

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
    <div class="relative z-20 min-h-screen flex flex-col lg:flex-row items-center justify-center lg:justify-between gap-8 sm:gap-10 px-5 sm:px-10 lg:px-20 py-8 sm:py-10 overflow-y-auto">

        {{-- Kiri: Logo + Judul --}}
        <div class="fade-up max-w-lg text-center lg:text-left" style="animation-delay:.05s">
            <img src="{{ asset('Logo_app_new.png') }}" alt="Logo OPPKPKE" class="w-24 sm:w-32 lg:w-40 h-auto mx-auto lg:mx-0 mb-4 sm:mb-5 drop-shadow-lg">
            <h1 class="text-2xl sm:text-4xl lg:text-5xl font-extrabold leading-tight tracking-tight">
                SISTEM INFORMASI<br>PENGENTASAN KEMISKINAN
            </h1>
            <p class="text-slate-200 text-base sm:text-lg lg:text-xl mt-2 sm:mt-3 font-medium">Kabupaten Kotabaru</p>
        </div>

        {{-- Kanan: Kartu Login (glass) --}}
        <div class="fade-up w-full max-w-md flex-shrink-0" style="animation-delay:.15s">
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

                <form method="POST" action="{{ route('login.post') }}" class="space-y-5" id="loginForm" novalidate>
                    @csrf

                    {{-- Honeypot anti-bot (harus tetap kosong; diisi = ditolak server) --}}
                    <div class="hp-field" aria-hidden="true">
                        <label>Jangan isi kolom ini
                            <input type="text" name="website" tabindex="-1" autocomplete="off">
                        </label>
                    </div>

                    {{-- Email --}}
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-200 mb-1.5">Email</label>
                        <div class="relative">
                            <i class="fas fa-envelope absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email') }}"
                                   placeholder="Masukkan Email"
                                   autocomplete="username"
                                   inputmode="email"
                                   autocapitalize="none"
                                   autocorrect="off"
                                   spellcheck="false"
                                   maxlength="150"
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
                                   maxlength="200"
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
                    <button type="submit" id="loginSubmit"
                            class="w-full text-white font-semibold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center gap-2 shadow-lg hover:shadow-xl hover:-translate-y-0.5 disabled:opacity-70 disabled:cursor-not-allowed"
                            style="background: linear-gradient(90deg, #2563eb 0%, #1d4ed8 55%, #0ea5e9 100%);">
                        <i class="fas fa-arrow-right-to-bracket" id="loginIcon"></i>
                        <span id="loginLabel">Masuk</span>
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

        // Anti double-submit + feedback (cegah pengiriman ganda / spam klik)
        (function () {
            var form = document.getElementById('loginForm');
            form.addEventListener('submit', function (e) {
                if (!form.email.value.trim() || !form.password.value) return; // biarkan required native
                if (form.dataset.submitting === '1') { e.preventDefault(); return; }
                form.dataset.submitting = '1';
                var btn = document.getElementById('loginSubmit');
                btn.disabled = true;
                document.getElementById('loginIcon').className = 'fas fa-spinner fa-spin';
                document.getElementById('loginLabel').textContent = 'Memproses...';
            });
        }());
    </script>
</body>
</html>
