@php
    use Illuminate\Support\Carbon;

    $bulanID = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',
                7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
    $hariID  = ['Sunday'=>'Minggu','Monday'=>'Senin','Tuesday'=>'Selasa','Wednesday'=>'Rabu',
                'Thursday'=>'Kamis','Friday'=>'Jumat','Saturday'=>'Sabtu'];

    $fmtTanggal = function ($dt) use ($bulanID) {
        if (!$dt) return '—';
        $c = $dt instanceof Carbon ? $dt : Carbon::parse($dt);
        return $c->format('d') . ' ' . $bulanID[(int)$c->format('n')] . ' ' . $c->format('Y');
    };
    $fmtTanggalJam = function ($dt) use ($bulanID) {
        if (!$dt) return '—';
        $c = $dt instanceof Carbon ? $dt : Carbon::parse($dt);
        return $c->format('d') . ' ' . $bulanID[(int)$c->format('n')] . ' ' . $c->format('Y') . ', ' . $c->format('H:i');
    };

    $tglCetak = $hariID[$generatedAt->format('l')] . ', ' . $fmtTanggal($generatedAt);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Rekap Pengguna — OPPKPKE</title>
<style>
    :root {
        --primary:   #1e3a8a;   /* blue-900 */
        --primary-2: #1d4ed8;   /* blue-700 */
        --accent:    #d97706;   /* amber-600 */
        --ink:       #111827;
        --muted:     #6b7280;
        --line:      #e5e7eb;
        --line-2:    #d1d5db;
        --soft:      #f9fafb;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        font-family: 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
        color: var(--ink);
        background: #e5e7eb;
        font-size: 12px;
        line-height: 1.5;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /* ── Page sheet (mimics A4) ────────────────────────────── */
    .sheet {
        width: 297mm;            /* A4 landscape width */
        min-height: 210mm;
        margin: 16px auto;
        background: #fff;
        padding: 14mm 14mm 18mm;
        box-shadow: 0 4px 24px rgba(0,0,0,0.15);
        position: relative;
    }

    /* ── Letterhead ────────────────────────────────────────── */
    .kop {
        display: flex;
        align-items: center;
        gap: 16px;
        border-bottom: 3px solid var(--primary);
        padding-bottom: 12px;
    }
    .kop .logo {
        width: 64px; height: 64px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--primary), var(--primary-2));
        color: #fbbf24;
        display: flex; align-items: center; justify-content: center;
        font-size: 30px; flex-shrink: 0;
        box-shadow: inset 0 0 0 3px rgba(255,255,255,.35);
    }
    .kop .titles { flex: 1; text-align: center; line-height: 1.35; }
    .kop .titles .gov   { font-size: 13px; font-weight: 700; letter-spacing: .5px; }
    .kop .titles .org   { font-size: 18px; font-weight: 800; color: var(--primary); letter-spacing: .5px; }
    .kop .titles .sub   { font-size: 11px; color: var(--muted); }
    .kop .titles .addr  { font-size: 10px; color: var(--muted); margin-top: 2px; }
    .kop .spacer { width: 64px; flex-shrink: 0; }

    /* ── Document title ────────────────────────────────────── */
    .doc-title {
        text-align: center;
        margin: 16px 0 4px;
    }
    .doc-title h1 {
        font-size: 15px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;
    }
    .doc-title .line {
        width: 120px; height: 2px; background: var(--accent);
        margin: 6px auto 0; border-radius: 2px;
    }

    /* ── Meta + filters ────────────────────────────────────── */
    .meta {
        display: flex; justify-content: space-between; gap: 16px;
        font-size: 10.5px; color: var(--muted);
        margin: 14px 0 10px;
    }
    .meta b { color: var(--ink); font-weight: 600; }
    .chips { display: flex; flex-wrap: wrap; gap: 6px; justify-content: flex-end; }
    .chip {
        background: var(--soft); border: 1px solid var(--line-2);
        border-radius: 999px; padding: 2px 10px; font-size: 10px; color: #374151;
    }
    .chip b { color: var(--primary); }

    /* ── Summary cards ─────────────────────────────────────── */
    .stats {
        display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;
        margin: 12px 0 16px;
    }
    .stat {
        border: 1px solid var(--line); border-radius: 10px;
        padding: 10px 12px; text-align: center; background: #fff;
    }
    .stat .num { font-size: 22px; font-weight: 800; line-height: 1; }
    .stat .lbl { font-size: 10px; color: var(--muted); margin-top: 5px; text-transform: uppercase; letter-spacing: .5px; }
    .stat.total   { background: #f8fafc; }
    .stat.total   .num { color: #0f172a; }
    .stat.master  { background: #fffbeb; border-color: #fde68a; } .stat.master  .num { color: #b45309; }
    .stat.daerah  { background: #eff6ff; border-color: #bfdbfe; } .stat.daerah  .num { color: #1d4ed8; }
    .stat.active  { background: #f0fdf4; border-color: #bbf7d0; } .stat.active  .num { color: #15803d; }
    .stat.inactive{ background: #fef2f2; border-color: #fecaca; } .stat.inactive.num,
    .stat.inactive .num { color: #b91c1c; }

    /* ── Table ─────────────────────────────────────────────── */
    table { width: 100%; border-collapse: collapse; font-size: 11px; }
    thead th {
        background: var(--primary); color: #fff;
        font-weight: 600; text-align: left;
        padding: 8px 10px; border: 1px solid var(--primary);
        font-size: 10.5px; text-transform: uppercase; letter-spacing: .4px;
    }
    thead th.center { text-align: center; }
    tbody td {
        padding: 7px 10px; border: 1px solid var(--line);
        vertical-align: middle;
    }
    tbody tr:nth-child(even) td { background: var(--soft); }
    td.center { text-align: center; }
    td.num    { text-align: center; color: var(--muted); width: 36px; }

    .group-row td {
        background: #eef2ff !important;
        font-weight: 700; color: var(--primary);
        text-transform: uppercase; font-size: 10.5px; letter-spacing: .5px;
        padding: 6px 10px;
    }

    .uname { font-weight: 600; color: var(--ink); }
    .uemail { color: var(--muted); font-size: 10px; }

    .badge {
        display: inline-block; padding: 2px 9px; border-radius: 999px;
        font-size: 10px; font-weight: 600; white-space: nowrap;
    }
    .badge.master { background: #fef3c7; color: #92400e; }
    .badge.daerah { background: #dbeafe; color: #1e40af; }
    .badge.on     { background: #dcfce7; color: #166534; }
    .badge.off    { background: #fee2e2; color: #991b1b; }

    .pd-name { color: var(--ink); }
    .pd-jenis { color: var(--muted); font-size: 10px; text-transform: capitalize; }
    .dash { color: #9ca3af; font-style: italic; }

    .empty { text-align: center; padding: 30px; color: var(--muted); }

    /* ── Signature block ───────────────────────────────────── */
    .sign {
        margin-top: 26px;
        display: flex; justify-content: flex-end;
    }
    .sign .box { width: 260px; text-align: center; font-size: 11px; }
    .sign .box .place { margin-bottom: 2px; }
    .sign .box .role  { margin-bottom: 60px; }
    .sign .box .name  { font-weight: 700; text-decoration: underline; }

    /* ── Footer note ───────────────────────────────────────── */
    .foot {
        margin-top: 18px; padding-top: 8px;
        border-top: 1px solid var(--line);
        font-size: 9.5px; color: var(--muted);
        display: flex; justify-content: space-between;
    }

    /* ── Floating toolbar (screen only) ────────────────────── */
    .toolbar {
        position: fixed; top: 16px; right: 16px; z-index: 50;
        display: flex; gap: 8px;
    }
    .toolbar button {
        font-family: inherit; font-size: 13px; font-weight: 600;
        border: none; border-radius: 8px; padding: 10px 16px;
        cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
        box-shadow: 0 2px 8px rgba(0,0,0,.18);
    }
    .toolbar .print { background: var(--primary-2); color: #fff; }
    .toolbar .print:hover { background: var(--primary); }
    .toolbar .close { background: #fff; color: #374151; border: 1px solid var(--line-2); }
    .toolbar .close:hover { background: #f3f4f6; }

    /* ── Print rules ───────────────────────────────────────── */
    @page { size: A4 landscape; margin: 10mm; }
    @media print {
        html, body { background: #fff; }
        .toolbar { display: none !important; }
        .sheet {
            width: auto; min-height: 0; margin: 0;
            padding: 0; box-shadow: none;
        }
        thead { display: table-header-group; }     /* repeat header each page */
        tr, .group-row { page-break-inside: avoid; }
        .sign { page-break-inside: avoid; }
    }
</style>
</head>
<body>

    {{-- ── Toolbar (tidak ikut tercetak) ── --}}
    <div class="toolbar">
        <button class="print" onclick="window.print()">🖨 Cetak / Simpan PDF</button>
        <button class="close" onclick="window.close()">✕ Tutup</button>
    </div>

    <div class="sheet">

        {{-- ── KOP SURAT ── --}}
        <div class="kop">
            <div class="logo">&#10084;</div>
            <div class="titles">
                <div class="gov">PEMERINTAH KABUPATEN KOTABARU</div>
                <div class="org">OPPKPKE</div>
                <div class="sub">Optimalisasi Pelaksanaan Pengentasan Kemiskinan dan Penghapusan Kemiskinan Ekstrem</div>
                <div class="addr">Sistem Informasi Pelaporan Realisasi Anggaran</div>
            </div>
            <div class="spacer"></div>
        </div>

        {{-- ── JUDUL DOKUMEN ── --}}
        <div class="doc-title">
            <h1>Rekapitulasi Data Pengguna Sistem</h1>
            <div class="line"></div>
        </div>

        {{-- ── META + FILTER ── --}}
        <div class="meta">
            <div>
                Dokumen dibuat: <b>{{ $fmtTanggalJam($generatedAt) }} WITA</b><br>
                Oleh: <b>{{ $generatedBy->name }}</b> ({{ $generatedBy->role_label }})
            </div>
            <div class="chips">
                @if(count($filters))
                    @foreach($filters as $key => $val)
                        <span class="chip">{{ $key }}: <b>{{ $val }}</b></span>
                    @endforeach
                @else
                    <span class="chip">Menampilkan <b>seluruh pengguna</b></span>
                @endif
            </div>
        </div>

        {{-- ── RINGKASAN ── --}}
        <div class="stats">
            <div class="stat total">    <div class="num">{{ $summary['total'] }}</div>    <div class="lbl">Total Akun</div></div>
            <div class="stat master">   <div class="num">{{ $summary['master'] }}</div>   <div class="lbl">Admin Master</div></div>
            <div class="stat daerah">   <div class="num">{{ $summary['daerah'] }}</div>   <div class="lbl">Operator Daerah</div></div>
            <div class="stat active">   <div class="num">{{ $summary['active'] }}</div>   <div class="lbl">Aktif</div></div>
            <div class="stat inactive"> <div class="num">{{ $summary['inactive'] }}</div> <div class="lbl">Nonaktif</div></div>
        </div>

        {{-- ── TABEL ── --}}
        <table>
            <thead>
                <tr>
                    <th class="center" style="width:36px;">No</th>
                    <th>Nama Pengguna</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Perangkat Daerah</th>
                    <th class="center" style="width:90px;">Status</th>
                    <th class="center" style="width:120px;">Login Terakhir</th>
                </tr>
            </thead>
            <tbody>
                @php $no = 0; $lastRole = null; @endphp
                @forelse($users as $user)
                    @if($user->role !== $lastRole)
                        @php $lastRole = $user->role; @endphp
                        <tr class="group-row">
                            <td colspan="7">
                                {{ $user->role === 'master' ? 'Admin Master' : 'Operator Daerah' }}
                                ({{ $users->where('role', $user->role)->count() }} akun)
                            </td>
                        </tr>
                    @endif
                    <tr>
                        <td class="num">{{ ++$no }}</td>
                        <td>
                            <div class="uname">{{ $user->name }}</div>
                        </td>
                        <td class="uemail">{{ $user->email }}</td>
                        <td>
                            <span class="badge {{ $user->role }}">{{ $user->role_label }}</span>
                        </td>
                        <td>
                            @if($user->perangkatDaerah)
                                <span class="pd-name">{{ $user->perangkatDaerah->nama }}</span>
                                <div class="pd-jenis">{{ $user->perangkatDaerah->jenis }}</div>
                            @else
                                <span class="dash">— Seluruh Daerah —</span>
                            @endif
                        </td>
                        <td class="center">
                            <span class="badge {{ $user->is_active ? 'on' : 'off' }}">
                                {{ $user->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="center">
                            @if($user->last_login_at)
                                {{ $fmtTanggal($user->last_login_at) }}<br>
                                <span class="uemail">{{ $user->last_login_at->format('H:i') }}</span>
                            @else
                                <span class="dash">Belum pernah</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="empty">Tidak ada data pengguna untuk ditampilkan.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        {{-- ── TANDA TANGAN ── --}}
        <div class="sign">
            <div class="box">
                <div class="place">Kotabaru, {{ $tglCetak }}</div>
                <div class="role">Administrator Sistem,</div>
                <div class="name">{{ $generatedBy->name }}</div>
            </div>
        </div>

        {{-- ── CATATAN KAKI ── --}}
        <div class="foot">
            <span>Dokumen ini dibuat secara otomatis oleh Sistem OPPKPKE Kabupaten Kotabaru.</span>
            <span>Total {{ $summary['total'] }} pengguna · Dicetak {{ $fmtTanggalJam($generatedAt) }}</span>
        </div>

    </div>

    <script>
        // Auto-buka dialog cetak saat halaman siap (beri jeda agar layout render penuh)
        window.addEventListener('load', function () {
            setTimeout(function () { window.print(); }, 400);
        });
    </script>
</body>
</html>
