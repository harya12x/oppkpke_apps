<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perangkat_daerah', function (Blueprint $table) {
            $table->string('kode')->nullable()->change();
            $table->string('singkatan')->nullable()->change();
            $table->string('jenis')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('perangkat_daerah', function (Blueprint $table) {
            $table->string('kode')->nullable(false)->default('')->change();
            $table->string('singkatan')->nullable(false)->default('')->change();
            $table->string('jenis')->nullable(false)->default('')->change();
        });
    }
};
