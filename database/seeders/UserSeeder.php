<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Admin Master
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@oppkpke.go.id'],
            [
                'name'                => 'Administrator Master',
                'email'               => 'admin@oppkpke.go.id',
                'password'            => Hash::make('password123'),
                'role'                => 'master',
                'perangkat_daerah_id' => null,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        // Operator Dinas Pendidikan (perangkat_daerah_id = 1)
        DB::table('users')->updateOrInsert(
            ['email' => 'dikbud@oppkpke.go.id'],
            [
                'name'                => 'Operator Dinas Dikbud',
                'email'               => 'dikbud@oppkpke.go.id',
                'password'            => Hash::make('password123'),
                'role'                => 'daerah',
                'perangkat_daerah_id' => 1,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        // Operator Dinas Kesehatan (perangkat_daerah_id = 2)
        DB::table('users')->updateOrInsert(
            ['email' => 'dinkes@oppkpke.go.id'],
            [
                'name'                => 'Operator Dinas Kesehatan',
                'email'               => 'dinkes@oppkpke.go.id',
                'password'            => Hash::make('password123'),
                'role'                => 'daerah',
                'perangkat_daerah_id' => 2,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        // Operator BPBD (perangkat_daerah_id = 3)
        DB::table('users')->updateOrInsert(
            ['email' => 'bpbd@oppkpke.go.id'],
            [
                'name'                => 'Operator BPBD',
                'email'               => 'bpbd@oppkpke.go.id',
                'password'            => Hash::make('password123'),
                'role'                => 'daerah',
                'perangkat_daerah_id' => 3,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        // Operator Dinas Sosial (perangkat_daerah_id = 4)
        DB::table('users')->updateOrInsert(
            ['email' => 'dinsos@oppkpke.go.id'],
            [
                'name'                => 'Operator Dinas Sosial',
                'email'               => 'dinsos@oppkpke.go.id',
                'password'            => Hash::make('password123'),
                'role'                => 'daerah',
                'perangkat_daerah_id' => 4,
                'created_at'          => now(),
                'updated_at'          => now(),
            ]
        );

        $this->command->info('✅ Users seeded successfully.');
        $this->command->table(
            ['Email', 'Role', 'Password'],
            [
                ['admin@oppkpke.go.id',  'master', 'password123'],
                ['dikbud@oppkpke.go.id', 'daerah', 'password123'],
                ['dinkes@oppkpke.go.id', 'daerah', 'password123'],
                ['bpbd@oppkpke.go.id',   'daerah', 'password123'],
                ['dinsos@oppkpke.go.id', 'daerah', 'password123'],
            ]
        );
    }
}
