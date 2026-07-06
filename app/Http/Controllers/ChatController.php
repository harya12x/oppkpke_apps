<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\ChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Chat support Operator Daerah ⇄ Tim IT.
 * - Operator Daerah: hanya melihat & membuat percakapan miliknya.
 * - Tim IT: melihat seluruh percakapan (inbox agent), membalas, ubah status.
 * - Admin Master: memantau seluruh percakapan (hanya-baca) — H6.
 * Otorisasi dipusatkan di App\Policies\ConversationPolicy (SEC5).
 */
class ChatController extends Controller
{
    /** Jumlah pesan per halaman (initial load & lazy history). */
    private const PAGE_SIZE = 50;

    /** Aturan validasi lampiran (maks 5 MB, tipe umum & aman). */
    private const ATTACHMENT_RULES = 'nullable|file|max:5120|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt';

    public function __construct(private ChatService $chat) {}

    /**
     * Simpan lampiran ke disk privat. Nama file di-generate acak agar tidak
     * bisa ditebak; hanya path yang disimpan di DB.
     *
     * @return array{path?:string,name?:string,mime?:string,size?:int}
     */
    private function storeAttachment(?UploadedFile $file): array
    {
        if (!$file || !$file->isValid()) {
            return [];
        }

        $path = $file->store('chat-attachments', 'local');

        return [
            'path' => $path,
            'name' => mb_substr($file->getClientOriginalName(), 0, 160),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ];
    }

    // =========================================
    // INBOX
    // =========================================

