<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda baca per (percakapan, user). Dibuat lazily saat user membuka
     * sebuah percakapan. Badge unread = last_message_at > last_read_at.
     * Baris Tim IT tercipta on-demand karena anggota Tim IT tidak
     * di-preassign sebagai partisipan setiap tiket.
     */
    public function up(): void
    {
        Schema::create('conversation_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('user_id');
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['conversation_id', 'user_id'], 'conv_user_read_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_reads');
    }
};
