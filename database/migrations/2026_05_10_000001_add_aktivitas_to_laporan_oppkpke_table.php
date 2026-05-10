<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->text('aktivitas_langsung')->nullable()->after('jumlah_sasaran');
            $table->text('aktivitas_tidak_langsung')->nullable()->after('aktivitas_langsung');
            $table->text('aktivitas_penunjang')->nullable()->after('aktivitas_tidak_langsung');
        });
    }

    public function down(): void
    {
        Schema::table('laporan_oppkpke', function (Blueprint $table) {
            $table->dropColumn(['aktivitas_langsung', 'aktivitas_tidak_langsung', 'aktivitas_penunjang']);
        });
    }
};
