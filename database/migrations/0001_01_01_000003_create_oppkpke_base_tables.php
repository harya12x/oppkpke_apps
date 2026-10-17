<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel inti OPPKPKE: strategi -> perangkat daerah -> program -> kegiatan ->
 * sub kegiatan -> laporan.
 *
 * Dulu tabel-tabel ini dibuat dari dump SQL, bukan migration, sehingga
 * `php artisan migrate` di database KOSONG gagal di
 * 2026_05_09_000002_add_urutan_to_strategi_oppkpke_table ("table doesn't exist").
 * Migration ini mengisi celah itu:
 *
 *  - Bentuk tabel = bentuk SEBELUM migration 2026_05_* dan 2026_07_* mengubahnya
 *    (kolom urutan, aktivitas_*, deleted_at, unique laporan, nama_* jadi text,
 *    perangkat_daerah nullable ditambahkan oleh migration masing-masing).
 *  - Tiap tabel dijaga Schema::hasTable: di database yang sudah ada (server lama)
 *    migration ini tidak mengubah apa pun, hanya tercatat di tabel migrations.
 *
 * Tipe kolom, unique key, dan aturan FK mengikuti struktur database produksi
 * (dump oppkpke_db, Oktober 2026), supaya CI dan database baru sama dengan produksi.
 * Tabel lama `program` (tunggal, tidak dipakai kode) sengaja tidak dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('strategi_oppkpke')) {
            Schema::create('strategi_oppkpke', function (Blueprint $table) {
                $table->id();
                $table->string('kode', 10)->unique();
                $table->string('nama');
                $table->text('deskripsi')->nullable();
                $table->string('icon')->nullable();
                $table->string('color', 20)->default('blue');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('perangkat_daerah')) {
            Schema::create('perangkat_daerah', function (Blueprint $table) {
                $table->id();
                // Dijadikan nullable oleh 2026_05_10_124448_alter_perangkat_daerah_nullable_fields.
                $table->string('kode')->unique();
                $table->string('nama');
                $table->string('jenis');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->string('singkatan')->nullable();
            });
        }

        if (! Schema::hasTable('programs')) {
            Schema::create('programs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('strategi_id')->constrained('strategi_oppkpke')->cascadeOnDelete();
                // CASCADE: PerangkatDaerahMergeService memindah program dulu, lalu
                // menghapus PD sumber yang sudah kosong.
                $table->foreignId('perangkat_daerah_id')->constrained('perangkat_daerah')->cascadeOnDelete();
                $table->string('kode_program', 50);
                // Menjadi text oleh 2026_05_10_120650_alter_nama_columns_to_text.
                $table->string('nama_program');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->unique(['strategi_id', 'perangkat_daerah_id', 'kode_program']);
            });
        }

        if (! Schema::hasTable('kegiatan')) {
            Schema::create('kegiatan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('program_id')->constrained('programs')->cascadeOnDelete();
                $table->string('kode')->nullable();
                $table->string('nama_kegiatan');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sub_kegiatan')) {
            Schema::create('sub_kegiatan', function (Blueprint $table) {
                $table->id();
                $table->foreignId('kegiatan_id')->constrained('kegiatan')->cascadeOnDelete();
                $table->string('kode')->nullable();
                $table->string('nama_sub_kegiatan');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('laporan_oppkpke')) {
            Schema::create('laporan_oppkpke', function (Blueprint $table) {
                $table->id();
                $table->foreignId('sub_kegiatan_id')->constrained('sub_kegiatan')->cascadeOnDelete();
                $table->year('tahun');
                $table->tinyInteger('semester')->default(2);
                $table->longText('durasi_pemberian')->nullable();
                $table->longText('besaran_manfaat')->nullable();
                $table->longText('jenis_bantuan')->nullable();
                $table->longText('jumlah_sasaran')->nullable();
                // aktivitas_* ditambahkan sesudah jumlah_sasaran oleh 2026_05_10_000001.
                $table->string('satuan_sasaran', 50)->nullable();
                $table->decimal('penerima_langsung', 20, 2)->default(0);
                $table->decimal('penerima_tidak_langsung', 20, 2)->default(0);
                $table->decimal('penerima_penunjang', 20, 2)->default(0);
                $table->longText('sumber_pembiayaan')->nullable();
                $table->longText('sifat_bantuan')->nullable();
                $table->longText('lokasi')->nullable();
                $table->decimal('alokasi_anggaran', 20, 2)->default(0);
                $table->decimal('realisasi_sem1', 20, 2)->default(0);
                $table->decimal('realisasi_sem2', 20, 2)->default(0);
                $table->decimal('realisasi_total', 20, 2)->default(0);
                // FK tanpa ON DELETE (= RESTRICT), sama dengan database produksi.
                $table->foreignId('created_by')->nullable()->constrained('users');
                $table->foreignId('updated_by')->nullable()->constrained('users');
                $table->timestamps();

                // Nama default ini DIHAPUS oleh 2026_07_02_090000_add_soft_deletes_to_laporan_oppkpke_table
                // (diganti laporan_sk_tahun_deleted_unique). Jangan diganti namanya.
                $table->unique(['sub_kegiatan_id', 'tahun', 'semester']);
            });
        }
    }

    public function down(): void
    {
        // Sengaja kosong: menghapus tabel inti (dan seluruh laporan) lewat rollback
        // terlalu berbahaya. Hapus manual kalau memang perlu.
    }
};
