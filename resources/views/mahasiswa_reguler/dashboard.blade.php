{{-- resources/views/mahasiswa_reguler/dashboard.blade.php --}}
@extends('layouts.mahasiswa_reguler')
@section('title', 'Dashboard Mahasiswa Reguler')

@section('content')
@php
  use Carbon\Carbon;

  // ========= Ambil entity mahasiswa (robust) =========
  $m = ($mahasiswaReguler ?? $mahasiswa ?? auth()->user());

  // ========= Kumpulan invoice yg diberikan controller (aman ke 0) =========
  $rows = collect($invoices ?? $invoices_reguler ?? []);

  // ========= Helper angka & rupiah =========
  $toInt  = fn($v) => (int) preg_replace('/\D+/', '', (string) $v);
  $rupiah = fn($v) => 'Rp ' . number_format($toInt($v), 0, ',', '.');

  // ========= Ringkasan tagihan =========
  $totalTagihan = $rows->sum(fn($i) => $toInt($i->jumlah ?? $i->nominal ?? 0));
  $totalLunas   = $rows->filter(fn($i) => in_array(strtolower((string)($i->status ?? '')), ['lunas','lunas (otomatis)','terverifikasi'], true))
                       ->sum(fn($i) => $toInt($i->jumlah ?? $i->nominal ?? 0));
  $sisaTagihan  = max(0, $totalTagihan - $totalLunas);

  // ========= Status badge kecil (buat tabel) =========
  $statusBadge = function ($s) {
    $s = strtolower((string) $s);
    if (in_array($s, ['lunas','lunas (otomatis)','terverifikasi'], true)) return 'bg-success';
    if ($s === 'menunggu verifikasi') return 'bg-warning text-dark';
    if ($s === 'ditolak') return 'bg-danger';
    return 'bg-secondary';
  };

  // ========= Sisa Masa Studi (Reguler = 24 bulan) =========
  $totalDurasiBulan = 24; // 4 semester

  // Semester awal dari profil (default: ganjil)
  $semAwal = strtolower((string) ($m->semester_awal ?? 'ganjil'));
  $isGenap = ($semAwal === 'genap');

  // Tahun akademik "YYYY/YYYY" → ambil tahun pertama
  $tahunAwal = (int) (preg_match('/(\d{4})/', (string)($m->tahun_akademik ?? ''), $mm) ? $mm[1] : now()->year);

  // Anchor tanggal mulai: 20 Februari utk Genap, 20 September utk Ganjil
  $startMonth = $isGenap ? 2 : 9;
  $startDate  = Carbon::create($tahunAwal, $startMonth, 20)->startOfDay();

  // Kalau anchor terlalu ke depan dan kuliah sudah berjalan, mundurkan setahun (guard)
  if ($startDate->greaterThan(now()->addMonths(1))) {
      $startDate = $startDate->copy()->subYear();
  }

  $endDate    = $startDate->copy()->addMonths($totalDurasiBulan);
  $nowClamped = now()->lessThan($endDate) ? now() : $endDate;

  // >>> INTEGER: diffInMonths (tanpa pecahan)
  $elapsedInt   = max(0, min($totalDurasiBulan, $startDate->diffInMonths($nowClamped)));
  $remainingInt = max(0, $totalDurasiBulan - $elapsedInt);
  $pct          = (int) round(($elapsedInt / max(1, $totalDurasiBulan)) * 100);

  // ✅ Alias supaya pemanggilan lama ($remaining / $elapsed) tetap jalan (dan bulat)
  $elapsed   = $elapsedInt;
  $remaining = $remainingInt;

  $labelMulai = ($isGenap ? 'Mulai Genap ' : 'Mulai Ganjil ')
              . $startDate->year . ' (' . $startDate->translatedFormat('d F Y') . ')';
  $labelKanan = 'Berjalan: ' . $elapsedInt . ' bln • Selesai: ' . $endDate->translatedFormat('d F Y');
