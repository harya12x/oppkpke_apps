@extends('layouts.oppkpke')

@section('title', 'Chat Support')
@section('page-title', $isMaster ? 'Pantau Chat Support' : ($isItTeam ? 'Inbox Support' : 'Chat Support IT'))
@section('page-subtitle', $isMaster ? 'Mode pantau — seluruh percakapan (hanya-baca)' : ($isItTeam ? 'Percakapan bantuan dari seluruh Operator Daerah' : 'Ajukan pertanyaan atau kendala ke Tim IT'))

@section('content')
@php $staffView = $isItTeam || $isMaster; @endphp
<div class="max-w-4xl mx-auto">

    @if($isMaster)
        <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-800 text-xs rounded-lg px-3 py-2 flex items-center gap-2">
            <i class="fas fa-eye"></i> Mode pantau: Anda dapat melihat seluruh percakapan namun tidak dapat membalas atau mengubah status.
        </div>
    @endif

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        @if($isDaerah)
            <button type="button" onclick="document.getElementById('newChatModal').classList.remove('hidden'); document.getElementById('newChatModal').classList.add('flex');"
                    class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2.5 rounded-lg transition shadow-sm">
                <i class="fas fa-plus"></i> Percakapan Baru
            </button>
        @endif

        <form method="GET" action="{{ route('oppkpke.chat.index') }}" class="flex flex-1 flex-wrap items-center gap-2 justify-end">
            @if($staffView)
            <div class="relative flex-1 min-w-[180px] max-w-xs">
                <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Cari subjek / operator..."
                       class="w-full pl-8 pr-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            @endif
            <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg text-sm px-3 py-2 bg-white focus:ring-2 focus:ring-blue-500">
                <option value="">Semua Status</option>
                @foreach(['open' => 'Terbuka', 'pending' => 'Menunggu', 'resolved' => 'Selesai', 'closed' => 'Ditutup'] as $val => $lbl)
                    <option value="{{ $val }}" {{ ($filters['status'] ?? '') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @if($staffView)
            <button type="submit" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm px-3 py-2 rounded-lg transition">
                <i class="fas fa-filter"></i>
            </button>
            @endif
        </form>
    </div>

    {{-- Conversation list --}}
    <div class="bg-white rounded-xl border shadow-sm divide-y divide-gray-100 overflow-hidden">
        @forelse($conversations as $c)
            @php $unread = isset($unreadIds[$c->id]); @endphp
            <a href="{{ route('oppkpke.chat.show', $c) }}"
               class="flex items-start gap-3 px-4 py-3.5 hover:bg-gray-50 transition {{ $unread ? 'bg-blue-50/40' : '' }}">
                <div class="w-10 h-10 rounded-full flex items-center justify-center flex-shrink-0 font-bold text-sm
                            {{ $staffView ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                    @if($staffView)
                        {{ strtoupper(substr($c->initiator->name ?? 'U', 0, 1)) }}
                    @else
                        <i class="fas fa-headset"></i>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <p class="font-semibold text-gray-800 truncate {{ $unread ? 'font-bold' : '' }}">
                            {{ $c->subject ?: 'Percakapan Support' }}
                        </p>
                        @if($unread)
                            <span class="w-2 h-2 rounded-full bg-red-500 flex-shrink-0"></span>
                        @endif
                    </div>
                    @if($staffView)
                        <p class="text-xs text-gray-500 truncate">
                            <i class="fas fa-user text-gray-400 mr-1"></i>{{ $c->initiator->name ?? '—' }}
                            @if($c->perangkatDaerah)
                                · {{ $c->perangkatDaerah->singkatan ?? $c->perangkatDaerah->nama }}
                            @endif
                        </p>
                    @endif
                    <p class="text-xs text-gray-400 mt-0.5 truncate">
                        {{ $c->latestMessage?->body ? \Illuminate\Support\Str::limit(strip_tags($c->latestMessage->body), 60) : 'Belum ada pesan' }}
                    </p>
                </div>
                <div class="flex flex-col items-end gap-1 flex-shrink-0">
                    @include('chat._status_badge', ['status' => $c->status, 'label' => $c->status_label])
                    <span class="text-[11px] text-gray-400">
                        {{ optional($c->last_message_at ?? $c->created_at)->diffForHumans(null, true) }}
                    </span>
                </div>
            </a>
        @empty
            <div class="py-16 text-center text-gray-400">
                <i class="fas fa-comments text-4xl mb-3 block"></i>
                <p class="font-medium">Belum ada percakapan</p>
                @if($isDaerah)
                    <p class="text-sm mt-1">Mulai percakapan baru untuk menghubungi Tim IT.</p>
                @endif
            </div>
        @endforelse
    </div>

    <div class="mt-4">
        {{ $conversations->links() }}
    </div>
</div>

{{-- Modal: percakapan baru (hanya Operator Daerah) --}}
@if($isDaerah)
<div id="newChatModal" class="fixed inset-0 z-[200] hidden items-center justify-center p-4">
    <div class="absolute inset-0 bg-black/60" onclick="this.parentElement.classList.add('hidden'); this.parentElement.classList.remove('flex');"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg z-10 overflow-hidden">
        <div class="px-5 py-4 border-b flex items-center justify-between">
            <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                <i class="fas fa-headset text-blue-500"></i> Percakapan Baru ke Tim IT
            </h3>
            <button onclick="document.getElementById('newChatModal').classList.add('hidden'); document.getElementById('newChatModal').classList.remove('flex');"
                    class="text-gray-400 hover:text-gray-600"><i class="fas fa-times text-lg"></i></button>
        </div>
        <form method="POST" action="{{ route('oppkpke.chat.store') }}" class="p-5 space-y-4" enctype="multipart/form-data">
            @csrf
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Subjek (opsional)</label>
                <input type="text" name="subject" maxlength="160" placeholder="Contoh: Tidak bisa input laporan"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Prioritas</label>
                <select name="priority" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500">
                    <option value="normal">Normal</option>
                    <option value="low">Rendah</option>
                    <option value="high">Tinggi</option>
                    <option value="urgent">Mendesak</option>
                </select>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Pesan <span class="text-red-500">*</span></label>
                <textarea name="body" rows="4" maxlength="5000" placeholder="Jelaskan kendala Anda..."
                          class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">{{ old('body') }}</textarea>
            </div>
            <div>
                <label class="text-xs font-medium text-gray-600 block mb-1">Lampiran (opsional)</label>
                <input type="file" name="attachment"
                       accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                       class="w-full text-sm text-gray-600 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100">
                <p class="text-[11px] text-gray-400 mt-1">Maks 5 MB — gambar, PDF, dokumen Office, teks.</p>
            </div>
            @error('body')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            @error('attachment')<p class="text-xs text-red-500">{{ $message }}</p>@enderror
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" onclick="document.getElementById('newChatModal').classList.add('hidden'); document.getElementById('newChatModal').classList.remove('flex');"
                        class="px-4 py-2 text-sm text-gray-600 hover:bg-gray-100 rounded-lg transition">Batal</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                    <i class="fas fa-paper-plane mr-1"></i> Kirim
                </button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@push('scripts')
<script>
    @if($errors->has('body'))
        document.getElementById('newChatModal').classList.remove('hidden');
        document.getElementById('newChatModal').classList.add('flex');
    @endif
</script>
@endpush
