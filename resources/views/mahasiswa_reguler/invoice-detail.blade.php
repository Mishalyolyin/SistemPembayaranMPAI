{{-- resources/views/mahasiswa_reguler/invoice-detail.blade.php --}}
@extends('layouts.mahasiswa_reguler')
@section('title', 'Detail Tagihan SKS (Reguler)')

@section('content')
@php
  use Illuminate\Support\Facades\Route;
  use Illuminate\Support\Str;

  // Ambil user reguler dengan fallback aman
  $mhs = ($mahasiswaReguler ?? $mahasiswa ?? auth('mahasiswa_reguler')->user() ?? auth()->user());

  // ===== Route INDEX (prefer singular; fallback plural/legacy) =====
  $indexRouteName = null;
  foreach ([
    'mahasiswa_reguler.invoice.index',
    'reguler.invoice.index',
    'mahasiswa_reguler.invoices.index', // legacy
    'reguler.invoices.index',           // legacy
  ] as $cand) {
    if (Route::has($cand)) { $indexRouteName = $cand; break; }
  }
  $indexUrl = $indexRouteName ? route($indexRouteName) : url('/reguler/invoice');

  // ===== Form pilih angsuran (prefer singular; fallback legacy) =====
  $pickPlanRouteName = null;
  foreach ([
    'mahasiswa_reguler.angsuran.form',
    'reguler.invoice.setup',
    'reguler.angsuran.create', // legacy
  ] as $cand) {
    if (Route::has($cand)) { $pickPlanRouteName = $cand; break; }
  }

  // ===== Build Upload / Reset routes (coba beberapa pola param) =====
  $idLike = $invoice->id ?? $invoice->invoice_id ?? $invoice->kode ?? null;

  $buildRoute = function(array $candidates, $idLike) {
    foreach ($candidates as $name) {
      if (!Route::has($name) || !$idLike) continue;
      try { return route($name, ['invoice' => $idLike]); } catch (\Throwable $e) {}
      try { return route($name, ['id' => $idLike]); } catch (\Throwable $e) {}
      try { return route($name, [$idLike]); } catch (\Throwable $e) {}
    }
    return null;
  };

  $uploadUrl = $buildRoute([
    'mahasiswa_reguler.invoice.upload',
    'reguler.invoice.upload',
    'mahasiswa_reguler.invoices.upload', // legacy
    'reguler.invoices.upload',           // legacy
  ], $idLike) ?: url('/reguler/invoice/'.$idLike.'/upload');

  $resetUrl = $buildRoute([
    'mahasiswa_reguler.invoice.reset',
    'reguler.invoice.reset',
    'mahasiswa_reguler.invoices.reset', // legacy
    'reguler.invoices.reset',           // legacy
  ], $idLike) ?: url('/reguler/invoice/'.$idLike.'/reset');

  // ===== Kwitansi (form/preview) — tampil saat sudah lunas =====
  $kwitansiUrl = $buildRoute([
    'mahasiswa_reguler.invoice.kwitansi.form',
    'reguler.invoice.kwitansi.form',
    'mahasiswa_reguler.invoice.kwitansi.direct', // kalau ada direct preview
    'reguler.invoice.kwitansi.direct',
  ], $idLike);

  // ===== Path bukti (mendukung 2 kolom & 2 pola path) =====
  $buktiFile = $invoice->bukti_pembayaran ?? $invoice->bukti ?? null;
  $buktiUrl  = $buktiFile
      ? (Str::contains($buktiFile, '/')
          ? asset('storage/'.ltrim($buktiFile,'/'))
          : asset('storage/bukti_reguler/'.ltrim($buktiFile,'/')))
      : null;

  // ===== Status & kendali tombol =====
  $statusRaw  = (string)($invoice->status ?? 'Belum');
  $statusLow  = strtolower($statusRaw);
  $isPaid     = in_array($statusLow, ['lunas','lunas (otomatis)','terverifikasi'], true);
  $isLocked   = $isPaid || strtolower((string)($mhs?->status ?? '')) === 'lulus';

  $showReset  = ($buktiUrl || in_array($statusRaw, ['Menunggu Verifikasi','Ditolak'], true))
                && !$isLocked;

  $angsKe     = $invoice->angsuran_ke ?? $invoice->cicilan_ke ?? null;

  // Catatan penolakan (ambil dari beberapa kemungkinan field)
  $catatan = $invoice->alasan_tolak
         ?? $invoice->catatan_penolakan
         ?? $invoice->alasan_penolakan
         ?? $invoice->catatan_admin
         ?? $invoice->catatan
         ?? $invoice->alasan
         ?? $invoice->keterangan
         ?? $invoice->catatan_tolak
         ?? $invoice->keterangan_tolak
         ?? null;
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Detail Tagihan SKS</h4>
  <a href="{{ $indexUrl }}" class="btn btn-outline-secondary">Kembali</a>
