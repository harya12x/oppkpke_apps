<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Kedua unique key lama tidak memperhitungkan deleted_at, jadi merestore
        // laporan yang sudah soft-deleted untuk sub_kegiatan+tahun yang sama akan
        // langsung bentrok "sudah ada" pada baris yang seharusnya dianggap hilang.
        // Gabungkan jadi satu key yang soft-delete-aware; key semester-scoped sudah
        // redundan sejak laporan_sk_tahun_unique (lebih ketat) ditambahkan.
        //
        // Urutan penting: buat index baru DULU sebelum drop yang lama — MySQL
        // menolak drop index terakhir yang menopang FK sub_kegiatan_id (error 1553)
        // kalau tidak ada index pengganti yang sudah berdiri di titik itu.
        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->unique(['sub_kegiatan_id', 'tahun', 'deleted_at'], 'laporan_sk_tahun_deleted_unique');
        });

        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->dropUnique('laporan_oppkpke_sub_kegiatan_id_tahun_semester_unique');
            $table->dropUnique('laporan_sk_tahun_unique');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->unique(['sub_kegiatan_id', 'tahun', 'semester'], 'laporan_oppkpke_sub_kegiatan_id_tahun_semester_unique');
            $table->unique(['sub_kegiatan_id', 'tahun'], 'laporan_sk_tahun_unique');
        });

        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->dropUnique('laporan_sk_tahun_deleted_unique');
        });

        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
