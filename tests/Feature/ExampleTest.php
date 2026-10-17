<?php

namespace Tests\Feature;

use App\Models\LaporanOppkpke;
use App\Models\User;
use Database\Seeders\OppkpkeMasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Uji asap aplikasi + skema dari database KOSONG (cara vps1 memulai).
 *
 * Butuh MySQL (beberapa migration memakai sintaks khusus MySQL: DELETE ... JOIN,
 * ALTER ... MODIFY ENUM). CI menjalankannya terhadap MySQL 8.4; di laptop:
 *   DB_CONNECTION=mysql DB_DATABASE=<db uji kosong> php artisan test
 */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_tamu_di_beranda_diarahkan_ke_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_halaman_login_tampil_dengan_header_keamanan(): void
    {
        $res = $this->get('/login');

        $res->assertOk();
        $res->assertSee('id="loginForm"', false);
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("frame-ancestors 'none'", (string) $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', (string) $res->headers->get('Cache-Control'));
    }

    public function test_health_check_up(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_halaman_aplikasi_wajib_login(): void
    {
        $this->get('/oppkpke/dashboard')->assertRedirect(route('login'));
    }

    public function test_admin_master_bisa_login(): void
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => true]);

        $res = $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $res->assertRedirect(route('oppkpke.matrix'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_akun_nonaktif_ditolak(): void
    {
        $user = User::factory()->create(['role' => 'master', 'is_active' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_migration_membuat_tabel_inti_dari_database_kosong(): void
    {
        foreach (['strategi_oppkpke', 'perangkat_daerah', 'programs', 'kegiatan', 'sub_kegiatan', 'laporan_oppkpke'] as $t) {
            $this->assertTrue(Schema::hasTable($t), "tabel $t tidak ada");
        }
        // Kolom yang ditambahkan migration lanjutan di atas tabel inti.
        $this->assertTrue(Schema::hasColumn('strategi_oppkpke', 'urutan'));
        $this->assertTrue(Schema::hasColumn('laporan_oppkpke', 'aktivitas_langsung'));
        $this->assertTrue(Schema::hasColumn('laporan_oppkpke', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('users', 'role'));
    }

    public function test_data_master_dan_laporan_soft_delete(): void
    {
        $this->seed(OppkpkeMasterDataSeeder::class);

        $this->assertSame(3, DB::table('strategi_oppkpke')->count());
        $this->assertGreaterThan(0, DB::table('sub_kegiatan')->count());

        $sub = (int) DB::table('sub_kegiatan')->orderBy('id')->value('id');
        $user = User::factory()->create(['role' => 'master']);

        $lama = LaporanOppkpke::create([
            'sub_kegiatan_id' => $sub, 'tahun' => 2026, 'semester' => 2,
            'alokasi_anggaran' => 1000000, 'created_by' => $user->id,
        ]);
        $lama->delete();   // soft delete

        // laporan_sk_tahun_deleted_unique: laporan baru untuk sub kegiatan + tahun
        // yang sama boleh dibuat setelah yang lama dihapus (soft).
        LaporanOppkpke::create([
            'sub_kegiatan_id' => $sub, 'tahun' => 2026, 'semester' => 2,
            'alokasi_anggaran' => 2000000, 'created_by' => $user->id,
        ]);

        $this->assertSame(1, LaporanOppkpke::where('sub_kegiatan_id', $sub)->where('tahun', 2026)->count());
        $this->assertSame(2, LaporanOppkpke::withTrashed()->where('sub_kegiatan_id', $sub)->count());
    }
}
