{{-- Banner pengumuman / maintenance.
     $liveAnnouncements di-inject oleh App\View\Composers\AnnouncementComposer
     dan hanya berisi data untuk role selain Tim IT. --}}
@if(!empty($liveAnnouncements) && $liveAnnouncements->count())
<div id="announcement-banner" class="mx-4 md:mx-6 mt-4 space-y-2">
    @foreach($liveAnnouncements as $ann)
        @php
            $style = match($ann->type) {
                'critical'    => ['wrap' => 'bg-red-50 border-red-300 text-red-800',       'icon' => 'fa-triangle-exclamation', 'ic' => 'text-red-500'],
                'maintenance' => ['wrap' => 'bg-orange-50 border-orange-300 text-orange-800','icon' => 'fa-screwdriver-wrench',   'ic' => 'text-orange-500'],
                'warning'     => ['wrap' => 'bg-yellow-50 border-yellow-300 text-yellow-800','icon' => 'fa-circle-exclamation',   'ic' => 'text-yellow-500'],
                default       => ['wrap' => 'bg-blue-50 border-blue-300 text-blue-800',      'icon' => 'fa-circle-info',          'ic' => 'text-blue-500'],
            };
        @endphp
        <div class="announcement-item border rounded-lg px-4 py-3 flex items-start gap-3 text-sm {{ $style['wrap'] }}"
             data-ann-id="{{ $ann->id }}"
             data-ann-sig="{{ md5($ann->id.'|'.$ann->updated_at) }}">
            <i class="fas {{ $style['icon'] }} {{ $style['ic'] }} flex-shrink-0 mt-0.5 text-base"></i>
            <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="font-semibold">{{ $ann->title }}</span>
                    <span class="text-[10px] uppercase tracking-wide font-bold px-1.5 py-0.5 rounded {{ $style['ic'] }} bg-white/60">
                        {{ $ann->type_label }}
                    </span>
                </div>
                <p class="mt-0.5 whitespace-pre-line leading-relaxed">{{ $ann->body }}</p>
                @if($ann->ends_at)
                    <p class="mt-1 text-xs opacity-70">
                        <i class="fas fa-clock mr-1"></i>Berlaku sampai {{ $ann->ends_at->timezone(config('app.timezone'))->format('d M Y H:i') }}
                    </p>
                @endif
            </div>
            <button type="button"
                    onclick="dismissAnnouncement(this)"
                    class="flex-shrink-0 -mr-1 p-1 rounded hover:bg-white/50 transition {{ $style['ic'] }}"
                    aria-label="Tutup pengumuman">
                <i class="fas fa-times"></i>
            </button>
        </div>
    @endforeach
</div>

<script>
// Sembunyikan banner yang sudah ditutup user (per-signature, reset otomatis bila
// pengumuman diperbarui). Disimpan di localStorage — ringan, tanpa tabel tambahan.
(function () {
    var KEY = 'oppkpke_dismissed_ann';
    var dismissed = {};
    try { dismissed = JSON.parse(localStorage.getItem(KEY) || '{}'); } catch (e) {}

    document.querySelectorAll('#announcement-banner .announcement-item').forEach(function (el) {
        if (dismissed[el.dataset.annSig]) el.remove();
    });

    var wrap = document.getElementById('announcement-banner');
    if (wrap && !wrap.querySelector('.announcement-item')) wrap.remove();

    window.dismissAnnouncement = function (btn) {
        var item = btn.closest('.announcement-item');
        if (!item) return;
        try {
            var d = JSON.parse(localStorage.getItem(KEY) || '{}');
            d[item.dataset.annSig] = 1;
            localStorage.setItem(KEY, JSON.stringify(d));
        } catch (e) {}
        item.remove();
        var w = document.getElementById('announcement-banner');
        if (w && !w.querySelector('.announcement-item')) w.remove();
    };
}());
</script>
@endif
