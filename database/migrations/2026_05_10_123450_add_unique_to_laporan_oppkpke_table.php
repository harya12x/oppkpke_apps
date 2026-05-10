<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Remove duplicate (sub_kegiatan_id, tahun) rows — keep the one with highest id (most recent)
        DB::statement('
            DELETE lo1 FROM laporan_oppkpke lo1
            INNER JOIN laporan_oppkpke lo2
            WHERE lo1.sub_kegiatan_id = lo2.sub_kegiatan_id
              AND lo1.tahun = lo2.tahun
              AND lo1.id < lo2.id
        ');

        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->unique(['sub_kegiatan_id', 'tahun'], 'laporan_sk_tahun_unique');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->dropUnique('laporan_sk_tahun_unique');
        });
    }
};
