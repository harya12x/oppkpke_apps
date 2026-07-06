<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengerasan skema chat:
     * - initiator_id: cascadeOnDelete → nullOnDelete (+ nullable). Menghapus akun
     *   operator TIDAK lagi menghancurkan riwayat percakapan/pesan (H1).
     * - soft delete pada conversations & messages agar penghapusan reversibel
     *   dan menyisakan jejak audit.
     */
    public function up(): void
    {
        // Lepas FK lama sebelum mengubah kolom.
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['initiator_id']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('initiator_id')->nullable()->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('initiator_id')->references('id')->on('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['initiator_id']);
            $table->dropSoftDeletes();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('initiator_id')->nullable(false)->change();
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreign('initiator_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }
};
