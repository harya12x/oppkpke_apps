<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pics', function (Blueprint $table) {
            $table->id();
            // PIC melekat pada perangkat daerah (bisa dilihat semua operator PD tsb).
            $table->foreignId('perangkat_daerah_id')->constrained('perangkat_daerah')->cascadeOnDelete();
            // Operator yang menambahkan (untuk audit).
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nama_lengkap', 120);
            // NIK 16 digit — unik global: satu NIK hanya boleh satu PIC.
            $table->string('no_ktp', 16)->unique();
            $table->timestamps();

            $table->index('perangkat_daerah_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pics');
    }
};
