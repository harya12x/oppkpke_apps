<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pengumuman / informasi maintenance. Dikelola oleh Admin Master & Tim IT,
     * dan ditampilkan sebagai banner ke SEMUA role KECUALI Tim IT
     * (Tim IT yang menerbitkan, jadi tidak perlu melihat banner-nya sendiri).
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->text('body');

            // Menentukan warna/ikon banner.
            $table->enum('type', ['info', 'warning', 'maintenance', 'critical'])->default('info');

            $table->boolean('is_active')->default(true);

            // Jendela tayang opsional — jika null berarti selalu tayang selama is_active.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['is_active', 'starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcements');
    }
};
