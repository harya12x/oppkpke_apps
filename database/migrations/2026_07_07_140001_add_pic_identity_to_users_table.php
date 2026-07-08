<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Identitas PIC (Person In Charge) penginput laporan.
     * Wajib dilengkapi Operator Daerah sebelum menginput laporan agar setiap
     * entri dapat ditelusuri ke individu nyata (bukan sekadar akun unit) di
     * audit log yang dipantau Tim IT.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nama_lengkap', 120)->nullable()->after('name');
            $table->string('no_ktp', 16)->nullable()->after('nama_lengkap');
            $table->timestamp('pic_completed_at')->nullable()->after('no_ktp');

            $table->index('no_ktp');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['no_ktp']);
            $table->dropColumn(['nama_lengkap', 'no_ktp', 'pic_completed_at']);
        });
    }
};
