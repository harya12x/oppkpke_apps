<?php

namespace App\Services;

use App\Models\Kegiatan;
use App\Models\PerangkatDaerah;
use App\Models\Program;
use App\Models\StrategiOppkpke;
use App\Models\SubKegiatan;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Reader\Csv as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xls as XlsReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as XlsxReader;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Import HIERARKI via Excel (Admin) — Perangkat Daerah → Strategi → Program →
 * Kegiatan → Sub Kegiatan. TANPA nilai laporan (anggaran/realisasi diisi terpisah).
 *
 * Alur: unduh template → upload → analisis/preview (validasi + resolusi) →
 * eksekusi (find-or-create dalam satu transaksi).
 */
class HierarkiImportService
{
    /** Header kolom template (urut A..H). */
    public const HEADERS = [
        'Perangkat Daerah',
        'Strategi (kode/nama)',
        'Kode Program',
        'Nama Program',
        'Kode Kegiatan',
        'Nama Kegiatan',
        'Kode Sub Kegiatan',
        'Nama Sub Kegiatan',
    ];

    private function normalize(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strtolower(preg_replace('/[^a-z0-9]+/i', ' ', $s))));
    }

    /**
     * Kode program aman terhadap unique (strategi_id, perangkat_daerah_id,
     * kode_program). Kode kosong dipakai apa adanya bila belum ada; bila '' sudah
     * terpakai di (strategi, PD), dibuat kode unik otomatis agar tidak menabrak.
     */
    private function kodeProgram(string $kode, int $pdId, int $strategiId): string
    {
        if ($kode !== '') {
            return $kode;
        }
        $taken = Program::where('perangkat_daerah_id', $pdId)
            ->where('strategi_id', $strategiId)
            ->where('kode_program', '')
            ->exists();

        return $taken ? 'AUTO-' . strtoupper(substr(md5(uniqid('', true)), 0, 8)) : '';
    }

    // ── PEMBACAAN FILE ────────────────────────────────────────────────────

    /**
     * Baca file jadi array baris mentah [pd, strategi, kode_prog, nama_prog,
     * kode_keg, nama_keg, kode_sub, nama_sub]. Baris 1 = header (dilewati).
     *
     * @return array<int, array<string, string>>
     */
    public function readFile(string $path, string $ext): array
    {
        $ext = strtolower($ext);
        if ($ext === 'csv' || $ext === 'txt') {
            $reader = new CsvReader();
            $reader->setDelimiter(',');
            $reader->setInputEncoding('UTF-8');
        } elseif ($ext === 'xls') {
            $reader = new XlsReader();
        } else {
            $reader = new XlsxReader();
        }
        $reader->setReadDataOnly(true);
        $sheet  = $reader->load($path)->getActiveSheet();
        $maxRow = $sheet->getHighestRow();

        $keys = ['pd', 'strategi', 'kode_program', 'nama_program', 'kode_kegiatan', 'nama_kegiatan', 'kode_sub', 'nama_sub'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];
        $rows = [];

        for ($r = 2; $r <= $maxRow; $r++) {
            $vals = [];
            foreach ($cols as $i => $letter) {
                $raw = $sheet->getCell($letter . $r)->getValue();
                $vals[$keys[$i]] = is_null($raw) ? '' : trim((string) $raw);
            }
            // Lewati baris kosong.
            if (count(array_filter($vals, fn ($v) => $v !== '')) === 0) {
                continue;
            }
            $vals['baris'] = $r;
            $rows[] = $vals;
        }

        return $rows;
    }

    // ── ANALISIS / PREVIEW ────────────────────────────────────────────────

    /**
     * Validasi + resolusi tiap baris (tanpa menulis DB). Menambahkan:
     * strategi_id, strategi_nama, pd_status (baru/ada), valid, error.
     *
     * @return array{rows: array<int,array<string,mixed>>, stats: array<string,int>}
     */
    public function analyze(array $rawRows): array
    {
        $strategis = StrategiOppkpke::where('is_active', true)->get(['id', 'kode', 'nama']);
        $pdAll     = PerangkatDaerah::get(['id', 'nama']);

        $rows = [];
        foreach ($rawRows as $row) {
            $errors = [];

            $pdNama = $row['pd'];
            if ($pdNama === '') {
                $errors[] = 'Perangkat Daerah kosong';
            }

            // Resolusi strategi (kode persis atau nama).
            $strategi = null;
            if ($row['strategi'] === '') {
                $errors[] = 'Strategi kosong';
            } else {
                $needle = $this->normalize($row['strategi']);
                $strategi = $strategis->first(fn ($s) => strcasecmp(trim($s->kode), trim($row['strategi'])) === 0)
                    ?? $strategis->first(fn ($s) => $this->normalize($s->nama) === $needle)
                    ?? $strategis->first(fn ($s) => $needle !== '' && str_contains($this->normalize($s->nama), $needle));
                if (! $strategi) {
                    $errors[] = 'Strategi "' . $row['strategi'] . '" tidak dikenali';
                }
            }

            if ($row['nama_sub'] === '') {
                $errors[] = 'Nama Sub Kegiatan kosong';
            }

            // PD sudah ada?
            $pdMatch = $pdNama === '' ? null : $pdAll->first(fn ($p) =>
                $this->normalize($p->nama) === $this->normalize($pdNama) ||
                (($n = $this->normalize($pdNama)) !== '' && str_contains($this->normalize($p->nama), $n))
            );

            $rows[] = array_merge($row, [
                'strategi_id'   => $strategi?->id,
                'strategi_nama' => $strategi?->nama,
                'pd_status'     => $pdMatch ? 'ada' : 'baru',
                'valid'         => empty($errors),
                'error'         => implode('; ', $errors),
            ]);
        }

        $coll  = collect($rows);
        $stats = [
            'total'    => $coll->count(),
            'valid'    => $coll->where('valid', true)->count(),
            'error'    => $coll->where('valid', false)->count(),
            'pd_baru'  => $coll->where('valid', true)->where('pd_status', 'baru')->pluck('pd')->map(fn ($v) => $this->normalize($v))->unique()->count(),
        ];

        return ['rows' => $rows, 'stats' => $stats];
    }

    // ── EKSEKUSI ──────────────────────────────────────────────────────────

    /**
     * Buat hierarki dari baris valid (find-or-create) dalam satu transaksi.
     *
     * @return array<string,int>
     */
    public function execute(array $rows): array
    {
        $c = [
            'pd_created' => 0, 'program_created' => 0, 'kegiatan_created' => 0,
            'sub_created' => 0, 'sub_existing' => 0, 'processed' => 0, 'skipped' => 0,
        ];

        DB::transaction(function () use ($rows, &$c) {
            foreach ($rows as $row) {
                if (empty($row['valid']) || empty($row['strategi_id'])) {
                    $c['skipped']++;
                    continue;
                }

                // PERANGKAT DAERAH (find-or-create).
                $pd = PerangkatDaerah::all()->first(fn ($p) =>
                    $this->normalize($p->nama) === $this->normalize($row['pd']) ||
                    (($n = $this->normalize($row['pd'])) !== '' && str_contains($this->normalize($p->nama), $n))
                );
                if (! $pd) {
                    $pd = PerangkatDaerah::create(['nama' => $row['pd'], 'is_active' => true]);
                    $c['pd_created']++;
                }

                // PROGRAM (under pd + strategi).
                $namaProg = $row['nama_program'] !== '' ? $row['nama_program'] : 'Program (belum diisi)';
                $kodeProg = $row['kode_program'];
                // Cocokkan berdasarkan kode (bila ada) ATAU nama (termasuk placeholder,
                // agar baris kosong tak menggandakan & tak menabrak unique kode).
                $program = Program::where('perangkat_daerah_id', $pd->id)->where('strategi_id', $row['strategi_id'])->get()->first(fn ($p) =>
                    ($kodeProg !== '' && strcasecmp(trim($p->kode_program), $kodeProg) === 0) ||
                    $this->normalize($p->nama_program) === $this->normalize($namaProg)
                );
                if (! $program) {
                    $program = Program::create([
                        'strategi_id'         => $row['strategi_id'],
                        'perangkat_daerah_id' => $pd->id,
                        'kode_program'        => $this->kodeProgram($kodeProg, $pd->id, (int) $row['strategi_id']),
                        'nama_program'        => $namaProg,
                        'is_active'           => true,
                    ]);
                    $c['program_created']++;
                }

                // KEGIATAN (under program) — cocokkan via nama (termasuk placeholder) agar idempoten.
                $namaKeg = $row['nama_kegiatan'] !== '' ? $row['nama_kegiatan'] : 'Kegiatan (belum diisi)';
                $kegiatan = Kegiatan::where('program_id', $program->id)->get()->first(fn ($k) =>
                    $this->normalize($k->nama_kegiatan) === $this->normalize($namaKeg)
                );
                if (! $kegiatan) {
                    $kegiatan = Kegiatan::create([
                        'program_id'    => $program->id,
                        'kode'          => $row['kode_kegiatan'] ?: null,
                        'nama_kegiatan' => $namaKeg,
                        'is_active'     => true,
                    ]);
                    $c['kegiatan_created']++;
                }

                // SUB KEGIATAN (under kegiatan) — tak menggandakan yang sudah ada.
                $namaSub = $row['nama_sub'];
                $sub = SubKegiatan::where('kegiatan_id', $kegiatan->id)->get()->first(fn ($s) =>
                    $this->normalize($s->nama_sub_kegiatan) === $this->normalize($namaSub)
                );
                if ($sub) {
                    $c['sub_existing']++;
                } else {
                    SubKegiatan::create([
                        'kegiatan_id'       => $kegiatan->id,
                        'kode'              => $row['kode_sub'] ?: null,
                        'nama_sub_kegiatan' => $namaSub,
                        'is_active'         => true,
                    ]);
                    $c['sub_created']++;
                }

                $c['processed']++;
            }
        });

        return $c;
    }

    // ── TEMPLATE ──────────────────────────────────────────────────────────

    /** Bangun spreadsheet template (2 sheet: Data + Petunjuk). */
    public function buildTemplate(): Spreadsheet
    {
        $ss    = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Data');

        $letters = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

        // Header.
        foreach (self::HEADERS as $i => $h) {
            $sheet->setCellValue($letters[$i] . '1', $h);
        }
        $sheet->getStyle('A1:H1')->getFont()->setBold(true);
        $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('4F46E5');
        $sheet->getStyle('A1:H1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1:H1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Contoh baris.
        $contoh = [
            ['Dinas Sosial', '1', '1.06.05', 'Program Pemberdayaan Sosial', '', 'Pemberdayaan Sosial KAT', '', 'Fasilitasi Bantuan Pengembangan Ekonomi Masyarakat'],
            ['Dinas Sosial', '1', '1.06.05', 'Program Pemberdayaan Sosial', '', 'Pemberdayaan Sosial KAT', '', 'Peningkatan Kemampuan Potensi Sumber Kesejahteraan Sosial'],
        ];
        $rowIdx = 2;
        foreach ($contoh as $baris) {
            foreach ($baris as $i => $val) {
                $sheet->setCellValue($letters[$i] . $rowIdx, $val);
            }
            $rowIdx++;
        }

        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setWidth(28);
        }
        $sheet->freezePane('A2');

        // Sheet Petunjuk + daftar strategi.
        $info = $ss->createSheet();
        $info->setTitle('Petunjuk');
        $info->setCellValue('A1', 'PETUNJUK PENGISIAN IMPORT HIERARKI');
        $info->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $lines = [
            '',
            '1. Isi mulai baris ke-2 pada sheet "Data". Baris 1 adalah judul kolom (jangan diubah/hapus).',
            '2. Satu baris = satu Sub Kegiatan lengkap dengan jalurnya (PD → Strategi → Program → Kegiatan → Sub).',
            '3. Kolom WAJIB: Perangkat Daerah, Strategi, Nama Sub Kegiatan.',
            '4. Kode & Nama Program/Kegiatan boleh dikosongkan (dibuat sebagai "belum diisi", bisa diubah operator).',
            '5. Strategi diisi dengan KODE atau NAMA sesuai daftar di bawah.',
            '6. Baris dengan nama Program/Kegiatan yang sama akan otomatis digabung ke satu Program/Kegiatan.',
            '7. Sub Kegiatan yang sudah ada tidak digandakan.',
            '8. File ini TIDAK mengisi anggaran/realisasi — itu diisi terpisah lewat "Isi Laporan".',
            '',
            'DAFTAR STRATEGI YANG VALID:',
        ];
        $r = 2;
        foreach ($lines as $l) {
            $info->setCellValue('A' . $r++, $l);
        }
        foreach (StrategiOppkpke::where('is_active', true)->orderBy('kode')->get() as $s) {
            $info->setCellValue('A' . $r++, 'Kode ' . $s->kode . '  —  ' . $s->nama);
        }
        $info->getColumnDimension('A')->setWidth(90);

        $ss->setActiveSheetIndex(0);

        return $ss;
    }
}
