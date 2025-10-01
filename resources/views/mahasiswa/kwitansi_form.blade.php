@extends('layouts.mahasiswa')
@section('title', 'Form Kwitansi Pembayaran')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Form Kwitansi Pembayaran</h4>
    <a href="{{ route('mahasiswa.invoice.index') }}" class="btn btn-outline-secondary">Kembali</a>
  </div>

  <div class="card border-success shadow-sm" style="max-width: 720px">
    <div class="card-header bg-success text-white"><strong>Data Untuk Kwitansi</strong></div>
    <div class="card-body">
      <form action="{{ route('mahasiswa.invoice.kwitansi.download', $invoice->id) }}" method="POST">
        @csrf

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nama</label>
            <input type="text" name="nama" class="form-control" value="{{ old('nama', auth()->user()->nama ?? '') }}" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">NIM</label>
            <input type="text" name="nim" class="form-control" value="{{ old('nim', auth()->user()->nim ?? '') }}" readonly>
          </div>

          <div class="col-md-6">
            <label class="form-label">Angkatan</label>
            <input type="text" name="angkatan" class="form-control @error('angkatan') is-invalid @enderror"
                   value="{{ old('angkatan', auth()->user()->angkatan ?? '') }}" placeholder="Misal: 2022">
            @error('angkatan') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>
          <div class="col-md-6">
            <label class="form-label">No. HP</label>
            <input type="text" name="no_hp" class="form-control @error('no_hp') is-invalid @enderror"
                   value="{{ old('no_hp', auth()->user()->no_hp ?? '') }}" placeholder="08xxxxxxxxxx">
            @error('no_hp') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="col-md-6">
            <label class="form-label">Bulan</label>
            <input type="text" class="form-control" value="{{ $invoice->bulan }}" readonly>
          </div>
          <div class="col-md-6">
            <label class="form-label">Jumlah</label>
            <input type="text" class="form-control" value="Rp{{ number_format($invoice->jumlah, 0, ',', '.') }}" readonly>
          </div>
        </div>

        <div class="d-flex gap-2 mt-4">
          <button type="submit" class="btn btn-success">Download PDF</button>
          <a href="{{ route('mahasiswa.invoice.detail', $invoice->id) }}" class="btn btn-outline-secondary">Batal</a>
        </div>
      </form>
    </div>
  </div>
@endsection

@push('scripts')
@endpush
