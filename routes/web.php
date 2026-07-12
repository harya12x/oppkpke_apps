<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\HierarkiImportController;
use App\Http\Controllers\OppkpkeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SessionController;
use App\Http\Controllers\UserController;

// =====================================================
// AUTH ROUTES
// =====================================================

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('oppkpke.index');
    }
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

// =====================================================
// OPPKPKE ROUTES (auth required)
// =====================================================

Route::prefix('oppkpke')->name('oppkpke.')->middleware('auth')->group(function () {

    // Home → redirect based on role
    Route::get('/', [OppkpkeController::class, 'index'])->name('index');

    // --------------------------------------------------
    // DASHBOARD - semua role (konten dibedakan di controller)
    // --------------------------------------------------
    Route::get('/dashboard', [OppkpkeController::class, 'dashboard'])
        ->name('dashboard');

    // --------------------------------------------------
    // EXPLORER DATA - semua role
    // --------------------------------------------------
    Route::get('/explorer', [OppkpkeController::class, 'explorer'])->name('explorer');
    Route::get('/explorer/data', [OppkpkeController::class, 'explorerData'])->name('explorer.data');

    // --------------------------------------------------
    // IDENTITAS PIC — wajib dilengkapi Operator Daerah sebelum input laporan
    // --------------------------------------------------
    Route::get('/lengkapi-identitas',  [ProfileController::class, 'picForm'])->name('pic.form');
    Route::post('/lengkapi-identitas', [ProfileController::class, 'picSave'])->name('pic.save');
    // PIC tambahan (undang PIC lain — hanya catatan identitas, tanpa akun login).
    Route::post('/pic',            [ProfileController::class, 'picInvite'])->middleware('throttle:30,1')->name('pic.invite');
    Route::delete('/pic/{pic}',    [ProfileController::class, 'picInviteDestroy'])->name('pic.invite.destroy');

    // --------------------------------------------------
    // INPUT LAPORAN - semua role (difilter by role di controller)
    // Gate 'pic.identity': Operator Daerah wajib lengkapi identitas PIC dulu.
    // --------------------------------------------------
    Route::middleware('pic.identity')->group(function () {
        Route::get('/laporan', [OppkpkeController::class, 'laporan'])->name('laporan.index');
        Route::post('/laporan', [OppkpkeController::class, 'store'])->name('laporan.store');
        Route::put('/laporan/{id}', [OppkpkeController::class, 'update'])->name('laporan.update');
        // batch HARUS terdaftar sebelum {id} — kalau tidak, "batch" akan tertangkap sebagai {id}.
        Route::delete('/laporan/batch', [OppkpkeController::class, 'batchDestroy'])->name('laporan.batch-destroy');
        Route::delete('/laporan/{id}', [OppkpkeController::class, 'destroy'])->name('laporan.destroy');
    });

    // --------------------------------------------------
    // OPTIONS - CASCADING DROPDOWN (AJAX)
    // --------------------------------------------------
    Route::prefix('options')->name('options.')->group(function () {
        Route::get('/strategi', [OppkpkeController::class, 'getStrategi'])->name('strategi');
        Route::get('/perangkat-daerah', [OppkpkeController::class, 'getPerangkatDaerah'])->name('perangkat-daerah');
        Route::get('/programs', [OppkpkeController::class, 'getPrograms'])->name('programs');
        Route::get('/kegiatan', [OppkpkeController::class, 'getKegiatan'])->name('kegiatan');
        Route::get('/sub-kegiatan', [OppkpkeController::class, 'getSubKegiatan'])->name('sub-kegiatan');
    });

    // --------------------------------------------------
    // TAMBAH HIRARKI MANUAL (Program → Kegiatan → Sub Kegiatan)
    // Wizard untuk Operator Daerah — terkunci ke PD mereka sendiri.
    // --------------------------------------------------
    Route::post('/hierarki', [OppkpkeController::class, 'hierarchyStore'])
        ->middleware('throttle:30,1')->name('hierarki.store');

    // Ubah nama program/kegiatan (operator daerah: terkunci ke PD-nya) — dari halaman Input Data.
    Route::patch('/program/{id}', [OppkpkeController::class, 'programUpdate'])
        ->middleware('throttle:60,1')->name('program.update');
    Route::patch('/kegiatan/{id}', [OppkpkeController::class, 'kegiatanUpdate'])
        ->middleware('throttle:60,1')->name('kegiatan.update');

    // --------------------------------------------------
    // IMPORT HIERARKI via Excel (Admin) — PD → Strategi → Program → Kegiatan → Sub.
    // Selalu: unduh template → upload → PREVIEW → eksekusi.
    // --------------------------------------------------
    Route::middleware('role:master,it_team')->group(function () {
        Route::get('/import-hierarki',          [HierarkiImportController::class, 'page'])->name('import.hierarki');
        Route::get('/import-hierarki/template',  [HierarkiImportController::class, 'template'])->name('import.hierarki.template');
        Route::post('/import-hierarki/preview',  [HierarkiImportController::class, 'preview'])->name('import.hierarki.preview');
        Route::post('/import-hierarki/execute',  [HierarkiImportController::class, 'execute'])->middleware('throttle:20,1')->name('import.hierarki.execute');
    });

    // --------------------------------------------------
    // RINGKASAN OTOMATIS — narasi ringan (dihitung server, tanpa LLM).
    // Dashboard, khusus Top Management & Tim IT.
    // --------------------------------------------------
    Route::get('/ringkasan', [OppkpkeController::class, 'ringkasan'])
        ->middleware('role:master,it_team')->name('ringkasan');

    // --------------------------------------------------
    // PRESENTASI TOP MANAGEMENT — halaman eksekutif (KPI, grafik, analisis).
    // Khusus Top Management & Tim IT.
    // --------------------------------------------------
    Route::get('/presentasi', [OppkpkeController::class, 'presentasi'])
        ->middleware('role:master,it_team')->name('presentasi');

    // --------------------------------------------------
    // MENU (mobile) — daftar seluruh menu sesuai role.
    // --------------------------------------------------
    Route::get('/menu', [OppkpkeController::class, 'menu'])->name('menu');

    // --------------------------------------------------
    // IMPORT DATA — OPPKPKE 21 kolom
    // --------------------------------------------------
    Route::get('/import', [OppkpkeController::class, 'importPage'])->name('import');
    Route::post('/import/preview', [OppkpkeController::class, 'importPreview'])->name('import.preview');
    Route::post('/import/execute', [OppkpkeController::class, 'importExecute'])->name('import.execute');
    Route::get('/import/status', [OppkpkeController::class, 'importStatus'])->name('import.status');

    // --------------------------------------------------
    // IMPORT DATA — Matriks RAT 18 kolom
    // --------------------------------------------------
    Route::get('/import-rat', [OppkpkeController::class, 'importRatPage'])->name('import.rat');
    Route::post('/import-rat/preview', [OppkpkeController::class, 'importRatPreview'])->name('import.rat.preview');
    Route::post('/import-rat/execute', [OppkpkeController::class, 'importRatExecute'])->name('import.rat.execute');

    // --------------------------------------------------
    // MATRIX REVIEW - tabel 21 kolom format resmi
    // --------------------------------------------------
    Route::get('/matrix', [OppkpkeController::class, 'matrixReview'])->name('matrix');

    // --------------------------------------------------
    // EXPORT - semua role (difilter by role di controller)
    // --------------------------------------------------
    Route::prefix('export')->name('export.')->group(function () {
        Route::get('/excel', [OppkpkeController::class, 'exportExcel'])->name('excel');
        Route::get('/pdf', [OppkpkeController::class, 'exportPdf'])->name('pdf');
    });

    // --------------------------------------------------
    // STATISTIK & REPORT - semua role
    // --------------------------------------------------
    Route::get('/statistik', [OppkpkeController::class, 'statistik'])->name('statistik');
    Route::get('/report', [OppkpkeController::class, 'report'])->name('report');

    // --------------------------------------------------
    // PANDUAN PENGGUNA
    // --------------------------------------------------
    Route::get('/panduan', [OppkpkeController::class, 'panduan'])->name('panduan');

    // --------------------------------------------------
    // CHAT SUPPORT — Operator Daerah ⇄ Tim IT
    // --------------------------------------------------
    Route::prefix('chat')->name('chat.')->middleware('role:daerah,it_team,master')->group(function () {
        Route::get('/',                          [ChatController::class, 'index'])->name('index');
        // Endpoint polling — batas longgar (dipanggil berkala oleh klien).
        Route::get('/unread-count',              [ChatController::class, 'unreadCount'])->middleware('throttle:120,1')->name('unread-count');
        // Anti-spam pembuatan tiket & pengiriman pesan (SEC1).
        Route::post('/',                         [ChatController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/{conversation}',            [ChatController::class, 'show'])->name('show');
        Route::get('/{conversation}/poll',       [ChatController::class, 'poll'])->middleware('throttle:120,1')->name('poll');
        Route::get('/{conversation}/history',    [ChatController::class, 'history'])->middleware('throttle:60,1')->name('history');
        Route::post('/{conversation}/messages',  [ChatController::class, 'storeMessage'])->middleware('throttle:60,1')->name('message');
        Route::get('/{conversation}/attachment/{message}', [ChatController::class, 'attachment'])->name('attachment');
        Route::patch('/{conversation}/messages/{message}', [ChatController::class, 'editMessage'])->middleware('throttle:60,1')->name('message.edit');
        Route::delete('/{conversation}/messages/{message}',[ChatController::class, 'deleteMessage'])->middleware('throttle:60,1')->name('message.delete');
        Route::patch('/{conversation}/status',   [ChatController::class, 'updateStatus'])->name('status');
    });

    // --------------------------------------------------
    // PENGUMUMAN / MAINTENANCE — dikelola Admin Master & Tim IT
    // --------------------------------------------------
    Route::prefix('pengumuman')->name('announcements.')->middleware('role:master,it_team')->group(function () {
        Route::get('/',                       [AnnouncementController::class, 'index'])->name('index');
        Route::post('/',                      [AnnouncementController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::put('/{announcement}',         [AnnouncementController::class, 'update'])->middleware('throttle:30,1')->name('update');
        Route::patch('/{announcement}/toggle',[AnnouncementController::class, 'toggle'])->name('toggle');
        Route::delete('/{announcement}',      [AnnouncementController::class, 'destroy'])->name('destroy');
    });

    // --------------------------------------------------
    // PROFIL — ganti password sendiri
    // --------------------------------------------------
    Route::get('/ganti-password',  [ProfileController::class, 'changePasswordForm'])->name('profile.change-password');
    Route::post('/ganti-password', [ProfileController::class, 'changePassword'])->name('profile.change-password.update');
});

// =====================================================
// USER MANAGEMENT (master only)
// =====================================================

// =====================================================
// AUDIT LOG — dikelola/dipantau oleh Tim IT (menu ada di Tim IT).
// Master tetap dapat mengakses via URL (super-admin), tapi menu dipindah ke Tim IT.
// =====================================================

Route::prefix('admin/audit')->name('admin.audit.')->middleware(['auth', 'role:it_team,master'])->group(function () {
    Route::get('/', [\App\Http\Controllers\AuditLogController::class, 'index'])->name('index');
});

// =====================================================
// SESI LOGIN — pantau & logout paksa (Tim IT & Master)
// =====================================================

Route::prefix('admin/sessions')->name('admin.sessions.')->middleware(['auth', 'role:it_team,master'])->group(function () {
    Route::get('/',              [SessionController::class, 'index'])->name('index');
    Route::post('/force-logout', [SessionController::class, 'forceLogout'])->middleware('throttle:30,1')->name('force-logout');
});

// =====================================================
// PERANGKAT DAERAH — deteksi & merge duplikat (Tim IT & Master)
// =====================================================

Route::prefix('admin/perangkat-daerah')->name('admin.perangkat-daerah.')->middleware(['auth', 'role:it_team,master'])->group(function () {
    Route::get('/',      [\App\Http\Controllers\PerangkatDaerahController::class, 'index'])->name('index');
    Route::post('/merge', [\App\Http\Controllers\PerangkatDaerahController::class, 'merge'])->middleware('throttle:20,1')->name('merge');
});

// =====================================================
// KELOLA MENU — aktivasi/deaktivasi menu per role (Tim IT & Master)
// =====================================================

Route::prefix('admin/menu-settings')->name('admin.menu-settings.')->middleware(['auth', 'role:it_team,master'])->group(function () {
    Route::get('/',  [\App\Http\Controllers\MenuSettingController::class, 'index'])->name('index');
    Route::post('/', [\App\Http\Controllers\MenuSettingController::class, 'update'])->middleware('throttle:30,1')->name('update');
});

// =====================================================
// KELOLA STRATEGI — edit label/nama strategi (Tim IT & Master)
// =====================================================

Route::prefix('admin/strategi')->name('admin.strategi.')->middleware(['auth', 'role:it_team,master'])->group(function () {
    Route::get('/',            [\App\Http\Controllers\StrategiController::class, 'index'])->name('index');
    Route::patch('/{id}',      [\App\Http\Controllers\StrategiController::class, 'update'])->middleware('throttle:30,1')->name('update');
});

Route::prefix('admin/users')->name('admin.users.')->middleware(['auth', 'role:master'])->group(function () {
    Route::get('/',                                    [UserController::class, 'index'])->name('index');
    Route::get('/export/pdf',                          [UserController::class, 'exportPdf'])->name('export-pdf');
    Route::post('/',                                   [UserController::class, 'store'])->name('store');
    // Alur khusus "Tambah Operator Daerah" (terpandu + kredensial sekali tampil).
    Route::get('/operator/prepare',                    [UserController::class, 'operatorPrepare'])->name('operator.prepare');
    Route::post('/operator',                           [UserController::class, 'storeOperator'])->middleware('throttle:30,1')->name('operator.store');
    Route::get('/generate-credentials/preview',        [UserController::class, 'generateCredentialsPreview'])->name('generate-credentials.preview');
    Route::post('/generate-credentials',               [UserController::class, 'generateCredentials'])->name('generate-credentials');
    Route::get('/{user}',                              [UserController::class, 'show'])->name('show');
    Route::put('/{user}',                              [UserController::class, 'update'])->name('update');
    Route::delete('/{user}',                           [UserController::class, 'destroy'])->name('destroy');
    Route::patch('/{user}/toggle-active',              [UserController::class, 'toggleActive'])->name('toggle-active');
    Route::patch('/{user}/reset-password',             [UserController::class, 'resetPassword'])->name('reset-password');
});
