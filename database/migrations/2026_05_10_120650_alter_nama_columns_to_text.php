<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->text('nama_sub_kegiatan')->change();
        });

        Schema::table('kegiatan', function (Blueprint $table) {
            $table->text('nama_kegiatan')->change();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->text('nama_program')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sub_kegiatan', function (Blueprint $table) {
            $table->string('nama_sub_kegiatan', 500)->change();
        });

        Schema::table('kegiatan', function (Blueprint $table) {
            $table->string('nama_kegiatan', 500)->change();
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->string('nama_program', 500)->change();
        });
    }
};
