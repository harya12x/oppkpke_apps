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
<style>
    @page { margin: 90px 32px 60px 32px; }

    * { font-family: DejaVu Sans, sans-serif; }
    body { margin: 0; color: #1f2937; font-size: 10px; }

    /* ── Header (fixed, muncul tiap halaman) ── */
    .kop {
        position: fixed; top: -70px; left: 0; right: 0;
        border-bottom: 2px solid #1e3a8a; padding-bottom: 6px;
    }
    .kop table { width: 100%; border-collapse: collapse; }
    .kop .logo-cell { width: 56px; vertical-align: middle; }
    .kop .logo {
        width: 46px; height: 46px; background-color: #1e3a8a;
        border-radius: 23px; color: #fbbf24; text-align: center;
        font-size: 24px; line-height: 46px;
    }
    .kop .gov  { font-size: 11px; font-weight: bold; color: #111827; }
    .kop .org  { font-size: 16px; font-weight: bold; color: #1e3a8a; }
    .kop .sub  { font-size: 8.5px; color: #6b7280; }

    /* ── Footer (fixed) ── */
    .footer {
        position: fixed; bottom: -45px; left: 0; right: 0;
        border-top: 1px solid #e5e7eb; padding-top: 5px;
        font-size: 8px; color: #6b7280;
    }
    .footer table { width: 100%; }
    .footer .right { text-align: right; }

    /* ── Judul ── */
    .doc-title { text-align: center; margin: 4px 0 10px; }
    .doc-title h1 { font-size: 13px; font-weight: bold; text-transform: uppercase; margin: 0; letter-spacing: 1px; }
    .doc-title .rule { width: 110px; height: 2px; background-color: #d97706; margin: 5px auto 0; }

    /* ── Meta ── */
    .meta { width: 100%; border-collapse: collapse; margin-bottom: 10px; font-size: 9px; color: #6b7280; }
    .meta b { color: #111827; }
    .meta .right { text-align: right; }
    .chip {
        display: inline-block; background-color: #f3f4f6; border: 1px solid #d1d5db;
        border-radius: 8px; padding: 2px 8px; color: #374151; margin-left: 4px;
    }
    .chip b { color: #1e3a8a; }

    /* ── Ringkasan ── */
    .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 12px; }
    .stats td {
        width: 33%; text-align: center; border: 1px solid #e5e7eb;
        border-radius: 8px; padding: 8px;
    }
    .stats .num { font-size: 20px; font-weight: bold; }
    .stats .lbl { font-size: 8.5px; color: #6b7280; text-transform: uppercase; }
    .stats .total    { background-color: #f8fafc; }
    .stats .total .num    { color: #0f172a; }
    .stats .active   { background-color: #f0fdf4; border-color: #bbf7d0; }
    .stats .active .num   { color: #15803d; }
    .stats .inactive { background-color: #fef2f2; border-color: #fecaca; }
    .stats .inactive .num { color: #b91c1c; }

    /* ── Tabel utama ── */
    table.data { width: 100%; border-collapse: collapse; }
    table.data thead th {
        background-color: #1e3a8a; color: #ffffff; font-size: 9px;
        padding: 6px 7px; border: 1px solid #1e3a8a; text-align: left;
    }
    table.data thead th.center { text-align: center; }
    table.data tbody td { padding: 5px 7px; border: 1px solid #e5e7eb; vertical-align: middle; }
    table.data tbody tr:nth-child(even) td { background-color: #f9fafb; }
    td.center { text-align: center; }
    td.num { text-align: center; color: #6b7280; }

    .uname { font-weight: bold; color: #111827; }
    .uemail { color: #6b7280; font-size: 9px; }
    .pd-name { color: #1f2937; }
    .pd-jenis { color: #9ca3af; font-size: 8.5px; }
    .dash { color: #9ca3af; font-style: italic; }

    .badge { padding: 2px 7px; border-radius: 8px; font-size: 8.5px; font-weight: bold; }
    .badge.on  { background-color: #dcfce7; color: #166534; }
    .badge.off { background-color: #fee2e2; color: #991b1b; }

    /* ── Tanda tangan ── */
    .sign { width: 100%; margin-top: 22px; }
    .sign td { vertical-align: top; }
    .sign .box { width: 240px; text-align: center; font-size: 10px; }
    .sign .gap { height: 55px; }
    .sign .name { font-weight: bold; text-decoration: underline; }
</style>
</head>
<body>

    {{-- ── HEADER (kop) ── --}}
    <div class="kop">
        <table>
            <tr>
                <td class="logo-cell"><div class="logo">&#9829;</div></td>
                <td align="center">
                    <div class="gov">PEMERINTAH KABUPATEN KOTABARU</div>
                    <div class="org">OPPKPKE</div>
                    <div class="sub">Optimalisasi Pelaksanaan Pengentasan Kemiskinan dan Penghapusan Kemiskinan Ekstrem</div>
                </td>
                <td class="logo-cell"></td>
            </tr>
        </table>
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <table>
            <tr>
                <td>Dokumen otomatis Sistem OPPKPKE Kabupaten Kotabaru.</td>
                <td class="right">Total {{ $summary['total'] }} operator · Dicetak {{ $fmtTanggalJam($generatedAt) }}</td>
            </tr>
        </table>
    </div>

    {{-- ── JUDUL ── --}}
    <div class="doc-title">
        <h1>Rekapitulasi Data Operator Daerah</h1>
        <div class="rule"></div>
    </div>

    {{-- ── META + FILTER ── --}}
    <table class="meta">
        <tr>
            <td>
                Dokumen dibuat: <b>{{ $fmtTanggalJam($generatedAt) }} WITA</b><br>
                Oleh: <b>{{ $generatedBy->name }}</b> ({{ $generatedBy->role_label }})
            </td>
            <td class="right">
                @if(count($filters))
                    @foreach($filters as $key => $val)
                        <span class="chip">{{ $key }}: <b>{{ $val }}</b></span>
                    @endforeach
                @else
                    <span class="chip">Menampilkan <b>seluruh operator daerah</b></span>
                @endif
            </td>
        </tr>
    </table>

    {{-- ── RINGKASAN ── --}}
    <table class="stats">
        <tr>
            <td class="total"><div class="num">{{ $summary['total'] }}</div><div class="lbl">Total Operator</div></td>
            <td class="active"><div class="num">{{ $summary['active'] }}</div><div class="lbl">Aktif</div></td>
            <td class="inactive"><div class="num">{{ $summary['inactive'] }}</div><div class="lbl">Nonaktif</div></td>
        </tr>
    </table>

    {{-- ── TABEL DATA ── --}}
    <table class="data">
        <thead>
            <tr>
                <th class="center" width="28">No</th>
                <th>Nama Operator</th>
                <th>Email</th>
                <th>Perangkat Daerah</th>
                <th class="center" width="70">Status</th>
                <th class="center" width="95">Login Terakhir</th>
            </tr>
        </thead>
        <tbody>
            @php $no = 0; @endphp
            @forelse($users as $user)
                <tr>
                    <td class="num">{{ ++$no }}</td>
                    <td><span class="uname">{{ $user->name }}</span></td>
                    <td class="uemail">{{ $user->email }}</td>
                    <td>
                        @if($user->perangkatDaerah)
                            <span class="pd-name">{{ $user->perangkatDaerah->nama }}</span>
                            <div class="pd-jenis">{{ $user->perangkatDaerah->jenis }}</div>
                        @else
                            <span class="dash">—</span>
                        @endif
                    </td>
                    <td class="center">
                        <span class="badge {{ $user->is_active ? 'on' : 'off' }}">{{ $user->is_active ? 'Aktif' : 'Nonaktif' }}</span>
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
                <tr><td colspan="6" style="text-align:center; padding:25px; color:#6b7280;">Tidak ada data operator untuk ditampilkan.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- ── TANDA TANGAN ── --}}
    <table class="sign">
        <tr>
            <td></td>
            <td align="right">
                <div class="box">
                    <div>Kotabaru, {{ $tglCetak }}</div>
                    <div>Administrator Sistem,</div>
                    <div class="gap"></div>
                    <div class="name">{{ $generatedBy->name }}</div>
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
