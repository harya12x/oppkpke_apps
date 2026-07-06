<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lampiran file/gambar + jejak edit pada pesan chat.
     * File disimpan di disk privat (storage/app/chat-attachments) dan
     * disajikan lewat route unduh berotorisasi — path saja yang di-DB.
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('attachment_path', 255)->nullable()->after('body');
            $table->string('attachment_name', 160)->nullable()->after('attachment_path');
            $table->string('attachment_mime', 100)->nullable()->after('attachment_name');
            $table->unsignedInteger('attachment_size')->nullable()->after('attachment_mime');
            $table->timestamp('edited_at')->nullable()->after('attachment_size');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime', 'attachment_size', 'edited_at']);
        });
    }
};
