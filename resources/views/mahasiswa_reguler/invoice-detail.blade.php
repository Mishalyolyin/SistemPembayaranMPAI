@extends('layouts.mahasiswa_reguler')
@section('title', 'Detail Tagihan SKS (Reguler)')

@section('content')
  @php
    use Illuminate\Support\Facades\Route;

    $mhs = ($mahasiswaReguler ?? $mahasiswa ?? auth()->user());

    $indexRoute = Route::has('mahasiswa_reguler.invoice.index')
      ? 'mahasiswa_reguler.invoice.index'
      : (Route::has('reguler.invoices.index') ? 'reguler.invoices.index' : 'reguler.invoice.index');

    $pickPlanRoute = Route::has('mahasiswa_reguler.angsuran.form')
      ? 'mahasiswa_reguler.angsuran.form'
      : (Route::has('reguler.invoice.setup') ? 'reguler.invoice.setup' : 'reguler.angsuran.create');

    $uploadRoute = Route::has('mahasiswa_reguler.invoices.upload')
      ? 'mahasiswa_reguler.invoices.upload'
      : 'reguler.invoices.upload';

    $resetRoute = Route::has('mahasiswa_reguler.invoices.reset')
      ? 'mahasiswa_reguler.invoices.reset'
      : 'reguler.invoices.reset';

    $kwitansiRoute = Route::has('mahasiswa_reguler.invoice.kwitansi.form')
      ? 'mahasiswa_reguler.invoice.kwitansi.form'
      : (Route::has('reguler.invoice.kwitansi.form') ? 'reguler.invoice.kwitansi.form' : null);

    $buktiPath = null;
    if (!empty($invoice->bukti_pembayaran)) {
      $buktiPath = asset('storage/' . ltrim($invoice->bukti_pembayaran, '/'));
    } elseif (!empty($invoice->bukti)) {
      $buktiPath = asset('storage/bukti_reguler/' . ltrim($invoice->bukti, '/'));
    }

    $status = $invoice->status ?? 'Belum';
    $showReset = ($buktiPath || in_array($status, ['Menunggu Verifikasi','Ditolak']))
                 && !in_array($status, ['Lunas','Lunas (Otomatis)']);
  @endphp

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Detail Tagihan SKS</h4>
    <a href="{{ route($indexRoute) }}" class="btn btn-outline-secondary">Kembali</a>
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
      <a href="{{ route($pickPlanRoute) }}" class="alert-link">Pilih Angsuran</a>
    </div>
  @endif

  <div class="card border-success shadow-sm mb-4">
    <div class="card-header bg-success text-white"><strong>Rincian Invoice</strong></div>
    <div class="card-body">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="p-3 border rounded-3 bg-light">
            <div class="mb-2"><strong>Bulan:</strong> {{ $invoice->bulan }}</div>
            <div class="mb-2"><strong>Jumlah:</strong> Rp {{ number_format((int)$invoice->jumlah, 0, ',', '.') }}</div>
            <div class="mb-2"><strong>Status:</strong>
              @switch($status)
                @case('Lunas') @case('Lunas (Otomatis)') <span class="badge bg-success">{{ $status }}</span> @break
                @case('Ditolak') <span class="badge bg-danger">Ditolak</span> @break
                @case('Menunggu Verifikasi') <span class="badge bg-warning text-dark">Menunggu Verifikasi</span> @break
                @default <span class="badge bg-secondary">{{ $status }}</span>
              @endswitch
            </div>
            @if(!empty($invoice->jatuh_tempo))
              <div class="mb-2"><strong>Jatuh Tempo:</strong> {{ $invoice->jatuh_tempo }}</div>
            @endif
            @if(!empty($invoice->angsuran_ke))
              <div class="mb-2"><strong>Angsuran Ke:</strong> {{ $invoice->angsuran_ke }}</div>
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

      @if(!empty($invoice->catatan_penolakan) && $status === 'Ditolak')
        <div class="alert alert-danger mt-3 mb-0">
          <strong>Catatan Penolakan:</strong> {{ $invoice->catatan_penolakan }}
        </div>
      @endif
    </div>
  </div>

  <div class="card border-success shadow-sm">
    <div class="card-header bg-success text-white"><strong>Upload Bukti Pembayaran</strong></div>
    <div class="card-body">
      <div class="mb-3"><small class="text-muted">Format: JPG, JPEG, atau PNG (maks 10MB).</small></div>

      @if($buktiPath)
        <div class="mb-3">
          <span class="me-2">Bukti saat ini:</span>
          <a href="{{ $buktiPath }}" target="_blank" class="btn btn-sm btn-outline-secondary">Lihat Bukti</a>
        </div>
      @endif

      @if(!in_array($status, ['Lunas','Lunas (Otomatis)']))
        {{-- FORM UPLOAD (sendiri) --}}
        <form action="{{ route($uploadRoute, $invoice->id) }}" method="POST" enctype="multipart/form-data" class="row g-3" id="uploadForm">
          @csrf
          <div class="col-md-8">
            <input
              type="file"
              name="bukti"
              class="form-control @error('bukti') is-invalid @enderror"
              accept=".jpg,.jpeg,.png,image/*"
              @if(!$buktiPath) required @endif
            >
            @error('bukti') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-4 d-grid d-md-flex gap-2">
            <button type="submit" class="btn btn-success">Kirim</button>
            @if($buktiPath)
              <a href="{{ $buktiPath }}" target="_blank" class="btn btn-outline-secondary">Preview</a>
            @endif
          </div>
        </form>

        {{-- FORM RESET (terpisah, tidak nested) --}}
        @if($showReset)
          <div class="mt-2">
            <form action="{{ route($resetRoute, $invoice->id) }}" method="POST" class="d-inline" id="resetForm">
              @csrf
              <button class="btn btn-warning"
                      onclick="return confirm('Reset bukti & status invoice ke \"Belum\"?')">
                Reset
              </button>
            </form>
          </div>
        @endif
      @else
        <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
          <div>Tagihan ini sudah <strong>Lunas</strong>.</div>
        </div>
        @if($kwitansiRoute)
          <div class="mt-3">
            <a href="{{ route($kwitansiRoute, $invoice->id) }}" class="btn btn-outline-success">
              Unduh Kwitansi (PDF)
            </a>
          </div>
        @endif
      @endif
    </div>
  </div>
@endsection

@push('scripts')
@endpush
