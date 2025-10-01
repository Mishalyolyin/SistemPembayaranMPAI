<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Kwitansi #{{ $invoice->id }}</title>
  <style>
    * { box-sizing: border-box; }
    body { font-family: DejaVu Sans, Arial, sans-serif; margin: 28px; font-size: 12px; color: #111; }
    .header { text-align: center; margin-bottom: 20px; }
    .header h1 { margin: 0 0 6px 0; font-size: 18px; letter-spacing: .5px; }
    .sub { margin: 0; font-size: 12px; color: #555; }
    .meta { width: 100%; border-collapse: collapse; margin-top: 16px; }
    .meta th, .meta td { padding: 8px 10px; border: 1px solid #333; vertical-align: top; }
    .meta th { width: 30%; background: #f5f5f5; text-align: left; font-weight: 600; }
    .right { text-align: right; }
    .muted { color: #666; }
    .foot { margin-top: 28px; display: flex; justify-content: space-between; align-items: flex-end; }
    .sign { width: 220px; text-align: center; }
    .small { font-size: 11px; }
    .stamp { margin-top: 56px; }
  </style>
</head>
<body>
  @php
    // Guard: ambil tanggal bayar paling masuk akal
    $tglBayar = $invoice->verified_at
      ?? ($invoice->tanggal_bayar ?? null)
      ?? ($invoice->uploaded_at ?? null)
      ?? $invoice->updated_at
      ?? $invoice->created_at;

    // Nominal fallback: jumlah -> nominal -> 0
    $nominal = (int) ($invoice->jumlah ?? $invoice->nominal ?? 0);

    // Format helper
    $fmtRupiah = function ($v) {
      return number_format((int)$v, 0, ',', '.');
    };

    // Label semester/TA fallback dari profil
    $semLabel = $invoice->semester ?? ($mahasiswa->semester_awal ?? null);
    $taLabel  = $invoice->tahun_akademik ?? ($mahasiswa->tahun_akademik ?? null);
  @endphp

  <div class="header">
    <h1>KWITANSI PEMBAYARAN</h1>
    <p class="sub">No. Kwitansi: {{ $invoice->kode ?? ('INV-'.$invoice->id) }}</p>
  </div>

  <table class="meta">
    <tr>
      <th>Nama</th>
      <td>{{ $mahasiswa->nama ?? '—' }}</td>
    </tr>
    <tr>
      <th>NIM</th>
      <td>{{ $mahasiswa->nim ?? '—' }}</td>
    </tr>
    <tr>
      <th>Semester / TA</th>
      <td>
        {{ $semLabel ? ucfirst($semLabel) : '—' }}{{ $taLabel ? ' / '.$taLabel : '' }}
      </td>
    </tr>
    <tr>
      <th>Periode Tagihan</th>
      <td>{{ $invoice->bulan ?? '—' }}@if(!empty($invoice->angsuran_ke)) &nbsp;<span class="muted">(Angsuran ke-{{ $invoice->angsuran_ke }})</span>@endif</td>
    </tr>
    <tr>
      <th>Jumlah (Rp)</th>
      <td class="right">Rp {{ $fmtRupiah($nominal) }}</td>
    </tr>
    <tr>
      <th>Tanggal Pembayaran</th>
      <td>
        @if($tglBayar)
          {{ \Carbon\Carbon::parse($tglBayar)->translatedFormat('d F Y') }}
        @else
          —
        @endif
      </td>
    </tr>
    <tr>
      <th>Status</th>
      <td>{{ $invoice->status ?? '—' }}</td>
    </tr>
  </table>

  <p class="small muted" style="margin-top: 14px;">
    Kwitansi ini dihasilkan secara otomatis oleh sistem pembayaran. Simpan dokumen ini sebagai bukti sah pembayaran.
  </p>

  <div class="foot">
    <div class="small muted">
      Dicetak: {{ now()->translatedFormat('d F Y H:i') }}
    </div>
    <div class="sign">
      <div>Bagian Keuangan</div>
      <div class="stamp">__________________________</div>
    </div>
  </div>
</body>
</html>
