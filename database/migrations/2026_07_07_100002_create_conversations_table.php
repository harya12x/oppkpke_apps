<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Percakapan (tiket support) antara Operator Daerah dengan Tim IT.
     * Mengikuti prinsip CHATKAFKA.md: PK berbasis ULID (ordered, aman untuk
     * indeks & sharding di masa depan) dan conversation_id sebagai kunci
     * pengurutan pesan.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->ulid('id')->primary();

            // Operator Daerah yang membuka percakapan + unit perangkat daerahnya.
            $table->unsignedBigInteger('initiator_id');
            $table->unsignedBigInteger('perangkat_daerah_id')->nullable();

            // Anggota Tim IT yang menangani (opsional — bisa di-assign belakangan).
            $table->unsignedBigInteger('assigned_to')->nullable();

            $table->string('subject', 160)->nullable();
            $table->enum('status', ['open', 'pending', 'resolved', 'closed'])->default('open');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Dipakai untuk sorting inbox & deteksi unread tanpa join ke messages.
            $table->timestamp('last_message_at')->nullable();

            $table->timestamps();

            $table->foreign('initiator_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('perangkat_daerah_id')->references('id')->on('perangkat_daerah')->nullOnDelete();
            $table->foreign('assigned_to')->references('id')->on('users')->nullOnDelete();

            $table->index('status');
            $table->index('initiator_id');
            $table->index(['status', 'last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
