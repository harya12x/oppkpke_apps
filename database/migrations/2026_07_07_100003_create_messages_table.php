<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabel pesan — tabel paling cepat membengkak, jadi dirancang ramping.
     * Index komposit (conversation_id, id) mempercepat query
     * "ambil pesan terbaru dari sebuah percakapan" dan polling
     * "ambil pesan setelah id X". ULID bersifat monotonic sehingga
     * urutan berdasarkan id == urutan waktu (per CHATKAFKA.md).
     */
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->unsignedBigInteger('sender_id')->nullable();

            // text = pesan biasa dari user; system = catatan otomatis (status berubah, dsb).
            $table->enum('type', ['text', 'system'])->default('text');
            $table->text('body');

            $table->timestamps();

            $table->foreign('sender_id')->references('id')->on('users')->nullOnDelete();

            // Index krusial untuk sorting & polling per percakapan.
            $table->index(['conversation_id', 'id']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
