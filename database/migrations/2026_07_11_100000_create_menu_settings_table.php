<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('menu_settings')) {
            return;
        }

        Schema::create('menu_settings', function (Blueprint $table) {
            $table->id();
            $table->string('role', 20);        // master | daerah
            $table->string('menu_key', 50);    // kunci menu (lihat MenuManager::catalog)
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->unique(['role', 'menu_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_settings');
    }
};