    public function index(Request $request)
    {
        $user = auth()->user();
        $this->authorize('viewAny', Conversation::class);

        $query = Conversation::query()
            ->with(['initiator', 'perangkatDaerah', 'assignee', 'latestMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('created_at');

        // Operator Daerah dibatasi ke percakapannya sendiri.
        if ($user->isDaerah()) {
            $query->where('initiator_id', $user->id);
        }

        // Filter status (Tim IT sering perlu memilah open/pending/selesai).
        if ($request->filled('status') && in_array($request->status, ['open', 'pending', 'resolved', 'closed'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('q')) {
            $q = $request->q;
            $query->where(function ($sub) use ($q) {
                $sub->where('subject', 'like', "%{$q}%")
                    ->orWhereHas('initiator', fn ($u) => $u->where('name', 'like', "%{$q}%"))
                    ->orWhereHas('perangkatDaerah', fn ($p) => $p->where('nama', 'like', "%{$q}%"));
            });
        }

        $conversations = $query->paginate(20)->withQueryString();

        $unreadIds = array_flip($this->chat->unreadConversationIds($user));

        return view('chat.index', [
            'conversations' => $conversations,
            'unreadIds'     => $unreadIds,
            'isItTeam'      => $user->isItTeam(),
            'isDaerah'      => $user->isDaerah(),
            'isMaster'      => $user->isMaster(),
            'filters'       => $request->only(['status', 'q']),
        ]);
    }

    // =========================================
    // THREAD
    // =========================================

    public function show(Conversation $conversation)
    {
        $user = auth()->user();
        $this->authorize('view', $conversation);

        $conversation->load(['initiator', 'perangkatDaerah', 'assignee']);

        // Muat hanya 50 pesan terbaru (S2) — pesan lama diambil on-demand via history().
        // withTrashed: pesan terhapus tetap tampil sebagai placeholder "dihapus".
        $messages = $conversation->messages()->withTrashed()->with('sender')
            ->orderByDesc('id')->limit(self::PAGE_SIZE)->get()
            ->reverse()->values();

        $hasMore = $messages->isNotEmpty()
            && $conversation->messages()->withTrashed()->where('id', '<', $messages->first()->id)->exists();

        // Master hanya memantau — jangan tandai baca / ubah state.
        if (!$user->isMaster()) {
            $this->chat->markRead($conversation, $user);
        }

        return view('chat.show', [
            'conversation' => $conversation,
            'messages'     => $messages,
            'hasMore'      => $hasMore,
            'serverTime'   => now()->toIso8601String(),
            'isItTeam'     => $user->isItTeam(),
            'isMaster'     => $user->isMaster(),
            'canReply'     => $user->can('reply', $conversation),
            'meCanModerate'=> $user->isItTeam(),
        ]);
    }

    // =========================================
    // HISTORY — pesan lama sebelum id tertentu (lazy load ke atas)
    // =========================================

    public function history(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $before = $request->query('before');

        $query = $conversation->messages()->withTrashed()->with('sender')->orderByDesc('id');
        if ($before) {
            $query->where('id', '<', $before);
        }

        // Ambil satu ekstra untuk mendeteksi apakah masih ada yang lebih lama.
        $rows = $query->limit(self::PAGE_SIZE + 1)->get();
        $hasMore = $rows->count() > self::PAGE_SIZE;
        $messages = $rows->take(self::PAGE_SIZE)->reverse()->values();

        return response()->json([
            'success'  => true,
            'has_more' => $hasMore,
            'messages' => $messages->map->toChatArray()->values(),
        ]);
    }

    // =========================================
    // START (Operator Daerah membuat percakapan baru)
    // =========================================

    public function store(Request $request)
    {
        $user = auth()->user();
        $this->authorize('create', Conversation::class);

        $validated = $request->validate([
            'subject'    => 'nullable|string|max:160',
            'priority'   => 'nullable|in:low,normal,high,urgent',
            'body'       => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => self::ATTACHMENT_RULES,
        ], [
            'body.required_without' => 'Pesan atau lampiran wajib diisi.',
            'body.max'              => 'Pesan maksimal 5000 karakter.',
            'attachment.max'        => 'Ukuran lampiran maksimal 5 MB.',
            'attachment.mimes'      => 'Tipe lampiran tidak didukung.',
        ]);

        $body       = trim((string) ($validated['body'] ?? ''));
        $attachment = $this->storeAttachment($request->file('attachment'));

        if ($body === '' && empty($attachment)) {
            return back()->withErrors(['body' => 'Pesan atau lampiran wajib diisi.'])->withInput();
        }

        $conversation = $this->chat->startConversation(
            $user,
            $body,
            $validated['subject'] ? trim($validated['subject']) : null,
            $validated['priority'] ?? 'normal',
            $attachment,
        );

        return redirect()
            ->route('oppkpke.chat.show', $conversation)
            ->with('success', 'Percakapan berhasil dibuat. Tim IT akan segera merespons.');
    }

    // =========================================
    // SEND MESSAGE
    // =========================================

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $user = auth()->user();
        $this->authorize('reply', $conversation);

        $validated = $request->validate([
            'body'       => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => self::ATTACHMENT_RULES,
        ], [
            'body.required_without' => 'Pesan atau lampiran wajib diisi.',
            'body.max'              => 'Pesan maksimal 5000 karakter.',
            'attachment.max'        => 'Ukuran lampiran maksimal 5 MB.',
            'attachment.mimes'      => 'Tipe lampiran tidak didukung.',
        ]);

        $body       = trim((string) ($validated['body'] ?? ''));
        $attachment = $this->storeAttachment($request->file('attachment'));

        if ($body === '' && empty($attachment)) {
            return $this->respond($request, false, 'Pesan atau lampiran wajib diisi.', 422);
        }

        // H2: percakapan tertutup.
        if ($conversation->is_closed) {
            if ($user->isDaerah()) {
                // Operator membalas → buka kembali tiket (dengan jejak sistem).
                $this->chat->changeStatus($conversation, $user, 'open');
                $conversation->refresh();
            } else {
                // Tim IT harus membuka status via dropdown, bukan lewat balasan.
                return $this->respond($request, false, 'Percakapan sudah ditutup. Ubah status ke "Terbuka" untuk membalas.', 422);
            }
        }

        // Tim IT yang membalas pertama kali otomatis "mengambil" tiket.
        if ($user->isItTeam() && $conversation->assigned_to === null) {
            $conversation->update(['assigned_to' => $user->id, 'status' => 'pending']);
        }

        $message = $this->chat->postMessage($conversation, $user, $body, 'text', $attachment);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => $message->fresh('sender')->toChatArray(),
            ]);
        }

        return back();
    }

    // =========================================
    // EDIT & DELETE MESSAGE
    // =========================================

    public function editMessage(Request $request, Conversation $conversation, Message $message)
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        $this->authorize('update', $message);

        $validated = $request->validate(['body' => 'required|string|max:5000']);
        $body = trim($validated['body']);
        if ($body === '') {
            return $this->respond($request, false, 'Pesan tidak boleh kosong.', 422);
        }