@endphp

  {{-- Heading --}}
  <h4 class="mb-3">Selamat Datang, {{ $m->nama }}</h4>

  {{-- Salam --}}
  <div class="p-3 mb-4 bg-light border-start border-success border-5 rounded shadow-sm">
    <h5 class="mb-1">Assalamu’alaikum, {{ $m->nama }} 👋</h5>
    <p class="mb-0 text-muted">
      Selamat datang di Portal Pembayaran SKS <strong>Magister Pendidikan Agama Islam (MPAI)</strong>.
      <em class="ms-1">Anda terdaftar sebagai <strong>Mahasiswa Reguler</strong>.</em>
      Semoga hari Anda penuh keberkahan dan semangat menuntut ilmu! 📚✨
    </p>
  </div>

  {{-- ======== Sisa Masa Studi ======== --}}
  <div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-success text-white d-flex justify-content-between">
      <span><strong>Sisa Masa Studi</strong></span>
      <span class="small">{{ $labelMulai }}</span>
    </div>
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-1">
        {{-- PAKSA INTEGER DI OUTPUT --}}
        <div class="h4 mb-0">{{ (int) $remainingInt }} <small class="text-muted">bulan</small></div>
        <div class="text-muted small">Berjalan: {{ (int) $elapsedInt }} bln • Selesai: {{ $endDate->translatedFormat('d F Y') }}</div>
      </div>
      <div class="progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}">
        <div class="progress-bar bg-success" style="width: {{ $pct }}%"></div>
      </div>
      <div class="text-muted small mt-2">
        Total durasi: {{ (int) $totalDurasiBulan }} bulan (4 semester). Perhitungan dimulai dari
        {{ $isGenap ? 'Februari' : 'September' }} {{ $startDate->year }}.
      </div>
    </div>
  </div>

  {{-- ======== Ringkasan Tagihan 3 kartu ======== --}}
  <div class="row g-3 mb-3">
    <div class="col-md-4">
      <div class="card bg-light border-0 shadow-sm rounded-3">
        <div class="card-body">
          <div class="text-muted small mb-1">Total Tagihan</div>
          <div class="h4 mb-0">{{ $rupiah($totalTagihan) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-light border-0 shadow-sm rounded-3">
        <div class="card-body">
          <div class="text-muted small mb-1">Total Lunas</div>
          <div class="h4 mb-0">{{ $rupiah($totalLunas) }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-4">
      <div class="card bg-warning-subtle border-0 shadow-sm rounded-3">
        <div class="card-body">
          <div class="text-muted small mb-1">Sisa Tagihan</div>
          <div class="h4 mb-0">{{ $rupiah($sisaTagihan) }}</div>
        </div>
      </div>
    </div>
  </div>

  {{-- ======== Notifikasi ======== --}}
  @foreach (($m->notifications ?? collect())->where('dibaca', false) as $notif)
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
      <strong>{{ $notif->judul }}</strong><br>{{ $notif->pesan }}
      <form method="POST" action="{{ route('mahasiswa_reguler.notifikasi.baca', $notif->id) }}" class="d-inline">
        @csrf
        <button type="submit" class="btn btn-sm btn-secondary ms-2">Tandai dibaca</button>
      </form>
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endforeach

  {{-- ======== Flash ======== --}}
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

  {{-- ======== Tabel Ringkasan ======== --}}
  <div class="card border-success mb-4 shadow-sm">
    <div class="card-header bg-success text-white">
      <strong>Ringkasan Tagihan SKS</strong>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
        <table class="table table-bordered invoice-table mb-0 align-middle">
          <thead class="table-success">
            <tr>
              <th style="width:60px">No</th>
              <th>Bulan</th>
              <th style="width:180px">Jumlah</th>
              <th style="width:160px">Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $i => $inv)
              @php $amt = $inv->jumlah ?? $inv->nominal ?? 0; @endphp
              <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $inv->bulan }}</td>
                <td>{{ $rupiah($amt) }}</td>
                <td>
                  @php $s = $inv->status ?? 'Belum'; @endphp
                  <span class="badge {{ $statusBadge($s) }}">{{ $s }}</span>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="4" class="text-center text-muted">Belum ada tagihan.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="text-end p-3">
        <a href="{{ route('reguler.invoice.index') }}" class="btn btn-outline-success">
          Lihat Detail Tagihan
        </a>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
{{-- Tempat script tambahan jika perlu --}}
@endpush
