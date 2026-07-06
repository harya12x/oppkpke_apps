@extends('layouts.oppkpke')

@section('title', 'Percakapan')
@section('page-title', $conversation->subject ?: 'Percakapan Support')
@section('page-subtitle', $isItTeam
    ? (($conversation->initiator->name ?? '—') . ($conversation->perangkatDaerah ? ' · ' . ($conversation->perangkatDaerah->singkatan ?? $conversation->perangkatDaerah->nama) : ''))
    : 'Tim IT OPPKPKE')

@section('content')
@php $meId = auth()->id(); @endphp
<div class="max-w-3xl mx-auto flex flex-col" style="height: calc(100vh - 9rem); height: calc(100dvh - 9rem);">

    {{-- Header --}}
    <div class="bg-white rounded-t-xl border border-b-0 px-4 py-3 flex items-center gap-3">
        <a href="{{ route('oppkpke.chat.index') }}" class="text-gray-400 hover:text-gray-700 p-1">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div class="flex-1 min-w-0">
            <p class="font-semibold text-gray-800 truncate">{{ $conversation->subject ?: 'Percakapan Support' }}</p>
            <div class="flex items-center gap-2 mt-0.5">
                @include('chat._status_badge', ['status' => $conversation->status, 'label' => $conversation->status_label])
                @if($conversation->priority !== 'normal')
                    <span class="text-[10px] font-semibold text-gray-500 uppercase">{{ $conversation->priority }}</span>
                @endif
            </div>
        </div>

        @if($isItTeam)
        <form method="POST" action="{{ route('oppkpke.chat.status', $conversation) }}" class="flex-shrink-0">
            @csrf
            @method('PATCH')
            <select name="status" onchange="this.form.submit()"
                    class="border border-gray-300 rounded-lg text-xs px-2 py-1.5 bg-white focus:ring-2 focus:ring-blue-500">
                @foreach(['open' => 'Terbuka', 'pending' => 'Menunggu', 'resolved' => 'Selesai', 'closed' => 'Ditutup'] as $val => $lbl)
                    <option value="{{ $val }}" {{ $conversation->status === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </form>
        @endif
    </div>

    {{-- Messages --}}
    <div id="chat-messages" class="flex-1 overflow-y-auto bg-gray-50 border-x px-4 py-4 space-y-3">
        <div id="load-older-wrap" class="text-center {{ $hasMore ? '' : 'hidden' }}">
            <button type="button" id="load-older"
                    class="text-xs text-blue-600 hover:text-blue-800 bg-white border rounded-full px-4 py-1.5 shadow-sm transition">
                <i class="fas fa-arrow-up mr-1"></i> Muat pesan lama
            </button>
        </div>
        <div id="messages-anchor"></div>
        @foreach($messages as $m)
            @include('chat._bubble', ['m' => $m, 'meId' => $meId])
        @endforeach
    </div>

    {{-- Composer --}}
    <div class="bg-white rounded-b-xl border border-t-0 p-3">
        @if($isMaster)
            <p class="text-center text-sm text-gray-400 py-2">
                <i class="fas fa-eye mr-1"></i> Mode pantau — hanya-baca. Admin Master tidak dapat membalas percakapan.
            </p>
        @elseif($conversation->is_closed && $isItTeam)
            <p class="text-center text-sm text-gray-400 py-2">
                <i class="fas fa-lock mr-1"></i> Percakapan sudah {{ strtolower($conversation->status_label) }}.
                Ubah status ke "Terbuka" untuk membalas.
            </p>
        @else
        @if($conversation->is_closed)
            <p class="text-center text-xs text-amber-600 mb-2">
                <i class="fas fa-rotate-left mr-1"></i> Percakapan sudah {{ strtolower($conversation->status_label) }} — membalas akan membukanya kembali.
            </p>
        @endif
        {{-- Preview lampiran terpilih --}}
        <div id="attach-preview" class="hidden items-center gap-2 mb-2 text-xs bg-gray-50 border rounded-lg px-3 py-2">
            <i class="fas fa-paperclip text-gray-400"></i>
            <span id="attach-name" class="flex-1 truncate text-gray-600"></span>
            <button type="button" id="attach-clear" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
        </div>
        <form id="chat-form" class="flex items-end gap-2" enctype="multipart/form-data">
            @csrf
            <label for="chat-file" title="Lampirkan file"
                   class="flex-shrink-0 w-11 h-11 rounded-full border border-gray-300 text-gray-500 hover:bg-gray-50 flex items-center justify-center cursor-pointer transition">
                <i class="fas fa-paperclip"></i>
            </label>
            <input type="file" id="chat-file" name="attachment" class="hidden"
                   accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.csv,.txt">
            <textarea id="chat-input" name="body" rows="1" maxlength="5000"
                      placeholder="Tulis pesan..."
                      class="flex-1 resize-none px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:ring-2 focus:ring-blue-500 max-h-32"></textarea>
            <button type="submit" id="chat-send"
                    class="flex-shrink-0 w-11 h-11 bg-blue-600 hover:bg-blue-700 text-white rounded-full flex items-center justify-center transition">
                <i class="fas fa-paper-plane"></i>
            </button>
        </form>
        @endif
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var meId        = {{ (int) $meId }};
    var canModerate = @json($meCanModerate ?? false);
    var box         = document.getElementById('chat-messages');
    var form        = document.getElementById('chat-form');
    var pollUrl     = "{{ route('oppkpke.chat.poll', $conversation) }}";
    var histUrl     = "{{ route('oppkpke.chat.history', $conversation) }}";
    var sendUrl     = "{{ route('oppkpke.chat.message', $conversation) }}";
    var msgBase     = "{{ route('oppkpke.chat.message', $conversation) }}"; // .../messages ; +/{id} utk edit/hapus
    var csrf        = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var lastId      = @json($messages->last()?->id);
    var oldestId    = @json($messages->first()?->id);
    var hasMore     = @json((bool) $hasMore);
    var wasClosed   = @json($conversation->is_closed && !$isItTeam);
    var since       = @json($serverTime);
    var polling     = false;
    var loadingOld  = false;

    function scrollBottom() { box.scrollTop = box.scrollHeight; }
    scrollBottom();

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function within15(iso) { return iso && (Date.now() - new Date(iso).getTime()) < 15 * 60 * 1000; }

    function attachmentHtml(a, mine) {
        if (!a) return '';
        if (a.is_image) {
            return '<a href="' + esc(a.url) + '" target="_blank" class="block mb-1">'
                 + '<img src="' + esc(a.url) + '" alt="' + esc(a.name) + '" class="max-w-[220px] max-h-60 rounded-lg object-cover"></a>';
        }
        return '<a href="' + esc(a.url) + '" target="_blank" download '
             + 'class="flex items-center gap-2 mb-1 px-2.5 py-2 rounded-lg ' + (mine ? 'bg-white/15' : 'bg-gray-50 border') + '">'
             + '<i class="fas fa-file-arrow-down"></i><span class="truncate max-w-[160px]">' + esc(a.name) + '</span></a>';
    }

    function renderBubble(m) {
        if (m.type === 'system') {
            return '<div class="text-center" data-mid="' + esc(m.id) + '">'
                 + '<span class="inline-block bg-gray-200 text-gray-500 text-[11px] px-3 py-1 rounded-full">' + esc(m.body) + '</span></div>';
        }
        var mine   = m.sender_id === meId;
        var align  = mine ? 'items-end' : 'items-start';
        var bubble = mine ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-white text-gray-800 border rounded-bl-sm';
        var name   = mine ? '' : '<p class="text-[11px] font-semibold text-gray-500 mb-0.5 px-1">' + esc(m.sender_name) + '</p>';

        var inner;
        if (m.deleted) {
            inner = '<em class="italic opacity-70 text-xs"><i class="fas fa-ban mr-1"></i>Pesan dihapus</em>';
        } else {
            inner = attachmentHtml(m.attachment, mine)
                  + (m.body ? '<div class="msg-body whitespace-pre-line">' + esc(m.body) + '</div>' : '');
        }

        var canEdit   = mine && m.type === 'text' && !m.deleted && within15(m.created_at);
        var canDelete = !m.deleted && m.type === 'text' && (mine || canModerate);
        var actions = '';
        if (canEdit)   actions += '<button type="button" onclick="editMsg(\'' + m.id + '\')" class="hover:text-blue-500" title="Edit"><i class="fas fa-pen"></i></button>';
        if (canDelete) actions += '<button type="button" onclick="deleteMsg(\'' + m.id + '\')" class="hover:text-red-500" title="Hapus"><i class="fas fa-trash"></i></button>';

        return '<div class="msg flex flex-col ' + align + '" data-mid="' + esc(m.id) + '">' + name
             + '<div class="msg-bubble max-w-[80%] px-3.5 py-2 rounded-2xl text-sm break-words ' + bubble + '">' + inner + '</div>'
             + '<div class="flex items-center gap-1.5 mt-0.5 px-1 text-[10px] text-gray-400">'
             + '<span class="msg-time">' + esc(m.created_label) + '</span>'
             + '<span class="italic ' + (m.edited && !m.deleted ? '' : 'hidden') + '">(diedit)</span>'
             + actions + '</div></div>';
    }

    function findBubble(id) { return box.querySelector('[data-mid="' + (window.CSS && CSS.escape ? CSS.escape(id) : id) + '"]'); }

    function append(m) {
        if (findBubble(m.id)) { return replace(m); }   // hindari duplikat
        box.insertAdjacentHTML('beforeend', renderBubble(m));
        lastId = m.id;
        scrollBottom();
    }

    function replace(m) {
        var el = findBubble(m.id);
        if (el) el.outerHTML = renderBubble(m);
    }

    // ── Muat pesan lama (lazy load ke atas) ─────────────────────
    var anchor    = document.getElementById('messages-anchor');
    var olderWrap = document.getElementById('load-older-wrap');
    var olderBtn  = document.getElementById('load-older');

    function loadOlder() {
        if (loadingOld || !hasMore) return;
        loadingOld = true;
        olderBtn.disabled = true;
        var prevHeight = box.scrollHeight;
        fetch(histUrl + '?before=' + encodeURIComponent(oldestId || ''), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d || !d.messages) return;
                var html = '';
                d.messages.forEach(function (m) { html += renderBubble(m); });
                anchor.insertAdjacentHTML('afterend', html);
                if (d.messages.length) oldestId = d.messages[0].id;
                hasMore = d.has_more;
                if (!hasMore) olderWrap.classList.add('hidden');
                box.scrollTop = box.scrollHeight - prevHeight;   // pertahankan posisi baca
            })
            .catch(function () {})
            .finally(function () { loadingOld = false; olderBtn.disabled = false; });
    }
    if (olderBtn) olderBtn.addEventListener('click', loadOlder);

    // ── Poll: pesan baru + perubahan (edit/hapus) ───────────────
    function poll() {
        if (polling || document.hidden) return;   // B2: hemat resource saat tab tidak aktif
        polling = true;
        fetch(pollUrl + '?after=' + encodeURIComponent(lastId || '') + '&since=' + encodeURIComponent(since || ''),
              { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok ? r.json() : null; })
            .then(function (d) {
                if (!d) return;
                if (d.server_time) since = d.server_time;
                if (d.updates) d.updates.forEach(function (m) { replace(m); });
                if (d.messages) d.messages.forEach(function (m) { append(m); });
            })
            .catch(function () {})
            .finally(function () { polling = false; });
    }
    setInterval(poll, 3000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) poll(); });

    // ── Edit / hapus pesan (global agar bisa dipanggil onclick) ──
    window.editMsg = function (id) {
        var el = findBubble(id); if (!el) return;
        var bodyEl = el.querySelector('.msg-body');
        var current = bodyEl ? bodyEl.textContent : '';
        var next = window.prompt('Edit pesan:', current);
        if (next === null) return;
        next = next.trim();
        if (!next || next === current) return;
        fetch(msgBase + '/' + id, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_method=PATCH&body=' + encodeURIComponent(next)
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            if (res.ok && res.d.success) { replace(res.d.message); }
            else { showToast((res.d && res.d.message) || 'Gagal mengedit pesan.', 'error'); }
        })
        .catch(function () { showToast('Gagal mengedit pesan.', 'error'); });
    };

    window.deleteMsg = function (id) {
        if (!window.confirm('Hapus pesan ini?')) return;
        fetch(msgBase + '/' + id, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: '_method=DELETE'
        })
        .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
        .then(function (res) {
            if (res.ok && res.d.success) {
                replace({ id: id, type: 'text', sender_id: meId, deleted: true, body: null });
            } else { showToast((res.d && res.d.message) || 'Gagal menghapus pesan.', 'error'); }
        })
        .catch(function () { showToast('Gagal menghapus pesan.', 'error'); });
    };

    // ── Composer: lampiran + kirim (FormData) ────────────────────
    if (form) {
        var input   = document.getElementById('chat-input');
        var sendBtn = document.getElementById('chat-send');
        var fileEl  = document.getElementById('chat-file');
        var preview = document.getElementById('attach-preview');
        var attName = document.getElementById('attach-name');

        input.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 128) + 'px';
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); }
        });

        fileEl.addEventListener('change', function () {
            if (fileEl.files.length) {
                attName.textContent = fileEl.files[0].name;
                preview.classList.remove('hidden'); preview.classList.add('flex');
            } else { clearFile(); }
        });
        function clearFile() {
            fileEl.value = '';
            preview.classList.add('hidden'); preview.classList.remove('flex');
        }
        document.getElementById('attach-clear').addEventListener('click', clearFile);

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var body = input.value.trim();
            var hasFile = fileEl.files.length > 0;
            if (!body && !hasFile) return;
            sendBtn.disabled = true;

            var fd = new FormData(form);   // termasuk _token, body, attachment

            fetch(sendUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: fd
            })
            .then(function (r) { return r.json().then(function (d) { return { ok: r.ok, d: d }; }); })
            .then(function (res) {
                if (res.ok && res.d.success) {
                    if (wasClosed) { window.location.reload(); return; }
                    append(res.d.message);
                    input.value = ''; input.style.height = 'auto';
                    clearFile();
                } else {
                    showToast((res.d && res.d.message) || 'Gagal mengirim pesan.', 'error');
                }
            })
            .catch(function () { showToast('Gagal mengirim pesan.', 'error'); })
            .finally(function () { sendBtn.disabled = false; input.focus(); });
        });
    }
}());
</script>
@endpush