</div>

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
    @if($pickPlanRouteName)
      <a href="{{ route($pickPlanRouteName) }}" class="alert-link">Pilih Angsuran</a>
    @endif
  </div>
@endif

<div class="card border-success shadow-sm mb-4">
  <div class="card-header bg-success text-white"><strong>Rincian Invoice</strong></div>
  <div class="card-body">
    <div class="row g-3">
      <div class="col-md-6">
        <div class="p-3 border rounded-3 bg-light">
          <div class="mb-2"><strong>Bulan:</strong> {{ $invoice->bulan ?? '-' }}</div>
          @if(!is_null($angsKe))
            <div class="mb-2"><strong>Angsuran Ke:</strong> {{ $angsKe }}</div>
          @endif
          <div class="mb-2">
            <strong>Jumlah:</strong>
            Rp {{ number_format((int)($invoice->jumlah ?? $invoice->nominal ?? 0), 0, ',', '.') }}
          </div>
          <div class="mb-2"><strong>Status:</strong>
            @switch($statusRaw)
              @case('Lunas') @case('Lunas (Otomatis)') @case('Terverifikasi')
                <span class="badge bg-success">{{ $statusRaw }}</span> @break
              @case('Ditolak') @case('Reject')
                <span class="badge bg-danger">Ditolak</span> @break
              @case('Menunggu Verifikasi') @case('Pending') @case('Menunggu')
                <span class="badge bg-warning text-dark">{{ $statusRaw }}</span> @break
              @default
                <span class="badge bg-secondary">{{ $statusRaw ?: 'Belum Upload' }}</span>
            @endswitch
          </div>
          @if(!empty($invoice->jatuh_tempo))
            <div class="mb-2"><strong>Jatuh Tempo:</strong> {{ $invoice->jatuh_tempo }}</div>
          @endif
          <div class="mb-0"><strong>Tanggal:</strong> {{ $invoice->created_at?->format('d M Y') }}</div>
        </div>
      </div>

      <div class="col-md-6">
        <div class="p-3 border rounded-3">
          <div class="mb-2"><strong>Nama:</strong> {{ $mhs->nama ?? '—' }}</div>
          <div class="mb-2"><strong>NIM:</strong> {{ $mhs->nim ?? '—' }}</div>
          <div class="mb-0"><strong>No. HP:</strong> {{ $mhs->no_hp ?? '—' }}</div>
        </div>
      </div>
    </div>

    @if($catatan && in_array($statusRaw, ['Ditolak','Reject'], true))
      <div class="alert alert-danger mt-3 mb-0">
        <strong>Catatan Admin:</strong> {{ $catatan }}
      </div>
    @endif
  </div>
</div>

<div class="card border-success shadow-sm">
  <div class="card-header bg-success text-white"><strong>Upload Bukti Pembayaran</strong></div>
  <div class="card-body">
    @if($isLocked)
      <div class="alert alert-success mb-3">
        Invoice sudah diverifikasi atau akun Anda berstatus Lulus. Upload bukti baru dinonaktifkan.
      </div>
    @endif

    <div class="mb-3">
      <small class="text-muted">Format diperbolehkan: JPG, JPEG, PNG (maks 10MB).</small>
    </div>

    @if($buktiUrl)
      <div class="mb-3">
        <span class="me-2">Bukti saat ini:</span>
        <a href="{{ $buktiUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">Lihat Bukti</a>
      </div>
    @endif

    @unless($isLocked)
      {{-- FORM UPLOAD --}}
      <form action="{{ $uploadUrl }}"
            method="POST" enctype="multipart/form-data" class="row g-3" id="uploadForm">
        @csrf
        <div class="col-md-8">
          <input
            type="file"
            name="bukti"
            class="form-control @error('bukti') is-invalid @enderror"
            accept=".jpg,.jpeg,.png,image/*"
            @if(empty($buktiUrl)) required @endif
          >
          @error('bukti')
            <div class="invalid-feedback">{{ $message }}</div>
          @enderror
        </div>
        <div class="col-md-4 d-grid d-md-flex gap-2">
          <button type="submit" class="btn btn-success">Kirim</button>
          @if($buktiUrl)
            <a href="{{ $buktiUrl }}" target="_blank" class="btn btn-outline-secondary">Preview</a>
          @endif
          @if($showReset && $resetUrl)
            {{-- Tombol reset: POST terpisah agar tidak nested form --}}
            <form action="{{ $resetUrl }}" method="POST" class="d-inline"
                  onsubmit="return confirm('Reset bukti & kembalikan status ke Belum?')">
              @csrf
              <button type="submit" class="btn btn-outline-danger">Reset</button>
            </form>
          @endif
        </div>
      </form>
    @endunless

    @if($isPaid && $kwitansiUrl)
      <hr class="my-4">
      <a href="{{ $kwitansiUrl }}" class="btn btn-outline-success">
        Unduh Kwitansi (PDF)
      </a>
    @endif
  </div>
</div>
@endsection

@push('scripts')
@endpush
