<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Data Terhapus" untuk program / kegiatan / sub kegiatan.
 *
 * Menghapus dari halaman Input Data TIDAK menghapus baris: node diberi
 * is_active = false + hapus_batch_id, laporannya di-soft-delete + hapus_batch_id.
 * Satu kali hapus = satu baris hierarki_hapus, sehingga operator bisa
 * mengembalikan persis apa yang ia hapus (tidak lebih, tidak kurang).
 */
return new class extends Migration
{
    private const TABEL = ['programs', 'kegiatan', 'sub_kegiatan', 'laporan_oppkpke'];

    public function up(): void
    {
        if (! Schema::hasTable('hierarki_hapus')) {
            Schema::create('hierarki_hapus', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('perangkat_daerah_id')->nullable()->index();
                $table->string('jenis', 20);                 // program | kegiatan | sub_kegiatan
                $table->unsignedBigInteger('objek_id');
                $table->text('nama');
                $table->text('lokasi')->nullable();          // "Perangkat Daerah › Program › Kegiatan"
                $table->unsignedInteger('jumlah_kegiatan')->default(0);
                $table->unsignedInteger('jumlah_sub')->default(0);
                $table->unsignedInteger('jumlah_laporan')->default(0);
                $table->json('ringkasan')->nullable();       // per tahun: jumlah, alokasi, realisasi
                $table->foreignId('dihapus_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->string('dihapus_oleh_nama')->nullable();
                $table->timestamp('dipulihkan_pada')->nullable()->index();
                $table->foreignId('dipulihkan_oleh')->nullable()->constrained('users')->nullOnDelete();
                $table->string('dipulihkan_oleh_nama')->nullable();
                $table->string('cara_pulih', 20)->nullable(); // manual | otomatis
                $table->timestamps();

                $table->index(['jenis', 'objek_id']);
            });
        }

        foreach (self::TABEL as $t) {
            if (! Schema::hasColumn($t, 'hapus_batch_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->unsignedBigInteger('hapus_batch_id')->nullable()->index();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABEL as $t) {
            if (Schema::hasColumn($t, 'hapus_batch_id')) {
                Schema::table($t, function (Blueprint $table) {
                    $table->dropIndex(['hapus_batch_id']);
                    $table->dropColumn('hapus_batch_id');
                });
            }
        }
        Schema::dropIfExists('hierarki_hapus');
    }
};
