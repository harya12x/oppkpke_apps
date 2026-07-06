<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * SEC4 — Audit trail terstruktur untuk aksi sensitif (kelola user,
     * pengumuman, perubahan status chat, login/logout). Immutable: hanya
     * insert, tidak pernah di-update.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();   // aktor (null = sistem/anonim)
            $table->string('actor_name', 120)->nullable();       // snapshot nama (tahan hapus user)
            $table->string('action', 60);                        // mis. user.created, announcement.deleted
            $table->string('auditable_type', 120)->nullable();   // model terkait (opsional)
            $table->string('auditable_id', 80)->nullable();      // id model (string agar muat ULID)
            $table->string('description', 255)->nullable();
            $table->json('properties')->nullable();              // konteks tambahan
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->nullable();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('action');
            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
