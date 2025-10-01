{{-- resources/views/mahasiswa_reguler/invoice.blade.php --}}
@extends('layouts.mahasiswa_reguler')
@section('title', 'Invoice Mahasiswa Reguler')

@php
  use Illuminate\Support\Str;
  use Illuminate\Support\Facades\Route;

  // Ambil mahasiswa reguler (fallback aman)
  $mhs = ($mahasiswaReguler ?? $mahasiswa ?? auth('mahasiswa_reguler')->user() ?? auth()->user());

  // Kumpulan baris invoice reguler (fleksibel)
  $rows = ($invoices_reguler ?? $invoices ?? collect());

  // Hitung KPI (aman jika field jumlah/nominal tidak ada)
  $getNominal = function($inv){
    return (int)($inv->jumlah ?? $inv->nominal ?? 0);
  };
  $totalTagihan = $totalTagihan ?? (is_iterable($rows) ? $rows->sum(fn($inv) => $getNominal($inv)) : 0);
  $paidRows = is_iterable($rows)
      ? $rows->filter(function($inv){
          $s = strtolower((string)($inv->status ?? ''));
          return in_array($s, ['lunas','lunas (otomatis)','terverifikasi'], true);
        })
      : collect();
  $totalPaid = $totalPaid ?? $paidRows->sum(fn($inv) => $getNominal($inv));
  $remaining = $remaining ?? max(0, $totalTagihan - $totalPaid);
  $paidCount = $paidRows->count();

  // ===== Detect route Detail =====
  $detailRoute = null;
  foreach ([
    'mahasiswa_reguler.invoices.show',
    'mahasiswa_reguler.invoice.detail',
    'reguler.invoices.show',
  ] as $cand) {
    if (Route::has($cand)) { $detailRoute = $cand; break; }
  }

  // ===== Detect route Kwitansi PREVIEW (GET) =====
  $kwitansiPreviewRoute = null;
  foreach ([
    'reguler.invoice.kwitansi.direct',
    'mahasiswa_reguler.invoice.kwitansi.direct',
    'reguler.invoices.kwitansi',
  ] as $cand) {
    if (Route::has($cand)) { $kwitansiPreviewRoute = $cand; break; }
  }

  // ===== Detect route Kwitansi BULK =====
  $kwitansiBulkRoute = null;
  foreach ([
    'reguler.invoices.kwitansi.bulk',
    'mahasiswa_reguler.invoice.kwitansi.bulk',
    'mahasiswa_reguler.invoices.kwitansi.bulk',
  ] as $cand) {
    if (Route::has($cand)) { $kwitansiBulkRoute = $cand; break; }
  }

  // Helper aman ambil inisial
  $namaMhs = $mhs?->nama ?? 'Mahasiswa';
  $inisial = function_exists('mb_substr') ? mb_strtoupper(mb_substr($namaMhs,0,1)) : strtoupper(substr($namaMhs,0,1));
