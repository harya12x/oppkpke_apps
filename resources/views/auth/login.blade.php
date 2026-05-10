<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - OPPKPKE Sistem Pengentasan Kemiskinan</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1e3a5f 0%, #2563eb 50%, #1e40af 100%); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center px-4">

    <div class="w-full max-w-md">
        {{-- Logo / Header --}}
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 backdrop-blur rounded-2xl mb-4">
                <i class="fas fa-hand-holding-heart text-4xl text-white"></i>
            </div>
            <h1 class="text-3xl font-bold text-white">OPPKPKE</h1>
            <p class="text-blue-200 mt-1">Sistem Pengentasan Kemiskinan</p>
            <p class="text-blue-300 text-sm mt-1">Kabupaten Kotabaru</p>
        </div>

        {{-- Card Login --}}
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-xl font-semibold text-gray-800 mb-6 text-center">Masuk ke Sistem</h2>

            {{-- Flash Success --}}
            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm flex items-center gap-2">
                    <i class="fas fa-check-circle"></i>
                    {{ session('success') }}
                </div>
            @endif

            {{-- Error Global --}}
            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg text-sm">
                    <div class="flex items-center gap-2 font-medium mb-1">
                        <i class="fas fa-exclamation-circle"></i>
                        Login gagal
                    </div>
                    @foreach ($errors->all() as $error)
                        <p class="text-red-600">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('login.post') }}" class="space-y-5">
                @csrf

                {{-- Email --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-envelope mr-1 text-gray-400"></i> Email
                    </label>
                    <input type="email"
                           name="email"
                           value="{{ old('email') }}"
                           placeholder="contoh@email.com"
                           autocomplete="email"
                           required
                           class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm @error('email') border-red-400 @enderror">
                </div>

                {{-- Password --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">
                        <i class="fas fa-lock mr-1 text-gray-400"></i> Password
                    </label>
                    <div class="relative">
                        <input type="password"
                               id="password"
                               name="password"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               required
                               class="w-full px-4 py-2.5 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition text-sm pr-10">
                        <button type="button"
                                onclick="togglePassword()"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <i class="fas fa-eye" id="eye-icon"></i>
                        </button>
                    </div>
                </div>

                {{-- Remember Me --}}
                <div class="flex items-center">
                    <input type="checkbox" name="remember" id="remember" class="w-4 h-4 text-blue-600 rounded border-gray-300">
                    <label for="remember" class="ml-2 text-sm text-gray-600">Ingat saya</label>
                </div>

                {{-- Submit --}}
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2.5 px-4 rounded-lg transition duration-200 flex items-center justify-center gap-2">
                    <i class="fas fa-sign-in-alt"></i>
                    Masuk
                </button>
            </form>

            {{-- Info roles --}}
            <div class="mt-6 p-4 bg-blue-50 rounded-lg border border-blue-100">
                <p class="text-xs text-blue-700 font-semibold mb-2">
                    <i class="fas fa-info-circle mr-1"></i> Informasi Akun
                </p>
                <ul class="text-xs text-blue-600 space-y-1">
                    <li><i class="fas fa-user-shield mr-1"></i><strong>Admin Master:</strong> Akses penuh ke semua data & dashboard</li>
                    <li><i class="fas fa-user mr-1"></i><strong>Operator Daerah:</strong> Input laporan untuk perangkat daerah sendiri</li>
                </ul>
                <p class="text-xs text-blue-500 mt-2">Hubungi administrator jika lupa password.</p>
            </div>
        </div>

        <p class="text-center text-blue-200 text-xs mt-6">
            &copy; {{ date('Y') }} OPPKPKE &mdash; Sistem Pengentasan Kemiskinan Kabupaten Kotabaru
        </p>
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
