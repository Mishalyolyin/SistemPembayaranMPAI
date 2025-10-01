{{-- resources/views/admin/invoices_reguler/detail.blade.php --}}
@extends('layouts.admin')

@section('title', 'Detail Tagihan Reguler')

@section('content')
@php
  $mhs = $invoice->mahasiswaReguler ?? null;

  // Normalisasi status
  $status   = $invoice->status ?? 'Belum';
  $isPaid   = in_array($status, ['Lunas','Lunas (Otomatis)','Paid','Terverifikasi'], true);
  $isWait   = in_array($status, ['Menunggu Verifikasi','Waiting','Pending','Menunggu'], true);
  $isReject = in_array($status, ['Ditolak','Reject'], true);
  $badge    = $isPaid ? 'bg-success' : ($isReject ? 'bg-danger' : ($isWait ? 'bg-warning text-dark' : 'bg-secondary'));

  // URL bukti (dukung 2 skema + accessor)
  $buktiUrl = method_exists($invoice, 'getBuktiUrlAttribute') ? $invoice->bukti_url : null;
  if (!$buktiUrl) {
    if (!empty($invoice->bukti_pembayaran)) {
      $buktiUrl = asset('storage/' . ltrim($invoice->bukti_pembayaran, '/'));
    } elseif (!empty($invoice->bukti)) {
      $buktiUrl = asset('storage/bukti_reguler/' . ltrim($invoice->bukti, '/'));
    }
  }
  $isImg = $buktiUrl && preg_match('/\.(jpe?g|png|gif|webp)$/i', $buktiUrl);
@endphp

<div class="d-flex justify-content-between align-items-center mb-3">
  <h4 class="mb-0">
    Detail Invoice Reguler:
    {{ $mhs->nama ?? '-' }}
    <small class="text-muted">({{ $invoice->bulan }})</small>
  </h4>
  <div class="d-flex gap-2">
    <a href="{{ route('admin.invoices-reguler.index') }}" class="btn btn-outline-secondary">← Kembali</a>
    @if($buktiUrl)
      <a href="{{ route('admin.invoices-reguler.bukti', $invoice->id) }}" target="_blank" class="btn btn-outline-primary">Lihat Bukti</a>
    @endif
  </div>
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

