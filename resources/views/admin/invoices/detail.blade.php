{{-- resources/views/admin/invoices/detail.blade.php --}}
@extends('layouts.admin')

@section('title', 'Detail Tagihan RPL')

@section('content')
  <h4 class="mb-4">Detail Invoice: {{ $invoice->mahasiswa->nama }} ({{ $invoice->bulan }})</h4>

  <div class="card mb-4">
    <div class="card-body">
      <p><strong>Nama:</strong> {{ $invoice->mahasiswa->nama }}</p>
      <p><strong>NIM:</strong> {{ $invoice->mahasiswa->nim }}</p>
      <p><strong>Bulan:</strong> {{ $invoice->bulan }}</p>
      <p><strong>Jumlah:</strong> Rp{{ number_format($invoice->jumlah, 0, ',', '.') }}</p>
      <p>
        <strong>Status:</strong>
        <span class="badge {{
          $invoice->status === 'Belum'    ? 'bg-warning text-dark' :
          ($invoice->status === 'Lunas'   ? 'bg-success' :
          ($invoice->status === 'Ditolak' ? 'bg-danger' : 'bg-secondary'))
        }}">
          {{ $invoice->status }}
        </span>
      </p>

      @if($invoice->bukti)
        <p>
          <strong>Bukti Pembayaran:</strong><br>
          <img src="{{ asset('storage/bukti/' . $invoice->bukti) }}"
               alt="Bukti Pembayaran" width="200">
        </p>
      @endif
    </div>
  </div>

  <form action="{{ route('admin.invoices.verify', $invoice) }}" method="POST" class="d-inline">
    @csrf
    <button class="btn btn-success">✅ Verifikasi</button>
  </form>

  <form action="{{ route('admin.invoices.reject', $invoice) }}" method="POST" class="d-inline ms-2">
    @csrf
    <button class="btn btn-danger">❌ Tolak</button>
  </form>

  <a href="{{ route('admin.invoices.index') }}" class="btn btn-outline-secondary ms-3">← Kembali</a>
@endsection
