{{-- resources/views/mahasiswa/invoice-detail.blade.php --}}
@php use Illuminate\Support\Facades\Route; @endphp
@extends('layouts.mahasiswa')
@section('title', 'Detail Tagihan SKS')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Detail Tagihan SKS</h4>
    <a href="{{ route('mahasiswa.invoice.index') }}" class="btn btn-outline-secondary">Kembali</a>
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

  @if (empty($mahasiswa->angsuran))
    <div class="alert alert-warning">
      Anda belum memilih skema angsuran. Silakan tentukan terlebih dahulu.
      <a href="{{ route('mahasiswa.angsuran.form') }}" class="alert-link">Pilih Angsuran</a>
    </div>
  @endif

  @php
    // ===== Locks & helpers =====
    $statusInv = strtolower((string)($invoice->status ?? ''));
    $statusMhs = strtolower((string)($mahasiswa->status ?? ''));
    $isLocked  = in_array($statusInv, ['lunas','lunas (otomatis)','terverifikasi']) || $statusMhs === 'lulus';

    $angsKe = $invoice->angsuran_ke ?? $invoice->cicilan_ke ?? null;

    // Catatan admin (penolakan)
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

    // File bukti
    $buktiFile = $invoice->bukti ?? $invoice->bukti_pembayaran ?? null;
    $buktiUrl  = $buktiFile
      ? (str_contains($buktiFile, '/')
          ? asset('storage/'.$buktiFile)
          : asset('storage/bukti/'.$buktiFile))
      : null;

    // ID fleksibel
    $iid = $invoice->id ?? $invoice->invoice_id ?? $invoice->kode ?? null;

    // Route upload (fallback aman)
    $uploadAction = null;
    foreach (['mahasiswa.invoices.upload','mahasiswa.invoice.upload'] as $r) {
      if (Route::has($r) && $iid) {
        try { $uploadAction = route($r, ['invoice' => $iid]); break; } catch (\Throwable $e) {}
        try { $uploadAction = route($r, ['id' => $iid]); break; } catch (\Throwable $e) {}
        try { $uploadAction = route($r, [$iid]); break; } catch (\Throwable $e) {}
      }
    }
    $uploadAction = $uploadAction ?: url('/mahasiswa/invoices/'.$iid.'/upload');

    // Route reset (POST) – hanya URL; methodnya kita paksa POST via <button formaction=...>
    $resetAction = null;
    foreach (['mahasiswa.invoices.reset','mahasiswa.invoice.reset'] as $r) {
      if (Route::has($r) && $iid) {
        try { $resetAction = route($r, ['invoice' => $iid]); break; } catch (\Throwable $e) {}
        try { $resetAction = route($r, ['id' => $iid]); break; } catch (\Throwable $e) {}
        try { $resetAction = route($r, [$iid]); break; } catch (\Throwable $e) {}
      }
    }
    $resetAction = $resetAction ?: url('/mahasiswa/invoices/'.$iid.'/reset');

    // Route kwitansi (opsional)
    $kwitansiUrl = null;
    foreach (['mahasiswa.invoices.kwitansi.form','mahasiswa.invoice.kwitansi.form','mahasiswa.invoices.kwitansi','mahasiswa.invoice.kwitansi'] as $cand) {
      if (Route::has($cand) && $iid) {
        try { $kwitansiUrl = route($cand, ['invoice' => $iid]); break; } catch (\Throwable $e) {}
        try { $kwitansiUrl = route($cand, ['id' => $iid]); break; } catch (\Throwable $e) {}
        try { $kwitansiUrl = route($cand, [$iid]); break; } catch (\Throwable $e) {}
      }
    }
  @endphp

  <div class="card border-success shadow-sm mb-4">
    <div class="card-header bg-success text-white">
      <strong>Rincian Invoice</strong>
    </div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 border rounded-3 bg-light">
            <div class="mb-2"><strong>Bulan:</strong> {{ $invoice->bulan ?? '-' }}</div>
            @if(!is_null($angsKe))
              <div class="mb-2"><strong>Angsuran:</strong> Ke-{{ $angsKe }}</div>
            @endif
            <div class="mb-2">
              <strong>Jumlah:</strong>
              Rp {{ number_format((int)($invoice->jumlah ?? $invoice->nominal ?? 0), 0, ',', '.') }}
            </div>
            <div class="mb-2"><strong>Status:</strong>
              @switch($invoice->status)
                @case('Lunas') @case('Lunas (Otomatis)') @case('Terverifikasi')
                  <span class="badge bg-success">{{ $invoice->status }}</span> @break
                @case('Ditolak') @case('Reject')
                  <span class="badge bg-danger">Ditolak</span> @break
                @case('Menunggu Verifikasi') @case('Pending') @case('Menunggu')
                  <span class="badge bg-warning text-dark">{{ $invoice->status }}</span> @break
                @default
                  <span class="badge bg-secondary">{{ $invoice->status ?? 'Belum Upload' }}</span>
              @endswitch
            </div>
            <div class="mb-0"><strong>Tanggal:</strong> {{ $invoice->created_at?->format('d M Y') }}</div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="p-3 border rounded-3">
            <div class="mb-2"><strong>Nama:</strong> {{ $mahasiswa->nama }}</div>
            <div class="mb-2"><strong>NIM:</strong> {{ $mahasiswa->nim }}</div>
            
            <div class="mb-0"><strong>No. HP:</strong> {{ $mahasiswa->no_hp ?? '—' }}</div>
          </div>
        </div>
      </div>

      @if($catatan && in_array($invoice->status, ['Ditolak','Reject']))
        <div class="alert alert-danger mt-3 mb-0">
          <strong>Catatan Admin:</strong> {{ $catatan }}
        </div>
      @endif
    </div>
  </div>

  <div class="card border-success shadow-sm">
    <div class="card-header bg-success text-white">
      <strong>Upload Bukti Pembayaran</strong>
    </div>
    <div class="card-body">
      @if($isLocked)
        <div class="alert alert-success mb-3">
          Invoice sudah diverifikasi atau akun Anda berstatus Lulus. Upload bukti baru dinonaktifkan.
        </div>
      @endif

      <div class="mb-3">
        <small class="text-muted">Format diperbolehkan: JPG, JPEG, PNG, atau PDF.</small>
      </div>

      @if($buktiUrl)
        <div class="mb-3">
          <span class="me-2">Bukti saat ini:</span>
          <a href="{{ $buktiUrl }}" target="_blank" class="btn btn-sm btn-outline-secondary">Lihat Bukti</a>
        </div>
      @endif

      @unless($isLocked)
        {{-- Satu form untuk upload. Tombol Reset akan override action ke $resetAction (POST) pakai formaction --}}
        <form action="{{ $uploadAction }}" method="POST" enctype="multipart/form-data" class="row g-3">
          @csrf
          <div class="col-md-8">
            <input type="file"
                   name="bukti"
                   class="form-control @error('bukti') is-invalid @enderror"
                   accept=".jpg,.jpeg,.png,.pdf"
                   @if(empty($buktiFile)) required @endif>
            @error('bukti')
              <div class="invalid-feedback">{{ $message }}</div>
            @enderror
          </div>
          <div class="col-md-4 d-grid d-md-flex gap-2">
            <button type="submit" class="btn btn-success">Kirim</button>
            @if($buktiUrl)
              <a href="{{ $buktiUrl }}" target="_blank" class="btn btn-outline-secondary">Preview</a>
            @endif

            @if($buktiUrl && $iid && $resetAction)
              {{-- Tombol reset: tetap POST. Tidak pakai <a href>, jadi anti-404 --}}
              <button type="submit"
                      class="btn btn-outline-danger"
                      formaction="{{ $resetAction }}"
                      formmethod="POST"
                      onclick="return confirm('Hapus bukti & kembalikan status ke Belum?')">
                Reset Bukti
              </button>
            @endif
          </div>
        </form>
      @endunless

      @if(in_array($invoice->status, ['Lunas','Lunas (Otomatis)','Terverifikasi']) && $kwitansiUrl)
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