@endphp

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Daftar Tagihan SKS</h4>
    <a href="{{ route('mahasiswa_reguler.dashboard') }}" class="btn btn-outline-secondary">Kembali</a>
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
      @php
        $angsForm = null;
        foreach ([
          'mahasiswa_reguler.angsuran.form',
          'reguler.angsuran.create',
          'reguler.angsuran.form',
        ] as $cand) { if (Route::has($cand)) { $angsForm = $cand; break; } }
      @endphp
      @if($angsForm)
        <a href="{{ route($angsForm) }}" class="alert-link">Pilih Angsuran</a>
      @endif
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
      box-shadow: 0 6px 24px rgba(2, 132, 199, .08);
    }
    .welcome-card .wave{position:absolute; right:-10%; top:-40px; width:480px; opacity:.25; transform:rotate(8deg); z-index:0; pointer-events:none}
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

    /* Mobile polish */
    @media (max-width:576px){
      .invoice-table td.text-nowrap{white-space:normal!important}
      .invoice-table .btn{margin-bottom:6px}
    }
  </style>

  <div class="card welcome-card my-3">
    <svg class="wave" viewBox="0 0 600 200" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
      <path d="M0,120 C120,60 240,180 360,120 C480,60 520,140 600,100 L600,0 L0,0 Z" fill="#60a5fa"/>
    </svg>
    <div class="card-body d-flex flex-wrap align-items-center gap-3">
      <div class="avatar me-1">{{ $inisial }}</div>
      <div class="me-auto">
        <div class="fw-bold fs-5 mb-1">Halo, {{ $mhs?->nama ?? 'Mahasiswa' }}! 👋</div>
        <div class="text-muted">Kelola tagihanmu di sini. Butuh panduan cepat? Klik tombol di kanan →</div>
      </div>
      <div class="d-flex flex-wrap gap-2">
        <div class="kpi text-center">
          <div class="lbl">Lunas</div>
          <div class="val text-success">{{ $paidCount }}/{{ is_iterable($rows) ? $rows->count() : 0 }}</div>
        </div>
        <div class="kpi text-center">
          <div class="lbl">Sisa</div>
          <div class="val text-danger">Rp {{ number_format((int)$remaining,0,',','.') }}</div>
        </div>

        {{-- toggle collapse (jalan meski tanpa JS bootstrap pakai fallback di bawah) --}}
        <button
          type="button"
          class="btn btn-primary tgl-btn align-self-center"
          aria-expanded="false"
          aria-controls="howtoPanelReg"
          id="howtoToggleReg"
          data-bs-toggle="collapse"
          data-bs-target="#howtoPanelReg">
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
    .bank-card .mono{font-family:ui-monospace,Menlo,Consolas,monospace}
    .tips{font-size:.92rem;color:#475569;margin-top:8px}
    .chip{display:inline-block;padding:4px 10px;border-radius:999px;font-size:.85rem;background:#e2fbe8;color:#166534;font-weight:600}
    .danger-chip{background:#fee2e2;color:#991b1b}
    .muted{color:#64748b}
    .copyable{cursor:pointer;position:relative;display:inline-block}
    .copy-hint{margin-left:8px;font-size:.75rem;background:#1e293b;color:#fff;padding:2px 6px;border-radius:999px;opacity:0;transform:translateY(-3px);transition:all .15s ease;vertical-align:middle}
    .copyable:hover .copy-hint{opacity:1;transform:translateY(0)}
    .copied-badge{position:absolute;right:-6px;top:-8px;background:#16a34a;color:#fff;font-size:.75rem;font-weight:700;padding:2px 8px;border-radius:999px;box-shadow:0 6px 16px rgba(22,163,74,.35);animation:pop .15s ease}
  </style>

  <div class="collapse" id="howtoPanelReg">
    <div class="howto-wrap mb-3">
      <span class="howto-badge">Selamat datang</span>
      <h5 class="howto-title">Yuk beresin tagihan dengan cepat ✨</h5>
      <p class="howto-sub">Transfer → upload bukti → tunggu verifikasi. Selesai!</p>

      {{-- Rekening resmi --}}
      <div class="bank-card mb-2">
        <div>
          <div class="fw-semibold">Rekening Kampus (Resmi) — Bank Jateng</div>
          <div class="small muted">Magister Pend Agama Islam</div>
        </div>
        <div class="text-end">
          <div class="mono fw-bold fs-6 copyable" data-copy="Magister Pend Agama Islam" title="Klik untuk salin a.n.">
            a.n. Magister Pend Agama Islam <span class="copy-hint">Salin</span>
          </div>
          <div class="mono fw-bold fs-5 copyable mt-1" data-copy="6033046486" title="Klik untuk salin nomor rekening">
            No. Rek. <span class="acct">6033046486</span> <span class="copy-hint">Salin</span>
          </div>
        </div>
      </div>

      <div class="bank-card mb-2" style="background:#111827">
        <div>
          <div class="fw-semibold">Rekening Kampus (Resmi) — BSI</div>
          <div class="small muted">Magister Pendidikan Agama Islam</div>
        </div>
        <div class="text-end">
          <div class="mono fw-bold fs-6 copyable" data-copy="Magister Pendidikan Agama Islam" title="Klik untuk salin a.n.">
            a.n. Magister Pendidikan Agama Islam <span class="copy-hint">Salin</span>
          </div>
          <div class="mono fw-bold fs-5 copyable mt-1" data-copy="9998799985" title="Klik untuk salin nomor rekening">
            No. Rek. <span class="acct">9998799985</span> <span class="copy-hint">Salin</span>
          </div>
        </div>
      </div>

      <ul class="howto-steps">
        <li class="howto-step"><span class="howto-num">1</span><div><h6>Cek invoice & catat nominal</h6><p>Pastikan bulan/TA yang mau dibayar dan nominalnya <b>persis sama</b> seperti di tabel.</p></div></li>
        <li class="howto-step"><span class="howto-num">2</span><div><h6>Transfer ke rekening resmi</h6><p>Tulis <b>berita transfer (WAJIB)</b> dengan format: <span class="mono chip">NIM - NAMA - BULAN/TA</span></p></div></li>
        <li class="howto-step"><span class="howto-num">3</span><div><h6>Simpan bukti yang jelas</h6><p>Harus terlihat: tanggal, nominal, rekening tujuan, dan berita transfer.</p></div></li>
        <li class="howto-step"><span class="howto-num">4</span><div><h6>Upload di baris invoice yang sesuai</h6><p>Klik <b>Detail</b> → <b>Upload</b> → <b>Kirim</b>.</p></div></li>
        <li class="howto-step"><span class="howto-num">5</span><div><h6>Tunggu verifikasi</h6><p>Status akan berubah jadi <span class="chip">Menunggu Verifikasi</span> → <span class="chip">LUNAS</span> kalau cocok.</p></div></li>
        <li class="howto-step"><span class="howto-num">6</span><div><h6>Perlu koreksi?</h6><p>Pakai tombol <b>Reset</b> untuk hapus bukti & ulangi upload.</p></div></li>
      </ul>

      <div class="mt-2 p-2 rounded border bg-white">
        <div class="fw-semibold mb-1">Butuh konfirmasi/ada kendala? 📲</div>
        <div>Hubungi admin <b>Shicha Alfiya</b> di
          <a href="https://wa.me/6288221372120" target="_blank">088221372120</a> (WhatsApp).
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
              <th style="width:90px">Angsuran</th>
              <th>Bulan</th>
              <th class="text-end">Jumlah</th>
              <th>Status</th>
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

                $buktiFile = $inv->bukti_pembayaran ?? $inv->bukti ?? null;
                $buktiUrl  = $buktiFile
                  ? (Str::contains($buktiFile, '/')
                        ? asset('storage/'.ltrim($buktiFile,'/'))
                        : asset('storage/bukti_reguler/'.ltrim($buktiFile,'/')))
                  : null;

                $angsKe = $inv->angsuran_ke ?? $inv->cicilan_ke ?? null;

                $statusRow  = strtolower((string)($inv->status ?? ''));
                $statusMhs  = strtolower((string)($mhs?->status ?? ''));
                $isPaid     = in_array($statusRow, ['lunas','lunas (otomatis)','terverifikasi'], true);
                $isLocked   = $isPaid || $statusMhs === 'lulus';

                $iid = $inv->id ?? $inv->invoice_id ?? $inv->kode ?? null;

                // Detail URL
                $detailUrl = null;
                if ($detailRoute && $iid) {
                  try { $detailUrl = route($detailRoute, ['invoice' => $iid]); } catch (\Throwable $e) {}
                  if (!$detailUrl) { try { $detailUrl = route($detailRoute, ['id' => $iid]); } catch (\Throwable $e) {} }
                  if (!$detailUrl) { try { $detailUrl = route($detailRoute, [$iid]); } catch (\Throwable $e) {} }
                }
                if (!$detailUrl && $iid) { $detailUrl = url('/reguler/invoices/'.$iid); }

                // Kwitansi PREVIEW url
                $kwPreviewUrl = null;
                if ($kwitansiPreviewRoute && $iid) {
                  try { $kwPreviewUrl = route($kwitansiPreviewRoute, ['invoice' => $iid]); } catch (\Throwable $e) {}
                  if (!$kwPreviewUrl) { try { $kwPreviewUrl = route($kwitansiPreviewRoute, ['id' => $iid]); } catch (\Throwable $e) {} }
                  if (!$kwPreviewUrl) { try { $kwPreviewUrl = route($kwitansiPreviewRoute, [$iid]); } catch (\Throwable $e) {} }
                }

                $nominal = (int)($inv->jumlah ?? $inv->nominal ?? 0);
              @endphp

              <tr>
                <td>{{ $i + 1 }}</td>
                <td>@if(!is_null($angsKe)) Ke-{{ $angsKe }} @else — @endif</td>
                <td>{{ $inv->bulan ?? '-' }}</td>
                <td class="text-end" style="white-space:nowrap;">Rp {{ number_format($nominal, 0, ',', '.') }}</td>
                <td>
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
                  @endswitch
                </td>
                <td class="text-center">
                  @if($buktiUrl)
                    <a href="{{ $buktiUrl }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary">Lihat</a>
                  @else
                    —
                  @endif
                </td>

                <td class="text-nowrap">
                  {{-- Detail --}}
                  <a href="{{ $detailUrl ?? '#' }}"
                     class="btn btn-sm btn-success @if(!$detailUrl) disabled @endif"
                     @if(!$detailUrl) aria-disabled="true" tabindex="-1" @endif>
                     Detail
                  </a>

                  {{-- Kwitansi (Preview) – tampil hanya jika route ada; disable bila belum lunas --}}
                  @if($kwPreviewUrl)
                    <a href="{{ $kwPreviewUrl }}"
                       target="_blank" rel="noopener"
                       class="btn btn-sm ms-1 {{ $isPaid ? 'btn-outline-primary' : 'btn-outline-secondary disabled' }}"
                       @if(!$isPaid) aria-disabled="true" tabindex="-1" @endif>
                      Kwitansi
                    </a>
                  @else
                    <button class="btn btn-sm btn-outline-secondary ms-1" disabled>Kwitansi</button>
                  @endif

                  {{-- Reset bukti (aktif jika belum locked) --}}
                  @php
                    $resetRoute = null;
                    foreach ([
                      'mahasiswa_reguler.invoices.reset',
                      'reguler.invoices.reset',
                    ] as $cand) {
                      if (Route::has($cand)) { $resetRoute = $cand; break; }
                    }
                    $resetAction = null;
                    if ($resetRoute && $iid) {
                      try { $resetAction = route($resetRoute, ['invoice' => $iid]); } catch (\Throwable $e) {}
                      if (!$resetAction) { try { $resetAction = route($resetRoute, ['id' => $iid]); } catch (\Throwable $e) {} }
                      if (!$resetAction) { try { $resetAction = route($resetRoute, [$iid]); } catch (\Throwable $e) {} }
                    }
                    if (!$resetAction && $iid) { $resetAction = url('/reguler/invoices/'.$iid.'/reset'); }
                  @endphp

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
                <td colspan="7" class="text-center text-muted py-4">Belum ada invoice.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    {{-- FOOTER: Kwitansi semua lunas (Reguler) --}}
    <div class="card-footer d-flex justify-content-between align-items-center">
      <div class="text-muted small">Cetak semua tagihan yang sudah <b>Lunas/Terverifikasi</b> dalam satu PDF.</div>
      @if($kwitansiBulkRoute)
        <a href="{{ route($kwitansiBulkRoute) }}"
           class="btn btn-success @if($paidCount<=0) disabled @endif"
           @if($paidCount<=0) aria-disabled="true" tabindex="-1" @endif>
          Kwitansi Semua Lunas
        </a>
      @else
        <button class="btn btn-success" disabled>Kwitansi Semua Lunas</button>
      @endif
    </div>
  </div>
@endsection

@push('scripts')
<script>
(function () {
  function hasBootstrapCollapse() {
    try { return (typeof window !== 'undefined') && !!window.bootstrap && typeof window.bootstrap.Collapse !== 'undefined'; }
    catch (e) { return false; }
  }
  function showCopied(el) {
    try {
      el.querySelectorAll('.copied-badge').forEach(function (n) { n.remove(); });
      var b = document.createElement('span');
      b.className = 'copied-badge';
      b.textContent = 'Tersalin';
      el.appendChild(b);
      setTimeout(function () { b.remove(); }, 1400);
    } catch (e) {}
  }

  var btn  = document.getElementById('howtoToggleReg');
  var pane = document.getElementById('howtoPanelReg');
  if (btn && pane) {
    btn.addEventListener('click', function (e) {
      if (!hasBootstrapCollapse()) {
        e.preventDefault();
        pane.classList.toggle('show');
        btn.setAttribute('aria-expanded', pane.classList.contains('show') ? 'true' : 'false');
      }
    }, false);
  }

  var copyables = document.querySelectorAll('.copyable');
  for (var i = 0; i < copyables.length; i++) {
    (function (el) {
      el.addEventListener('click', function () {
        var text = el.getAttribute('data-copy') || (el.textContent || '').trim();
        var useFallback = (!navigator.clipboard || typeof navigator.clipboard.writeText !== 'function');
        if (useFallback) {
          try {
            var ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.top = '-9999px';
            document.body.appendChild(ta); ta.focus(); ta.select(); document.execCommand('copy'); ta.remove();
            showCopied(el);
          } catch (e) {}
        } else {
          navigator.clipboard.writeText(text).then(function(){ showCopied(el); }).catch(function(){});
        }
      }, false);
    })(copyables[i]);
  }
})();
</script>
@endpush
