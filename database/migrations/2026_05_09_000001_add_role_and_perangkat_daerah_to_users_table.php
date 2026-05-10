<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['master', 'daerah'])->default('daerah')->after('email');
            $table->unsignedBigInteger('perangkat_daerah_id')->nullable()->after('role');
            $table->foreign('perangkat_daerah_id')->references('id')->on('perangkat_daerah')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['perangkat_daerah_id']);
            $table->dropColumn(['role', 'perangkat_daerah_id']);
        });
    }
};
