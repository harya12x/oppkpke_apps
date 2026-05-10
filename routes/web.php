<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\OppkpkeController;
use App\Http\Controllers\UserController;

// =====================================================
// AUTH ROUTES
// =====================================================

Route::get('/', fn() => redirect()->route('login'));

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
    // INPUT LAPORAN - semua role (difilter by role di controller)
    // --------------------------------------------------
    Route::get('/laporan', [OppkpkeController::class, 'laporan'])->name('laporan.index');
    Route::post('/laporan', [OppkpkeController::class, 'store'])->name('laporan.store');
    Route::put('/laporan/{id}', [OppkpkeController::class, 'update'])->name('laporan.update');
    Route::delete('/laporan/{id}', [OppkpkeController::class, 'destroy'])->name('laporan.destroy');

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
    // IMPORT DATA — OPPKPKE 21 kolom
    // --------------------------------------------------
    Route::get('/import', [OppkpkeController::class, 'importPage'])->name('import');
    Route::post('/import/preview', [OppkpkeController::class, 'importPreview'])->name('import.preview');
    Route::post('/import/execute', [OppkpkeController::class, 'importExecute'])->name('import.execute');

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
});

// =====================================================
// USER MANAGEMENT (master only)
// =====================================================

Route::prefix('admin/users')->name('admin.users.')->middleware(['auth', 'role:master'])->group(function () {
    Route::get('/',                          [UserController::class, 'index'])->name('index');
    Route::post('/',                         [UserController::class, 'store'])->name('store');
    Route::get('/{user}',                    [UserController::class, 'show'])->name('show');
    Route::put('/{user}',                    [UserController::class, 'update'])->name('update');
    Route::delete('/{user}',                 [UserController::class, 'destroy'])->name('destroy');
    Route::patch('/{user}/toggle-active',    [UserController::class, 'toggleActive'])->name('toggle-active');
    Route::patch('/{user}/reset-password',   [UserController::class, 'resetPassword'])->name('reset-password');
});
