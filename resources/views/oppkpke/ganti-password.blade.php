@extends('layouts.oppkpke')

@section('title', 'Ganti Password')
@section('page-title', 'Ganti Password')
@section('page-subtitle', 'Perbarui password akun Anda')

@section('content')

<div class="max-w-lg mx-auto">
    <div class="bg-white rounded-2xl border shadow-sm overflow-hidden">

        {{-- Header --}}
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-5 flex items-center gap-3">
            <div class="w-11 h-11 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0">
                <i class="fas fa-key text-white text-lg"></i>
            </div>
            <div>
                <h2 class="text-white font-bold text-base">Ganti Password</h2>
                <p class="text-blue-200 text-xs mt-0.5">{{ auth()->user()->email }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('oppkpke.profile.change-password.update') }}" class="p-6 space-y-5">
            @csrf

            @if(session('success'))
            <div class="flex items-start gap-3 bg-green-50 border border-green-200 rounded-xl px-4 py-3">
                <i class="fas fa-check-circle text-green-500 mt-0.5 flex-shrink-0"></i>
                <p class="text-sm text-green-700 font-medium">{{ session('success') }}</p>
            </div>
            @endif

            @if($errors->any())
            <div class="flex items-start gap-3 bg-red-50 border border-red-200 rounded-xl px-4 py-3">
                <i class="fas fa-exclamation-circle text-red-500 mt-0.5 flex-shrink-0"></i>
                <ul class="text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
            </div>
            @endif

            {{-- Password Saat Ini --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password Saat Ini <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" name="current_password" id="currentPwd"
                           autocomplete="current-password"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('current_password') border-red-400 bg-red-50 @enderror">
                    <button type="button" onclick="togglePwd('currentPwd','eyeCur')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i id="eyeCur" class="fas fa-eye text-sm"></i>
                    </button>
                </div>
            </div>

            {{-- Password Baru --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Password Baru <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" name="new_password" id="newPwd"
                           autocomplete="new-password"
                           placeholder="Min. 8 karakter, huruf + angka"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 @error('new_password') border-red-400 bg-red-50 @enderror">
                    <button type="button" onclick="togglePwd('newPwd','eyeNew')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i id="eyeNew" class="fas fa-eye text-sm"></i>
                    </button>
                </div>
                {{-- Strength indicator --}}
                <div class="mt-2 flex gap-1" id="strengthBars">
                    <div class="h-1 flex-1 rounded bg-gray-200" id="bar1"></div>
                    <div class="h-1 flex-1 rounded bg-gray-200" id="bar2"></div>
                    <div class="h-1 flex-1 rounded bg-gray-200" id="bar3"></div>
                    <div class="h-1 flex-1 rounded bg-gray-200" id="bar4"></div>
                </div>
                <p class="text-xs text-gray-400 mt-1" id="strengthLabel">Masukkan password baru</p>
            </div>

            {{-- Konfirmasi Password --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                    Konfirmasi Password Baru <span class="text-red-500">*</span>
                </label>
                <div class="relative">
                    <input type="password" name="new_password_confirmation" id="confirmPwd"
                           autocomplete="new-password"
                           placeholder="Ulangi password baru"
                           class="w-full border border-gray-300 rounded-xl px-4 py-2.5 pr-10 text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <button type="button" onclick="togglePwd('confirmPwd','eyeConf')"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                        <i id="eyeConf" class="fas fa-eye text-sm"></i>
                    </button>
                </div>
                <p class="text-xs mt-1 hidden" id="matchHint"></p>
            </div>

            {{-- Buttons --}}
            <div class="flex gap-3 pt-1">
                <a href="{{ route('oppkpke.dashboard') }}"
                   class="flex-1 text-center border border-gray-300 text-gray-700 py-2.5 rounded-xl text-sm font-medium hover:bg-gray-50 transition">
                    Batal
                </a>
                <button type="submit"
                        class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2.5 rounded-xl text-sm font-semibold transition flex items-center justify-center gap-2">
                    <i class="fas fa-save"></i> Simpan Password
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
function togglePwd(inputId, iconId) {
    var inp  = document.getElementById(inputId);
    var icon = document.getElementById(iconId);
    inp.type = inp.type === 'password' ? 'text' : 'password';
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

document.getElementById('newPwd').addEventListener('input', function() {
    var v = this.value;
    var score = 0;
    if (v.length >= 8)  score++;
    if (/[a-zA-Z]/.test(v)) score++;
    if (/[0-9]/.test(v)) score++;
    if (v.length >= 12) score++;

    var colors = ['bg-red-400','bg-orange-400','bg-yellow-400','bg-green-500'];
    var labels = ['Lemah','Cukup','Baik','Kuat'];
    var bars   = ['bar1','bar2','bar3','bar4'];

    bars.forEach(function(id, i) {
        var el = document.getElementById(id);
        el.className = 'h-1 flex-1 rounded ' + (i < score ? colors[score - 1] : 'bg-gray-200');
    });

    var lbl = document.getElementById('strengthLabel');
    lbl.textContent = v.length === 0 ? 'Masukkan password baru' : labels[score - 1] || 'Lemah';
    lbl.className   = 'text-xs mt-1 ' + (['text-red-500','text-orange-500','text-yellow-600','text-green-600'][score - 1] || 'text-gray-400');

    checkMatch();
});

document.getElementById('confirmPwd').addEventListener('input', checkMatch);

function checkMatch() {
    var newPwd  = document.getElementById('newPwd').value;
    var confPwd = document.getElementById('confirmPwd').value;
    var hint    = document.getElementById('matchHint');
    if (!confPwd) { hint.classList.add('hidden'); return; }
    hint.classList.remove('hidden');
    if (newPwd === confPwd) {
        hint.textContent = '✓ Password cocok';
        hint.className   = 'text-xs mt-1 text-green-600';
    } else {
        hint.textContent = '✗ Password tidak cocok';
        hint.className   = 'text-xs mt-1 text-red-500';
    }
}
</script>
@endpush