        $message->update(['body' => $body, 'edited_at' => now()]);

        return response()->json(['success' => true, 'message' => $message->fresh('sender')->toChatArray()]);
    }

    public function deleteMessage(Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        $this->authorize('delete', $message);

        // Hapus file lampiran fisik bila ada, lalu soft-delete barisnya.
        if ($message->attachment_path) {
            Storage::disk('local')->delete($message->attachment_path);
        }
        $message->delete();

        return response()->json(['success' => true, 'id' => $message->id]);
    }

    // =========================================
    // ATTACHMENT — unduh berotorisasi (disk privat)
    // =========================================

    public function attachment(Conversation $conversation, Message $message): StreamedResponse
    {
        $this->authorize('view', $conversation);
        abort_unless($message->conversation_id === $conversation->id && $message->attachment_path, 404);
        abort_unless(Storage::disk('local')->exists($message->attachment_path), 404);

        // Inline agar gambar tampil langsung; tipe lain tetap bisa diunduh.
        // Content-Type diset dari nilai tersimpan (bukan tebakan dari file).
        return Storage::disk('local')->response(
            $message->attachment_path,
            $message->attachment_name,
            ['Content-Type' => $message->attachment_mime ?: 'application/octet-stream'],
        );
    }

    // =========================================
    // POLL — pesan baru setelah id tertentu (fallback real-time)
    // =========================================

    public function poll(Request $request, Conversation $conversation): JsonResponse
    {
        $user = auth()->user();
        $this->authorize('view', $conversation);

        $after = $request->query('after');
        $since = $request->query('since');   // waktu server dari poll sebelumnya

        // Pesan BARU (id > after).
        $newQuery = $conversation->messages()->with('sender')->orderBy('id');
        if ($after) {
            $newQuery->where('id', '>', $after);
        }
        $messages = $newQuery->get();

        // PERUBAHAN pesan lama (edit/hapus) sejak poll terakhir — termasuk
        // yang sudah di-soft-delete agar klien bisa mengganti bubble.
        // Parse ISO8601 → Carbon agar perbandingan datetime valid di MySQL
        // (string mentah "...T...+00:00" tidak dikenali MySQL).
        $updates = collect();
        $sinceTime = null;
        if ($since) {
            try {
                $sinceTime = \Illuminate\Support\Carbon::parse($since);
            } catch (\Throwable $e) {
                $sinceTime = null;
            }
        }
        if ($sinceTime) {
            $updates = $conversation->messages()->withTrashed()->with('sender')
                ->where('updated_at', '>=', $sinceTime)
                ->when($after, fn ($q) => $q->where('id', '<=', $after))
                ->orderBy('id')->get();
        }

        // Hanya tulis penanda baca bila memang ada pesan baru — mencegah
        // UPDATE conversation_reads setiap 3 dtk saat percakapan idle.
        // Master hanya memantau, jadi tidak ikut menandai baca.
        if ($messages->isNotEmpty() && !$user->isMaster()) {
            $this->chat->markRead($conversation, $user);
        }

        return response()->json([
            'success'     => true,
            'status'      => $conversation->status,
            'server_time' => now()->toIso8601String(),
            'messages'    => $messages->map->toChatArray()->values(),
            'updates'     => $updates->map->toChatArray()->values(),
        ]);
    }

    // =========================================
    // UNREAD BADGE (polling ringan untuk sidebar)
    // =========================================

    public function unreadCount(): JsonResponse
    {
        return response()->json([
            'count' => $this->chat->unreadConversationCount(auth()->user()),
        ]);
    }

    // =========================================
    // STATUS (Tim IT)
    // =========================================

    public function updateStatus(Request $request, Conversation $conversation)
    {
        $user = auth()->user();
        $this->authorize('updateStatus', $conversation);

        $validated = $request->validate([
            'status' => 'required|in:open,pending,resolved,closed',
        ]);

        $this->chat->changeStatus($conversation, $user, $validated['status']);

        return back()->with('success', 'Status percakapan diperbarui.');
    }

    private function respond(Request $request, bool $ok, string $message, int $code = 200)
    {
        if ($request->expectsJson() || $request->ajax()) {
            return response()->json(['success' => $ok, 'message' => $message], $code);
        }

        return back()->with($ok ? 'success' : 'error', $message);
    }
}
