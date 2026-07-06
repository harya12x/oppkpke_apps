@php
    $mine      = $m->sender_id === $meId;
    $deleted   = $m->trashed();
    $moderate  = $meCanModerate ?? false;
    $canEdit   = $mine && $m->type === 'text' && !$deleted && $m->created_at?->gt(now()->subMinutes(15));
    $canDelete = !$deleted && $m->type === 'text' && ($mine || $moderate);
@endphp
@if($m->type === 'system')
    <div class="text-center" data-mid="{{ $m->id }}">
        <span class="inline-block bg-gray-200 text-gray-500 text-[11px] px-3 py-1 rounded-full">{{ $m->body }}</span>
    </div>
@else
    <div class="msg flex flex-col {{ $mine ? 'items-end' : 'items-start' }}" data-mid="{{ $m->id }}">
        @unless($mine)
            <p class="text-[11px] font-semibold text-gray-500 mb-0.5 px-1">{{ $m->sender->name ?? 'Sistem' }}</p>
        @endunless
        <div class="msg-bubble max-w-[80%] px-3.5 py-2 rounded-2xl text-sm break-words
                    {{ $mine ? 'bg-blue-600 text-white rounded-br-sm' : 'bg-white text-gray-800 border rounded-bl-sm' }}">
            @if($deleted)
                <em class="italic opacity-70 text-xs"><i class="fas fa-ban mr-1"></i>Pesan dihapus</em>
            @else
                @if($m->hasAttachment())
                    @php $attUrl = route('oppkpke.chat.attachment', ['conversation' => $m->conversation_id, 'message' => $m->id]); @endphp
                    @if($m->isImageAttachment())
                        <a href="{{ $attUrl }}" target="_blank" class="block mb-1">
                            <img src="{{ $attUrl }}" alt="{{ $m->attachment_name }}" class="max-w-[220px] max-h-60 rounded-lg object-cover">
                        </a>
                    @else
                        <a href="{{ $attUrl }}" target="_blank" download
                           class="flex items-center gap-2 mb-1 px-2.5 py-2 rounded-lg {{ $mine ? 'bg-white/15' : 'bg-gray-50 border' }}">
                            <i class="fas fa-file-arrow-down"></i>
                            <span class="truncate max-w-[160px]">{{ $m->attachment_name }}</span>
                        </a>
                    @endif
                @endif
                @if(trim((string) $m->body) !== '')
                    <div class="msg-body whitespace-pre-line">{{ $m->body }}</div>
                @endif
            @endif
        </div>
        <div class="flex items-center gap-1.5 mt-0.5 px-1 text-[10px] text-gray-400">
            <span class="msg-time">{{ $m->created_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</span>
            <span class="msg-edited {{ ($m->edited_at && !$deleted) ? '' : 'hidden' }} italic">(diedit)</span>
            @if($canEdit)
                <button type="button" onclick="editMsg('{{ $m->id }}')" class="msg-edit-btn hover:text-blue-500" title="Edit"><i class="fas fa-pen"></i></button>
            @endif
            @if($canDelete)
                <button type="button" onclick="deleteMsg('{{ $m->id }}')" class="msg-del-btn hover:text-red-500" title="Hapus"><i class="fas fa-trash"></i></button>
            @endif
        </div>
    </div>
@endif
