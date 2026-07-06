<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Tambah role 'it_team' (Tim IT) ke enum kolom users.role.
     * MySQL enum diubah lewat raw statement karena paling andal untuk
     * modifikasi enum (tidak butuh doctrine/dbal).
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('master','daerah','it_team') NOT NULL DEFAULT 'daerah'");
    }

    public function down(): void
    {
        // Turunkan role it_team menjadi daerah agar tidak menyisakan nilai enum yatim.
        DB::statement("UPDATE users SET role = 'daerah' WHERE role = 'it_team'");
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('master','daerah') NOT NULL DEFAULT 'daerah'");
    }
};
