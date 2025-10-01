{{-- resources/views/pdf/kwitansi-bulk-table.blade.php --}}
@php
  use Carbon\Carbon;

  // ====== Data dasar ======
  $nama      = $mahasiswa->nama ?? 'Mahasiswa';
  $nim       = $mahasiswa->nim  ?? '-';
  $angkatan  = $angkatan ?? ($mahasiswa->angkatan ?? ($mahasiswa->tahun_masuk ?? '-'));
  $no_hp     = $mahasiswa->no_hp ?? $mahasiswa->telepon ?? $mahasiswa->phone ?? '-';
  $printedAt = Carbon::now()->format('d-m-Y H:i');

  // ====== Helper: data-uri base64 dari file ======
  $toDataUri = function (?string $absPath) {
      if (!$absPath || !is_file($absPath)) return null;
      $f = @finfo_open(FILEINFO_MIME_TYPE);
      $mime = $f ? @finfo_file($f, $absPath) : 'image/png';
      $bin  = @file_get_contents($absPath);
      return $bin === false ? null : ('data:' . $mime . ';base64,' . base64_encode($bin));
  };

  // ====== Ambil logo dari public/storage/kwitansi ======
  $basePublicStorage = public_path('storage/kwitansi');
  $logoLeftPath  = $basePublicStorage . DIRECTORY_SEPARATOR . 'logo-unnisula.jpg'; // sesuaikan nama file
  $logoRightPath = $basePublicStorage . DIRECTORY_SEPARATOR . 'logo-right.png';   // opsional

  $logoLeft  = $toDataUri($logoLeftPath);
  $logoRight = $toDataUri($logoRightPath);

  $total = 0;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kwitansi – {{ $nama }} ({{ $nim }})</title>
  <style>
    @page { margin: 20px 26px 30px 26px; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color:#111; }

    /* ====== KONTEN DIPERSEMPIT ====== */
    .page { width: 82%; margin: 0 auto; }
    .full { width: 100%; }

    /* ==== KOP TENGAH & LOGO MELEKAT ==== */
    .kop-outer { width:100%; border-collapse:collapse; }
    .kop-outer td { padding:0; text-align:center; }
    .kop-inner { border-collapse:collapse; margin:0 auto; }
    .kop-inner td { padding:0; vertical-align:middle; }
    .kop-logo { width:72px; }
    .kop-logo img { width:72px; height:auto; display:block; }

    .kop-text { text-align:center; line-height:1.25; padding-left:8px; }
    .kop-text .l1 { font-size:14px; font-weight:700; }
    .kop-text .l2 { font-size:14px; font-weight:700; }
    .kop-text .l3 { font-size:13px; font-weight:700; }
    .kop-text .l4 { font-size:12px; }

    .kop-line { border-top:2px solid #000; border-bottom:1px solid #000; height:4px; margin-top:6px; }

    .title { text-align:center; margin: 8px 0 10px; font-weight:700; font-size:16px; white-space:pre-line; }
    .meta  { text-align:center; font-size:11px; color:#444; margin-top:-4px; }

    .header-table { width:100%; border-collapse:collapse; margin-top:8px; }
    .header-table th, .header-table td { border:1px solid #333; padding:6px 8px; }
    .header-table th { width:120px; background:#f7f7f7; text-align:left; }

    .grid { width:100%; border-collapse:collapse; margin-top:6px; table-layout:fixed; }
    .grid th, .grid td { border:1px solid #333; padding:6px 8px; }
    .grid th { background:#f0f3f7; }
    .right  { text-align:right; }
    .center { text-align:center; }
    .muted  { color:#555; font-size:11px; }
    .total-row td { background:#eaf3ff; font-weight:700; }

    /* kolom sangat kecil untuk "No" */
    .col-no { width: 28px; }         /* kecil banget */
    .pad-no th, .pad-no td { padding-left:4px; padding-right:4px; } /* biar angka 'No' tetap muat */
  </style>
</head>
<body>

  <div class="page">
    {{-- ===== KOP SURAT ===== --}}
    <table class="kop-outer full">
      <tr>
        <td>
          <table class="kop-inner">
            <tr>
              @if($logoLeft)
                <td class="kop-logo">
                  <img src="{{ $logoLeft }}" alt="Logo UNISSULA">
                </td>
              @endif
              <td class="kop-text">
                <div class="l1">YAYASAN BADAN WAKAF SULTAN AGUNG</div>
                <div class="l2">UNIVERSITAS ISLAM SULTAN AGUNG (UNISSULA)</div>
                <div class="l3">PROGRAM PASCASARJANA MAGISTER PENDIDIKAN AGAMA ISLAM</div>
                <div class="l4">Jl. Raya Kaligawe Km. 4 PO. BOX. 1054 Telp. (024) 6583584 Semarang 50012</div>
              </td>
              @if($logoRight)
                <td class="kop-logo">
                  <img src="{{ $logoRight }}" alt="Logo Kanan">
                </td>
              @endif
            </tr>
          </table>
        </td>
      </tr>
    </table>
    <div class="kop-line"></div>

    {{-- ===== JUDUL ===== --}}
    <div class="title">BUKTI PEMBAYARAN
PROGRAM PASCASARJANA
MAGISTER PENDIDIKAN AGAMA ISLAM</div>
    <div class="meta">Dicetak pada {{ $printedAt }}</div>

    {{-- ===== IDENTITAS ===== --}}
    <table class="header-table">
      <tr>
        <th>Nama</th><td>{{ $nama }}</td>
        <th>NIM</th><td>{{ $nim }}</td>
      </tr>
      <tr>
        <th>Angkatan</th><td>{{ $angkatan }}</td>
        <th>No. HP</th><td>{{ $no_hp }}</td>
      </tr>
    </table>

    {{-- ===== TABEL RINGKASAN LUNAS (kolom No super kecil) ===== --}}
    <table class="grid pad-no">
      {{-- Atur lebar kolom di sini --}}
      <colgroup>
        <col class="col-no">              {{-- No (kecil) --}}
        <col style="width: 42%;">         {{-- Bulan (lebar) --}}
        <col style="width: 14%;">         {{-- Status --}}
        <col style="width: 18%;">         {{-- Tanggal Bayar --}}
        <col style="width: 18%;">         {{-- Jumlah --}}
      </colgroup>
      <thead>
        <tr>
          <th class="center">No</th>
          <th>Bulan</th>
          <th class="center">Status</th>
          <th class="center">Tanggal Bayar</th>
          <th class="right">Jumlah (Rp)</th>
        </tr>
      </thead>
      <tbody>
        @foreach($invoices as $i => $inv)
          @php
            $jumlah   = (int)($inv->jumlah ?? $inv->nominal ?? 0);
            $total   += $jumlah;
            $tglBayar = $inv->tanggal_bayar ?? $inv->verified_at ?? $inv->updated_at ?? $inv->uploaded_at ?? now();
            try { $tglFmt = Carbon::parse($tglBayar)->format('d-m-Y'); } catch (\Throwable $e) { $tglFmt = date('d-m-Y'); }
          @endphp
          <tr>
            <td class="center">{{ $i+1 }}</td>
            <td>{{ $inv->bulan }}</td>
            <td class="center">Lunas</td>
            <td class="center">{{ $tglFmt }}</td>
            <td class="right">{{ number_format($jumlah,0,',','.') }}</td>
          </tr>
        @endforeach
        <tr class="total-row">
          <td colspan="4" class="right">TOTAL</td>
          <td class="right">{{ number_format($total,0,',','.') }}</td>
        </tr>
      </tbody>
    </table>

    <p class="muted" style="margin-top:8px;">
      Dokumen ini berisi ringkasan pembayaran yang telah berstatus <strong>LUNAS/Terverifikasi</strong>.
      Jika ada perbedaan data, mohon hubungi admin keuangan kampus.
    </p>
  </div>

</body>
</html>
