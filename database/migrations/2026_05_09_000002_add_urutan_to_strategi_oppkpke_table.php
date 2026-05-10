<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('strategi_oppkpke', function (Blueprint $table) {
            if (!Schema::hasColumn('strategi_oppkpke', 'urutan')) {
                $table->unsignedTinyInteger('urutan')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('strategi_oppkpke', function (Blueprint $table) {
            $table->dropColumn('urutan');
        });
    }
};
