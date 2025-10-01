@extends('layouts.mahasiswa_reguler')
@section('title', 'Form Kwitansi Pembayaran (Reguler)')

@section('content')
@php
  $mhs    = $mahasiswaReguler ?? $mahasiswa ?? auth('mahasiswa_reguler')->user();
  $status = $invoice->status ?? 'Belum';
  $isPaid = in_array($status, ['Lunas','Lunas (Otomatis)'], true);
  $nominal= (int)($invoice->jumlah ?? $invoice->nominal ?? 0);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">Form Kwitansi Pembayaran</h4>
  {{-- Tetap pakai alias route yang sudah ada di app kamu --}}
  <a href="{{ route('mahasiswa_reguler.invoice.index') }}" class="btn btn-outline-secondary">Kembali</a>
</div>

{{-- Alerts --}}
@foreach (['success'=>'success','info'=>'info','warning'=>'warning','error'=>'danger'] as $k=>$cls)
  @if (session($k))
    <div class="alert alert-{{ $cls }} alert-dismissible fade show">
      {{ session($k) }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
@endforeach

@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">
      @foreach ($errors->all() as $e)
        <li>{{ $e }}</li>
      @endforeach
    </ul>
  </div>
@endif

{{-- Info status pembayaran (informasi saja; tidak mengubah flow) --}}
@if(!$isPaid)
  <div class="alert alert-warning">
    Status tagihan ini <strong>{{ $status }}</strong>. Kwitansi resmi biasanya diberikan setelah <strong>Lunas</strong>.
    Jika tetap perlu mengunduh, silakan lanjutkan.
  </div>
@endif

<div class="card border-success shadow-sm" style="max-width: 760px">
  <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
    <strong>Data Untuk Kwitansi</strong>
    <span class="badge {{ $isPaid ? 'bg-light text-success' : 'bg-light text-warning' }}">
      Status: {{ $status }}
    </span>
  </div>

  <div class="card-body">
    <form action="{{ route('mahasiswa_reguler.invoice.kwitansi.download', $invoice->id) }}" method="POST">
      @csrf

      <div class="row g-3">
        <div class="col-md-6">
          <label class="form-label">Nama</label>
          <input type="text" name="nama" class="form-control"
                 value="{{ old('nama', $mhs->nama ?? '') }}" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label">NIM</label>
          <input type="text" name="nim" class="form-control"
                 value="{{ old('nim', $mhs->nim ?? '') }}" readonly>
        </div>

        <div class="col-md-6">
          <label class="form-label">Angkatan</label>
          <input type="text" name="angkatan" class="form-control @error('angkatan') is-invalid @enderror"
                 value="{{ old('angkatan', $mhs->angkatan ?? '') }}" placeholder="Misal: 2022">
          @error('angkatan') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="col-md-6">
          <label class="form-label">No. HP</label>
          <input type="text" name="no_hp" class="form-control @error('no_hp') is-invalid @enderror"
                 value="{{ old('no_hp', $mhs->no_hp ?? '') }}" placeholder="08xxxxxxxxxx">
          @error('no_hp') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>

        <div class="col-md-6">
          <label class="form-label">Bulan</label>
          <input type="text" class="form-control" value="{{ $invoice->bulan }}" readonly>
        </div>
        <div class="col-md-6">
          <label class="form-label">Jumlah</label>
          <input type="text" class="form-control" value="Rp{{ number_format($nominal, 0, ',', '.') }}" readonly>
        </div>
      </div>

      <div class="d-flex gap-2 mt-4">
        <button type="submit" class="btn btn-success">Download PDF</button>
        {{-- Tetap pakai alias "detail" yang sudah dipakai view lain di proyekmu --}}
        <a href="{{ route('mahasiswa_reguler.invoice.detail', $invoice->id) }}" class="btn btn-outline-secondary">Batal</a>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
@endpush
