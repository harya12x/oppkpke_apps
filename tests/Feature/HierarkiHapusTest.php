<?php

namespace Tests\Feature;

use App\Models\HierarkiHapus;
use App\Models\Kegiatan;
use App\Models\LaporanOppkpke;
use App\Models\Program;
use App\Models\SubKegiatan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Hapus program/kegiatan/sub kegiatan dari Input Data → "Data Terhapus" → Kembalikan.
 * Butuh MySQL (lihat ExampleTest): DB_CONNECTION=mysql DB_DATABASE=<db uji kosong> php artisan test
 */
class HierarkiHapusTest extends TestCase
{
    use RefreshDatabase;

    private int $pdA;
    private int $pdB;
    private int $strategi;
    private Program $progA;
    private Kegiatan $keg1;
    private Kegiatan $keg2;
    private SubKegiatan $sub1a;
    private SubKegiatan $sub1b;
    private SubKegiatan $sub2a;
    private Program $progB;
    private User $opA;
    private User $opB;
    private User $master;

    protected function setUp(): void
    {
        parent::setUp();

        $this->strategi = DB::table('strategi_oppkpke')->insertGetId([
            'kode' => 'S1', 'nama' => 'Pengurangan Beban Pengeluaran', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pdA = DB::table('perangkat_daerah')->insertGetId([
            'kode' => 'PD-A', 'nama' => 'Badan Penanggulangan Bencana Daerah', 'jenis' => 'Badan', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->pdB = DB::table('perangkat_daerah')->insertGetId([
            'kode' => 'PD-B', 'nama' => 'Dinas Sosial', 'jenis' => 'Dinas', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->progA = Program::create(['strategi_id' => $this->strategi, 'perangkat_daerah_id' => $this->pdA, 'kode_program' => '1.05.03', 'nama_program' => 'Program Pengelolaan Air Limbah', 'is_active' => true]);
        $this->keg1  = Kegiatan::create(['program_id' => $this->progA->id, 'nama_kegiatan' => 'Kegiatan Satu', 'is_active' => true]);
        $this->keg2  = Kegiatan::create(['program_id' => $this->progA->id, 'nama_kegiatan' => 'Kegiatan Dua', 'is_active' => true]);
        $this->sub1a = SubKegiatan::create(['kegiatan_id' => $this->keg1->id, 'nama_sub_kegiatan' => 'Gladi Kesiapsiagaan', 'is_active' => true]);
        $this->sub1b = SubKegiatan::create(['kegiatan_id' => $this->keg1->id, 'nama_sub_kegiatan' => 'Pelatihan Mitigasi', 'is_active' => true]);
        $this->sub2a = SubKegiatan::create(['kegiatan_id' => $this->keg2->id, 'nama_sub_kegiatan' => 'Penguatan Kelembagaan', 'is_active' => true]);

        $this->progB = Program::create(['strategi_id' => $this->strategi, 'perangkat_daerah_id' => $this->pdB, 'kode_program' => '1.06.01', 'nama_program' => 'Program Dinsos', 'is_active' => true]);
        $kegB = Kegiatan::create(['program_id' => $this->progB->id, 'nama_kegiatan' => 'Kegiatan Dinsos', 'is_active' => true]);
        $subB = SubKegiatan::create(['kegiatan_id' => $kegB->id, 'nama_sub_kegiatan' => 'Bantuan Sosial', 'is_active' => true]);

        $this->laporan($this->sub1a->id, 2025, 100, 50);
        $this->laporan($this->sub1a->id, 2026, 200, 0);
        $this->laporan($this->sub2a->id, 2026, 300, 10);
        $this->laporan($subB->id, 2026, 1000, 0);

        $pic = ['nama_lengkap' => 'Budi Santoso', 'no_ktp' => '6302010101010001'];
        $this->opA    = User::factory()->create(['role' => 'daerah', 'perangkat_daerah_id' => $this->pdA, 'is_active' => true] + $pic);
        $this->opB    = User::factory()->create(['role' => 'daerah', 'perangkat_daerah_id' => $this->pdB, 'is_active' => true, 'nama_lengkap' => 'Siti', 'no_ktp' => '6302010101010002']);
        $this->master = User::factory()->create(['role' => 'master', 'is_active' => true]);
    }

    private function laporan(int $sub, int $tahun, float $alokasi, float $realisasi): LaporanOppkpke
    {
        return LaporanOppkpke::create([
            'sub_kegiatan_id' => $sub, 'tahun' => $tahun, 'semester' => 2,
            'alokasi_anggaran' => $alokasi, 'realisasi_sem1' => $realisasi, 'realisasi_total' => $realisasi,
        ]);
    }

    private function hapus(User $u, string $jenis, int $id)
    {
        return $this->actingAs($u)->deleteJson(route('oppkpke.hierarki.hapus', ['jenis' => $jenis, 'id' => $id]));
    }

    private function kembalikan(User $u, int $batch)
    {
        return $this->actingAs($u)->postJson(route('oppkpke.hierarki.kembalikan', ['batch' => $batch]));
    }

    private function totalAlokasi(int $tahun): float
    {
        return (float) LaporanOppkpke::where('tahun', $tahun)->sum('alokasi_anggaran');
    }

    public function test_hapus_program_masuk_data_terhapus_lalu_kembali_utuh(): void
    {
        $this->actingAs($this->opA)
            ->getJson(route('oppkpke.hierarki.hapus.info', ['jenis' => 'program', 'id' => $this->progA->id]))
            ->assertOk()
            ->assertJsonPath('data.jumlah_kegiatan', 2)
            ->assertJsonPath('data.jumlah_sub', 3)
            ->assertJsonPath('data.jumlah_laporan', 3);

        $this->assertSame(1500.0, $this->totalAlokasi(2026));

        $batch = $this->hapus($this->opA, 'program', $this->progA->id)->assertOk()->json('batch_id');

        // Hilang dari layar & dari total, tetapi barisnya masih ada.
        $this->assertFalse((bool) $this->progA->fresh()->is_active);
        $this->assertFalse((bool) $this->keg1->fresh()->is_active);
        $this->assertFalse((bool) $this->sub2a->fresh()->is_active);
        $this->assertSame(1000.0, $this->totalAlokasi(2026));
        $this->assertSame(0.0, $this->totalAlokasi(2025));
        $this->assertSame(4, LaporanOppkpke::withTrashed()->count());
        $this->actingAs($this->opA)->getJson(route('oppkpke.explorer.data', ['tahun' => 2026]))
            ->assertOk()->assertJsonCount(0, 'data');

        $this->actingAs($this->opA)->getJson(route('oppkpke.hierarki.sampah'))
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nama', 'Program Pengelolaan Air Limbah')
            ->assertJsonPath('data.0.dihapus_oleh', 'Budi Santoso');

        $this->kembalikan($this->opA, $batch)->assertOk()->assertJsonPath('dilewati', 0);

        $this->assertTrue((bool) $this->progA->fresh()->is_active);
        $this->assertTrue((bool) $this->sub1b->fresh()->is_active);
        $this->assertSame(1500.0, $this->totalAlokasi(2026));
        $this->assertSame(100.0, $this->totalAlokasi(2025));
        $this->assertSame(0, DB::table('programs')->whereNotNull('hapus_batch_id')->count()
            + DB::table('laporan_oppkpke')->whereNotNull('hapus_batch_id')->count());
        $this->assertNotNull(HierarkiHapus::find($batch)->dipulihkan_pada);
        $this->actingAs($this->opA)->getJson(route('oppkpke.hierarki.sampah'))->assertJsonCount(0, 'data');
        $this->actingAs($this->opA)->getJson(route('oppkpke.explorer.data', ['tahun' => 2026]))->assertJsonCount(3, 'data');

        // Dua kali kembalikan → ditolak dengan sopan.
        $this->kembalikan($this->opA, $batch)->assertStatus(409);
    }

    public function test_operator_hanya_bisa_mengurus_perangkat_daerahnya_sendiri(): void
    {
        $this->hapus($this->opB, 'program', $this->progA->id)->assertStatus(403);
        $this->actingAs($this->opB)
            ->getJson(route('oppkpke.hierarki.hapus.info', ['jenis' => 'program', 'id' => $this->progA->id]))
            ->assertStatus(403);
        $this->assertTrue((bool) $this->progA->fresh()->is_active);

        $batch = $this->hapus($this->opA, 'sub-kegiatan', $this->sub1a->id)->assertOk()->json('batch_id');

        $this->actingAs($this->opB)->getJson(route('oppkpke.hierarki.sampah'))->assertJsonCount(0, 'data');
        $this->kembalikan($this->opB, $batch)->assertStatus(403);
        $this->assertFalse((bool) $this->sub1a->fresh()->is_active);

        // Admin Master boleh semua perangkat daerah.
        $this->actingAs($this->master)->getJson(route('oppkpke.hierarki.sampah'))->assertJsonCount(1, 'data');
        $this->kembalikan($this->master, $batch)->assertOk();
        $this->assertTrue((bool) $this->sub1a->fresh()->is_active);
    }

    public function test_hapus_yang_sudah_terhapus_ditolak(): void
    {
        $this->hapus($this->opA, 'kegiatan', $this->keg1->id)->assertOk();
        $this->hapus($this->opA, 'kegiatan', $this->keg1->id)->assertStatus(404);
        $this->hapus($this->opA, 'sub-kegiatan', $this->sub1a->id)->assertStatus(404);
        $this->assertSame(1, HierarkiHapus::count());
    }

    public function test_kembalikan_anak_menunggu_induknya_dikembalikan(): void
    {
        $bSub = $this->hapus($this->opA, 'sub-kegiatan', $this->sub1a->id)->json('batch_id');
        $bKeg = $this->hapus($this->opA, 'kegiatan', $this->keg1->id)->json('batch_id');

        // Batch kegiatan hanya berisi sub yang MASIH aktif saat itu (sub1b), bukan sub1a.
        $this->assertSame($bKeg, (int) $this->sub1b->fresh()->hapus_batch_id);
        $this->assertSame($bSub, (int) $this->sub1a->fresh()->hapus_batch_id);

        $this->kembalikan($this->opA, $bSub)->assertStatus(422)
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'Kegiatan Satu') && str_contains($m, 'terlebih dahulu'));
        $this->assertFalse((bool) $this->sub1a->fresh()->is_active);

        $this->kembalikan($this->opA, $bKeg)->assertOk();
        $this->assertTrue((bool) $this->sub1b->fresh()->is_active);
        $this->assertFalse((bool) $this->sub1a->fresh()->is_active);   // tetap di batch-nya sendiri

        $this->kembalikan($this->opA, $bSub)->assertOk();
        $this->assertTrue((bool) $this->sub1a->fresh()->is_active);
        $this->assertSame(1500.0, $this->totalAlokasi(2026));
    }

    public function test_data_baru_ke_item_terhapus_mengaktifkannya_tanpa_dobel(): void
    {
        $batch = $this->hapus($this->opA, 'sub-kegiatan', $this->sub1a->id)->json('batch_id');
        $this->assertSame(1300.0, $this->totalAlokasi(2026));

        // Mis. import RAT menulis laporan 2026 ke sub kegiatan yang sedang terhapus.
        $this->laporan($this->sub1a->id, 2026, 999, 0);

        $this->assertTrue((bool) $this->sub1a->fresh()->is_active, 'laporan aktif tak boleh menempel di item terhapus');
        $this->assertNull($this->sub1a->fresh()->hapus_batch_id);

        $daftar = $this->actingAs($this->opA)->getJson(route('oppkpke.hierarki.sampah'))->assertJsonCount(1, 'data');
        $this->assertTrue($daftar->json('data.0.sebagian_aktif'));

        // Kembalikan: 2025 pulih; 2026 lama dilewati karena sudah ada laporan baru.
        $this->kembalikan($this->opA, $batch)->assertOk()->assertJsonPath('dilewati', 1);
        $this->assertSame(1, LaporanOppkpke::where('sub_kegiatan_id', $this->sub1a->id)->where('tahun', 2026)->count());
        $this->assertSame(999.0, (float) LaporanOppkpke::where('sub_kegiatan_id', $this->sub1a->id)->where('tahun', 2026)->value('alokasi_anggaran'));
        $this->assertSame(100.0, $this->totalAlokasi(2025));
        $this->actingAs($this->opA)->getJson(route('oppkpke.hierarki.sampah'))->assertJsonCount(0, 'data');
    }

    public function test_sub_kegiatan_baru_di_bawah_induk_terhapus_mengaktifkan_induknya(): void
    {
        $batch = $this->hapus($this->opA, 'program', $this->progA->id)->json('batch_id');

        // Mis. import membuat sub kegiatan baru di kegiatan yang sedang terhapus.
        SubKegiatan::create(['kegiatan_id' => $this->keg2->id, 'nama_sub_kegiatan' => 'Sub Baru dari Import', 'is_active' => true]);

        $this->assertTrue((bool) $this->keg2->fresh()->is_active);
        $this->assertTrue((bool) $this->progA->fresh()->is_active);
        $this->assertFalse((bool) $this->keg1->fresh()->is_active);   // yang lain tetap di Data Terhapus

        $this->kembalikan($this->opA, $batch)->assertOk();
        $this->assertTrue((bool) $this->keg1->fresh()->is_active);
        $this->assertTrue((bool) $this->sub2a->fresh()->is_active);
        $this->assertSame(1500.0, $this->totalAlokasi(2026));
    }

    public function test_wizard_mengarahkan_ke_data_terhapus_bila_nama_sama(): void
    {
        $this->hapus($this->opA, 'program', $this->progA->id)->assertOk();

        $res = $this->actingAs($this->opA)->postJson(route('oppkpke.hierarki.store'), [
            'program_mode'      => 'new',
            'strategi_id'       => $this->strategi,
            'nama_program'      => 'Program Pengelolaan Air Limbah',
            'kegiatan_mode'     => 'new',
            'nama_kegiatan'     => 'Kegiatan X',
            'nama_sub_kegiatan' => 'Sub X',
        ]);

        $res->assertStatus(422);
        $this->assertStringContainsString('Data Terhapus', $res->json('message'));
        $this->assertSame(2, Program::count());
    }

    public function test_halaman_input_data_menampilkan_tombol_data_terhapus(): void
    {
        $this->actingAs($this->opA)->get(route('oppkpke.laporan.index'))
            ->assertOk()
            ->assertSee('Data Terhapus')
            ->assertSee('id="hpModal"', false)
            ->assertSee('id="spModal"', false);
    }
}