<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header bg-light"><strong>Data Invoice</strong></div>
      <div class="card-body">
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">Nama</div>
          <div class="col-sm-7">{{ $mhs->nama ?? '-' }}</div>
        </div>
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">NIM</div>
          <div class="col-sm-7">{{ $mhs->nim ?? '—' }}</div>
        </div>
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">Bulan</div>
          <div class="col-sm-7">{{ $invoice->bulan }}</div>
        </div>
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">Jumlah</div>
          <div class="col-sm-7">Rp {{ number_format((int)($invoice->jumlah ?? $invoice->nominal ?? 0), 0, ',', '.') }}</div>
        </div>
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">Status</div>
          <div class="col-sm-7"><span class="badge {{ $badge }}">{{ $status }}</span></div>
        </div>
        @if(!empty($invoice->angsuran_ke))
          <div class="row mb-2">
            <div class="col-sm-5 text-muted">Angsuran Ke</div>
            <div class="col-sm-7">{{ $invoice->angsuran_ke }}</div>
          </div>
        @endif
        @if(!empty($invoice->jatuh_tempo))
          <div class="row mb-2">
            <div class="col-sm-5 text-muted">Jatuh Tempo</div>
            <div class="col-sm-7">{{ \Illuminate\Support\Carbon::parse($invoice->jatuh_tempo)->format('d M Y') }}</div>
          </div>
        @endif
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">Dibuat</div>
          <div class="col-sm-7">{{ $invoice->created_at?->format('d M Y H:i') }}</div>
        </div>
        <div class="row mb-2">
          <div class="col-sm-5 text-muted">Diverifikasi</div>
          <div class="col-sm-7">{{ $invoice->verified_at?->format('d M Y H:i') ?? '—' }}</div>
        </div>
        @if(!empty($invoice->catatan_penolakan))
          <div class="row mb-2">
            <div class="col-sm-5 text-muted">Catatan Penolakan</div>
            <div class="col-sm-7"><span class="text-danger">{{ $invoice->catatan_penolakan }}</span></div>
          </div>
        @endif
      </div>
    </div>

    {{-- Aksi cepat --}}
    <div class="card shadow-sm mt-3">
      <div class="card-header bg-light"><strong>Aksi Verifikasi</strong></div>
      <div class="card-body">
        <div class="d-flex flex-wrap gap-2">
          {{-- Verifikasi (Lunas) --}}
          <form method="POST" action="{{ route('admin.invoices-reguler.verify', $invoice->id) }}"
                onsubmit="return confirm('Verifikasi sebagai Lunas?')">
            @csrf
            <button class="btn btn-success" {{ $isPaid ? 'disabled' : '' }}>✅ Verifikasi</button>
          </form>

          {{-- Verifikasi otomatis --}}
          <form method="POST" action="{{ route('admin.invoices-reguler.verify', $invoice->id) }}"
                onsubmit="return confirm('Set Lunas (Otomatis)?')">
            @csrf
            <input type="hidden" name="mode" value="auto">
            <button class="btn btn-outline-success" {{ $isPaid ? 'disabled' : '' }}>⚡ Lunas (Otomatis)</button>
          </form>

          {{-- Tolak --}}
          <form method="POST" action="{{ route('admin.invoices-reguler.reject', $invoice->id) }}"
                onsubmit="return (this.alasan.value = prompt('Alasan penolakan?')) !== null;">
            @csrf
            <input type="hidden" name="alasan" value="">
            <button class="btn btn-danger" {{ $isPaid ? 'disabled' : '' }}>❌ Tolak</button>
          </form>

          {{-- Reset --}}
          <form method="POST" action="{{ route('admin.invoices-reguler.reset', $invoice->id) }}"
                onsubmit="return confirm('Reset bukti & status ke Belum?')">
            @csrf
            <button class="btn btn-outline-secondary" {{ $isPaid ? 'disabled' : '' }}>↺ Reset</button>
          </form>
        </div>

        @if($isPaid)
          <div class="alert alert-success mt-3 mb-0">Invoice sudah berstatus <strong>Lunas</strong>.</div>
        @elseif($isReject)
          <div class="alert alert-danger mt-3 mb-0">Invoice <strong>Ditolak</strong>. Anda dapat reset untuk unggah ulang bukti.</div>
        @elseif($isWait)
          <div class="alert alert-warning mt-3 mb-0">Invoice <strong>Menunggu Verifikasi</strong>. Silakan cek bukti terlebih dahulu.</div>
        @endif
      </div>
    </div>
  </div>

  {{-- Preview bukti --}}
  <div class="col-lg-5">
    <div class="card shadow-sm h-100">
      <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <strong>Bukti Pembayaran</strong>
        @if($buktiUrl)
          <a href="{{ route('admin.invoices-reguler.bukti', $invoice->id) }}" target="_blank" class="btn btn-sm btn-outline-primary">Buka</a>
        @endif
      </div>
      <div class="card-body d-flex align-items-center justify-content-center">
        @if($buktiUrl)
          @if($isImg)
            <a href="{{ route('admin.invoices-reguler.bukti', $invoice->id) }}" target="_blank" class="d-block w-100 text-center">
              <img src="{{ $buktiUrl }}" alt="Bukti Pembayaran" class="img-fluid rounded border">
            </a>
          @else
            <a href="{{ route('admin.invoices-reguler.bukti', $invoice->id) }}" target="_blank" class="btn btn-outline-secondary">
              Lihat Bukti
            </a>
          @endif
        @else
          <div class="text-muted text-center">Belum ada bukti terunggah.</div>
        @endif
      </div>

      <div class="card-footer bg-white">
        <div class="small text-muted mb-1">Mahasiswa</div>
        <div class="fw-semibold">{{ $mhs->nama ?? '-' }}</div>
        <div class="text-muted">NIM: {{ $mhs->nim ?? '—' }}</div>
        <div class="text-muted">Semester Awal: {{ ucfirst($mhs->semester_awal ?? '—') }}</div>
        <div class="text-muted">TA: {{ $mhs->tahun_akademik ?? '—' }}</div>
      </div>
    </div>
  </div>
</div>
@endsection
