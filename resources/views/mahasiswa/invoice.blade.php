{{-- resources/views/mahasiswa/invoice.blade.php --}}
@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  // Fallback user (aman)
  $mhs = ($mahasiswa ?? auth('mahasiswa')->user() ?? auth()->user());

  // Kandidat route untuk KWITANSI BULK (opsional)
  $kwitansiBulkRoute = null;
  foreach ([
    'mahasiswa.invoices.kwitansi.bulk',
    'mahasiswa.invoice.kwitansi.bulk',
    'mahasiswa.kwitansi.bulk',
  ] as $cand) {
    if (Route::has($cand)) { $kwitansiBulkRoute = $cand; break; }
  }

  // Ringkasan
  $rows         = ($invoices ?? collect());
  $totalTagihan = $totalTagihan ?? 0;
  $totalPaid    = $totalPaid    ?? 0;
  $remaining    = $remaining    ?? max(0, $totalTagihan - $totalPaid);

  $paidCount = $rows->filter(function($inv){
    $s = strtolower((string)($inv->status ?? ''));
    return in_array($s, ['lunas','lunas (otomatis)','terverifikasi'], true);
  })->count();
@endphp

@extends('layouts.mahasiswa')
@section('title', 'Invoice Mahasiswa')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Daftar Tagihan SKS</h4>
    <a href="{{ route('mahasiswa.dashboard') }}" class="btn btn-outline-secondary">Kembali</a>
  </div>
  <small class="text-muted d-block mb-3">Semua invoice Anda ditampilkan di sini.</small>

  @if (session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @elseif (session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  @if (empty($mhs?->angsuran))
    <div class="alert alert-warning">
      Anda belum memilih skema angsuran. Silakan tentukan terlebih dahulu.
      <a href="{{ route('mahasiswa.angsuran.form') }}" class="alert-link">Pilih Angsuran</a>
    </div>
  @endif

  {{-- ====== WELCOME BANNER ====== --}}
  <style>
    .collapse:not(.show){display:none}
    .collapse.show{display:block}

    .welcome-card{
      position:relative; overflow:hidden; border:0; border-radius:16px;
      background: radial-gradient(1200px 200px at 20% -100%, #c7f9ff 0%, transparent 60%),
                  linear-gradient(135deg, #eef6ff 0%, #ffffff 60%);
      box-shadow: 0 6px 24px rgba(2,132,199,.08);
    }
    .welcome-card .wave{
      position:absolute; right:-10%; top:-40px; width:480px; opacity:.25; transform:rotate(8deg);
      z-index:0; pointer-events:none;
    }
    .welcome-card .card-body{ position:relative; z-index:1; }
    .welcome-card .avatar{
      width:54px; height:54px; border-radius:12px; display:grid; place-items:center;
      font-weight:800; color:#075985; background:linear-gradient(135deg,#d1f3ff,#e9f8ff);
      border:1px solid #cde9f7;
    }
    .welcome-card .kpi{ min-width:120px; padding:8px 12px; border-radius:12px; background:#fff; border:1px solid #e9eef7; }
    .welcome-card .kpi .lbl{ font-size:.8rem; color:#64748b }
    .welcome-card .kpi .val{ font-weight:800; }
    .tgl-btn .chev{transition:transform .18s ease}
    .tgl-btn[aria-expanded="true"] .chev{transform:rotate(90deg)}

    /* Mobile polish untuk kolom aksi */
    @media (max-width:576px){
      .invoice-table td.text-nowrap{white-space:normal!important}
      .invoice-table .btn{margin-bottom:6px}
    }

    /* Mini badge VA */
    .badge-outline {
      background: #fff; border:1px solid #e5e7eb; color:#374151; padding:.25rem .5rem; border-radius:999px;
      font-size:.72rem; font-weight:600;
    }
    .badge-outline.success { border-color:#86efac; color:#166534; background:#ecfdf5; }
    .badge-outline.warn    { border-color:#fde68a; color:#92400e; background:#fffbeb; }
    .mono { font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace; }
  </style>

  <div class="card welcome-card my-3">
    <svg class="wave" viewBox="0 0 600 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M0,120 C120,60 240,180 360,120 C480,60 520,140 600,100 L600,0 L0,0 Z" fill="#60a5fa"/>
    </svg>
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="avatar me-1">
        {{ function_exists('mb_substr') ? mb_strtoupper(mb_substr($mhs?->nama ?? 'M',0,1)) : strtoupper(substr($mhs?->nama ?? 'M',0,1)) }}
      </div>
      <div class="me-auto">
        <div class="fw-bold fs-5 mb-1">
          Halo, {{ $mhs?->nama ?? 'Mahasiswa' }}! 👋
        </div>
        <div class="text-muted">Kelola tagihanmu di sini. Butuh panduan cepat? Klik tombol di kanan →</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <div class="kpi text-center">
          <div class="lbl">Lunas</div>
          <div class="val text-success">{{ $paidCount }}/{{ $rows->count() }}</div>
        </div>
        <div class="kpi text-center">
          <div class="lbl">Sisa</div>
          <div class="val text-danger">Rp {{ number_format((int)$remaining,0,',','.') }}</div>
        </div>
        <button
          type="button"
          class="btn btn-primary tgl-btn align-self-center"
          aria-expanded="false"
          aria-controls="howtoPanel"
          id="howtoToggle"
          data-bs-toggle="collapse"
          data-bs-target="#howtoPanel">
          <span class="chev">▶</span> Lihat Tutorial
        </button>
      </div>
    </div>
  </div>

  {{-- ====== TUTORIAL (Collapse) ====== --}}
  <style>
    .howto-wrap{background:linear-gradient(135deg,#f8fafc 0%, #eef6ff 100%);border:1px solid #e6eefc;border-radius:16px;padding:18px 18px 10px;position:relative;overflow:hidden}
    .howto-badge{position:absolute;right:-8px;top:-8px;rotate:-8deg;background:#0ea5e9;color:#fff;font-weight:600;padding:6px 14px;border-radius:10px;box-shadow:0 6px 18px rgba(14,165,233,.35)}
    .howto-title{font-weight:700;margin:0 0 6px;letter-spacing:.2px}
    .howto-sub{color:#64748b;margin:0 0 12px}
    .howto-steps{display:grid;gap:10px;margin:12px 0 6px;padding:0;list-style:none}
    .howto-step{display:flex;gap:10px;align-items:flex-start;background:#fff;border:1px solid #e7eef9;border-radius:12px;padding:10px 12px}
    .howto-num{width:28px;height:28px;flex:0 0 28px;border-radius:999px;display:grid;place-items:center;font-weight:700;font-size:13px;color:#0f172a;background:#e2ecff;border:1px solid #cfe0ff}
    .howto-step h6{margin:0;font-weight:700}
    .howto-step p{margin:2px 0 0;color:#475569}
    .bank-card{background:#0f172a;color:#e2e8f0;border-radius:14px;padding:12px;display:flex;flex-wrap:wrap;align-items:center;gap:10px;justify-content:space-between}
    .bank-card .mono{font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono",monospace}
    .tips{font-size:.92rem;color:#475569;margin-top:8px}
    .chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.85rem;background:#e2fbe8;color:#166534;font-weight:600}
    .danger-chip{background:#fee2e2;color:#991b1b}
    .muted{color:#64748b}
  </style>

  <div class="collapse" id="howtoPanel">
    <div class="howto-wrap mb-3">
      <span class="howto-badge">Selamat datang</span>
      <h5 class="howto-title">Yuk beresin tagihan dengan cepat ✨</h5>
      <p class="howto-sub">Transfer → upload bukti → tunggu verifikasi. Selesai!</p>

      <div class="bank-card mb-2">
        <div>
          <div class="fw-semibold">Rekening Kampus (Resmi) — BSI</div>
          <div class="small muted">Bank Syariah Indonesia</div>
        </div>
        <div class="text-end">
          <div class="mono fw-bold fs-6 copyable" data-copy="RPL MAGISTER PAI" title="Klik untuk salin a.n.">
            a.n. RPL MAGISTER PAI <span class="copy-hint">Salin</span>
          </div>
          <div class="mono fw-bold fs-5 copyable mt-1" data-copy="9800700989" title="Klik untuk salin nomor rekening">
            No. Rek. <span class="acct">9800700989</span> <span class="copy-hint">Salin</span>
          </div>
        </div>
      </div>

      <ul class="howto-steps">
        <li class="howto-step"><span class="howto-num">1</span><div><h6>Cek invoice & catat nominal</h6><p>Pastikan bulan/TA yang mau dibayar dan nominalnya <b>persis sama</b> seperti di tabel.</p></div></li>
        <li class="howto-step"><span class="howto-num">2</span><div><h6>Transfer ke rekening kampus</h6><p>Tulis <b>berita transfer (WAJIB)</b> dengan format: <span class="mono chip">NIM - NAMA - BULAN/TA</span><span class="muted d-block">Contoh: 23123456 - AMANAH - SEPT 2025 / 2024-2025</span></p></div></li>
        <li class="howto-step"><span class="howto-num">3</span><div><h6>Simpan bukti yang jelas</h6><p>Harus terlihat: <b>tanggal</b>, <b>nominal</b>, <b>rekening tujuan BSI</b>, dan <b>berita transfer</b>.<br>Tips nama foto: <span class="mono">NIM_BULAN_TA.jpg</span> → <span class="mono">23123456_SEPT_2425.jpg</span></p></div></li>
        <li class="howto-step"><span class="howto-num">4</span><div><h6>Upload di baris invoice yang sesuai</h6><p>Klik <b>Detail</b> pada invoice → tombol <b>Upload</b> → pilih file → <b>Kirim</b>. Satu bukti untuk satu bulan ya.</p></div></li>
        <li class="howto-step"><span class="howto-num">5</span><div><h6>Tunggu verifikasi</h6><p>Status berubah menjadi <span class="chip">Menunggu Verifikasi</span>. Jika cocok → <span class="chip">LUNAS</span>. Kalau ada kendala, status <span class="chip danger-chip">DITOLAK</span> beserta alasannya—silakan perbaiki & upload ulang.</p></div></li>
        <li class="howto-step"><span class="howto-num">6</span><div><h6>Perlu koreksi?</h6><p>Pakai tombol <b>Reset</b> di baris invoice untuk hapus bukti & ulangi upload.</p></div></li>
      </ul>

      <div class="tips"><b>Tips cepat:</b> Bayar dengan nominal yang sama persis. Kalau bayar beberapa bulan sekaligus, sebutkan semuanya di berita transfer. Waspada penipuan—selalu gunakan rekening resmi di atas.</div>

      <div class="mt-2 p-2 rounded border bg-white">
        <div class="fw-semibold mb-1">Butuh konfirmasi/ada kendala? 📲</div>
        <div>Hubungi admin <b>Shicha Alfiya</b> di
          <a href="https://wa.me/6288221372120" target="_blank" rel="noopener">088221372120</a> (WhatsApp).
        </div>
        <div class="small muted">Sertakan NIM, Nama, Bulan/TA, Nominal, dan lampirkan bukti transfer ya.</div>
      </div>
    </div>
  </div>

  {{-- ====== TABEL INVOICE ====== --}}
  <div class="card border-success shadow-sm mt-3">
    <div class="card-header bg-success text-white"><strong>Invoice Saya</strong></div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered table-hover mb-0 invoice-table">
          <thead>
            <tr>
              <th style="width:60px">No</th>
              <th>Bulan</th>
              <th style="width:160px">Jumlah</th>
              <th style="width:180px">Status</th>
              <th style="width:120px">Bukti</th>
              <th style="width:260px">Aksi</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $i => $inv)
              @php
                $catatan = $inv->alasan_tolak
                          ?? $inv->catatan_penolakan
                          ?? $inv->alasan_penolakan
                          ?? $inv->catatan_admin
                          ?? $inv->catatan
                          ?? $inv->alasan
                          ?? $inv->keterangan
                          ?? $inv->catatan_tolak
                          ?? $inv->keterangan_tolak
                          ?? null;

                $buktiFile = $inv->bukti ?? $inv->bukti_pembayaran ?? null;
                $buktiUrl  = $buktiFile
                  ? (Str::contains($buktiFile, '/')
                        ? asset('storage/'.ltrim($buktiFile,'/'))
                        : asset('storage/bukti/'.ltrim($buktiFile,'/')))
                  : null;

                $statusRow = strtolower((string)($inv->status ?? ''));
                $statusMhs = strtolower((string)($mhs?->status ?? ''));
                $isPaid    = in_array($statusRow, ['lunas','lunas (otomatis)','terverifikasi'], true);
                $isLocked  = $isPaid || $statusMhs === 'lulus';

                $iid = $inv->id ?? $inv->invoice_id ?? $inv->kode ?? null;

                // Route detail
                $detailRoute = null;
                foreach ([
                  'mahasiswa.invoices.show',
                  'mahasiswa.invoices.detail',
                  'mahasiswa.invoice.detail',
                  'invoices.show','invoices.detail',
                ] as $r) { if (Route::has($r)) { $detailRoute = $r; break; } }

                $detailUrl = null;
                if ($detailRoute && $iid) {
                  try { $detailUrl = route($detailRoute, ['invoice' => $iid]); } catch (\Throwable $e) {}
                  if (!$detailUrl) { try { $detailUrl = route($detailRoute, ['id' => $iid]); } catch (\Throwable $e) {} }
                  if (!$detailUrl) { try { $detailUrl = route($detailRoute, [$iid]); } catch (\Throwable $e) {} }
                }
                if (!$detailUrl && $iid) { $detailUrl = url('/mahasiswa/invoices/'.$iid); }

                // Route kwitansi (GET form/preview)
                $kwitansiRoute = null;
                foreach ([
                  'mahasiswa.invoices.kwitansi.form',
                  'mahasiswa.invoices.kwitansi',
                  'mahasiswa.invoice.kwitansi.form',
                  'mahasiswa.invoice.kwitansi',
                ] as $r) { if (Route::has($r)) { $kwitansiRoute = $r; break; } }

                $kwitansiUrl = null;
                if ($kwitansiRoute && $iid) {
                  try { $kwitansiUrl = route($kwitansiRoute, ['invoice' => $iid]); } catch (\Throwable $e) {}
                  if (!$kwitansiUrl) { try { $kwitansiUrl = route($kwitansiRoute, ['id' => $iid]); } catch (\Throwable $e) {} }
                  if (!$kwitansiUrl) { try { $kwitansiUrl = route($kwitansiRoute, [$iid]); } catch (\Throwable $e) {} }
                }

                $resetRouteName = null;
                foreach (['mahasiswa.invoices.reset','mahasiswa.invoice.reset'] as $r) {
                  if (Route::has($r)) { $resetRouteName = $r; break; }
                }
                $resetAction = null;
                if ($resetRouteName && $iid) {
                  try { $resetAction = route($resetRouteName, ['invoice' => $iid]); } catch (\Throwable $e) {}
                  if (!$resetAction) { try { $resetAction = route($resetRouteName, ['id' => $iid]); } catch (\Throwable $e) {} }
                  if (!$resetAction) { try { $resetAction = route($resetRouteName, [$iid]); } catch (\Throwable $e) {} }
                }
                if (!$resetAction && $iid) { $resetAction = url('/mahasiswa/invoices/'.$iid.'/reset'); }

                $nominal = (int)($inv->jumlah ?? $inv->nominal ?? 0);
              @endphp

              <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $inv->bulan ?? '-' }}</td>
                <td class="text-end" style="white-space:nowrap;">Rp {{ number_format($nominal, 0, ',', '.') }}</td>
                <td>
                  {{-- Status pembayaran lama (dipertahankan) --}}
                  @switch($inv->status)
                    @case('Lunas') @case('Lunas (Otomatis)') @case('Terverifikasi')
                      <span class="badge bg-success">{{ $inv->status }}</span> @break
                    @case('Ditolak') @case('Reject')
                      <span class="badge bg-danger" @if($catatan) title="{{ $catatan }}" @endif>Ditolak</span>
                      @if($catatan)
                        <div class="small text-danger mt-1"><strong>Catatan:</strong> {{ Str::limit($catatan, 140) }}</div>
                      @endif
                      @break
                    @case('Menunggu Verifikasi') @case('Pending') @case('Menunggu')
                      <span class="badge bg-warning text-dark">{{ $inv->status }}</span> @break
                    @default
                      <span class="badge bg-secondary">{{ $inv->status ?? 'Belum Upload' }}</span>
                  @endswitch>

                  {{-- Indikator VA (Plan A): hanya info, tidak ada input --}}
                  <div class="mt-2">
                    @if(!empty($inv->va_full))
                      <span class="badge-outline success">VA Assigned</span>
                    @else
                      <span class="badge-outline warn">Menunggu VA</span>
                      @if(!empty($inv->va_cust_code))
                        <div class="small text-muted mt-1">
                          CustCode: <span class="mono">{{ $inv->va_cust_code }}</span>
                        </div>
                      @endif
                    @endif
                  </div>
                </td>
                <td class="text-center">
                  @if($buktiUrl)
                    <a href="{{ $buktiUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Lihat</a>
                  @else
                    —
                  @endif
                </td>

                {{-- ====== AKSI (NO DOWNLOAD) ====== --}}
                <td class="text-nowrap">
                  <a href="{{ $detailUrl ?? '#' }}" class="btn btn-sm btn-success"
                     @if(!$detailUrl) onclick="return false" @endif>Detail</a>

                  @if($kwitansiUrl)
                    <a href="{{ $kwitansiUrl }}"
                       class="btn btn-sm btn-outline-success ms-1 @if(!$isPaid) disabled @endif"
                       @if(!$isPaid) aria-disabled="true" tabindex="-1" @endif>
                      Kwitansi
                    </a>
                  @else
                    <button class="btn btn-sm btn-outline-secondary ms-1" disabled>Kwitansi</button>
                  @endif

                  @if($buktiUrl && !$isLocked && $resetAction)
                    <form action="{{ $resetAction }}" method="POST" class="d-inline ms-1"
                          onsubmit="return confirm('Hapus bukti & kembalikan status ke Belum?')">
                      @csrf
                      <button type="submit" class="btn btn-sm btn-outline-danger">Reset</button>
                    </form>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-center text-muted py-4">Belum ada invoice.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- FOOTER: Kwitansi semua lunas --}}
    @if($kwitansiBulkRoute && $paidCount > 0)
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">Cetak semua tagihan yang sudah <b>Lunas/Terverifikasi</b> dalam satu PDF.</div>
        <a href="{{ route($kwitansiBulkRoute) }}" class="btn btn-success">Kwitansi Semua Lunas</a>
      </div>
    @endif
  </div>
@endsection

@push('scripts')
<script>
  (function(){
    const btn  = document.getElementById('howtoToggle');
    const pane = document.getElementById('howtoPanel');
    if (!btn || !pane) return;

    function hasBootstrapCollapse(){
      try{ return !!window.bootstrap && typeof window.bootstrap.Collapse !== 'undefined'; }
      catch(e){ return false; }
    }
    btn.addEventListener('click', function(e){
      if (!hasBootstrapCollapse()){
        e.preventDefault();
        pane.classList.toggle('show');
        btn.setAttribute('aria-expanded', pane.classList.contains('show') ? 'true' : 'false');
      }
    });

    // Copy to clipboard (bank info)
    function showCopied(el){
      try{
        el.querySelectorAll('.copied-badge').forEach(n=>n.remove());
        const b = document.createElement('span');
        b.className = 'copied-badge';
        b.textContent = 'Tersalin';
        b.style.position='absolute'; b.style.marginLeft='6px';
        el.appendChild(b);
        setTimeout(()=>b.remove(), 1400);
      }catch(e){}
    }
    document.querySelectorAll('.copyable').forEach(function(el){
      el.style.cursor='pointer';
      el.addEventListener('click', function(){
        const text = el.getAttribute('data-copy') || (el.textContent || '').trim();
        if (!navigator.clipboard){
          try{
            const ta = document.createElement('textarea');
            ta.value = text; ta.style.position='fixed'; ta.style.top='-9999px';
            document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
            showCopied(el);
          }catch(e){}
        }else{
          navigator.clipboard.writeText(text).then(()=>showCopied(el)).catch(()=>{});
        }
      }, false);
    });
  })();
</script>
@endpush
