<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * Pengelolaan pengumuman / informasi maintenance.
 * Dikelola oleh Admin Master & Tim IT (route middleware role:master,it_team).
 * Ditampilkan sebagai banner ke semua role KECUALI Tim IT (lihat
 * App\View\Composers\AnnouncementComposer).
 */
class AnnouncementController extends Controller
{
    public function index()
    {
        $announcements = Announcement::with('creator')
            ->orderByDesc('is_active')
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('announcements.index', compact('announcements'));
    }

    public function store(Request $request)
    {
        $this->authorize('create', Announcement::class);

        $data = $this->validateData($request);
        $data['created_by'] = auth()->id();

        $announcement = Announcement::create($data);

        AuditLog::record('announcement.created', "Menerbitkan pengumuman \"{$announcement->title}\"", $announcement, ['type' => $announcement->type]);

        return back()->with('success', 'Pengumuman berhasil diterbitkan.');
    }

    public function update(Request $request, Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $announcement->update($this->validateData($request));

        AuditLog::record('announcement.updated', "Memperbarui pengumuman \"{$announcement->title}\"", $announcement);

        return back()->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function toggle(Announcement $announcement)
    {
        $this->authorize('update', $announcement);

        $announcement->update(['is_active' => !$announcement->is_active]);

        $status = $announcement->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::record('announcement.toggled', "Pengumuman \"{$announcement->title}\" {$status}", $announcement, ['is_active' => $announcement->is_active]);

        return back()->with('success', "Pengumuman berhasil {$status}.");
    }

    public function destroy(Announcement $announcement)
    {
        $this->authorize('delete', $announcement);

        AuditLog::record('announcement.deleted', "Menghapus pengumuman \"{$announcement->title}\"", $announcement);

        $announcement->delete();

        return back()->with('success', 'Pengumuman berhasil dihapus.');
    }

    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'title'     => 'required|string|max:160',
            'body'      => 'required|string|max:5000',
            'type'      => 'required|in:info,warning,maintenance,critical',
            'starts_at' => 'nullable|date',
            'ends_at'   => 'nullable|date|after_or_equal:starts_at',
        ], [
            'title.required' => 'Judul wajib diisi.',
            'body.required'  => 'Isi pengumuman wajib diisi.',
            'type.required'  => 'Jenis pengumuman wajib dipilih.',
            'ends_at.after_or_equal' => 'Waktu berakhir harus setelah waktu mulai.',
        ]);

        $data['is_active'] = $request->boolean('is_active');

        return $data;
    }
}
