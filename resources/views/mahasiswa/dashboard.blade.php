{{-- resources/views/mahasiswa/dashboard.blade.php --}}

@extends('layouts.mahasiswa')
@section('title', 'Dashboard Mahasiswa')

@section('content')
  <h4 class="mb-3">Selamat Datang, {{ $mahasiswa->nama }}</h4>

  {{-- Salam --}}
  <div class="p-3 mb-4 bg-light border-start border-success border-5 rounded shadow-sm">
    <h5 class="mb-1">Assalamu’alaikum, {{ $mahasiswa->nama }} 👋</h5>
    <p class="mb-0 text-muted">
      Selamat datang di Portal Pembayaran SKS <strong>Magister Pendidikan Agama Islam (MPAI)</strong>.
      <em class="ms-1">Anda terdaftar sebagai <strong>Mahasiswa RPL</strong>.</em>
      Semoga hari Anda penuh keberkahan dan semangat menuntut ilmu! 📚✨
    </p>
  </div>

  @php
    // ====== Angka BULAT dari controller $masa ======
    $totalBulan   = (int) $masa['total_bulan'];
    $elapsedInt   = (int) $masa['elapsed_bulan'];
    $sisaInt      = (int) $masa['sisa_bulan'];
    $progressInt  = (int) $masa['progress_pct'];
    $semesterCnt  = intdiv($totalBulan, 6);

    // ====== Label tanggal langsung dari Carbon (AMAN) ======
    $mulaiDate    = $masa['mulai_date']->locale('id');
    $selesaiDate  = $masa['selesai_date']->locale('id');

    // Contoh: 20 September 2024
    $mulaiBadge   = $mulaiDate->isoFormat('D MMMM YYYY');
    $selesaiLabel = $selesaiDate->isoFormat('D MMMM YYYY');

    // Header contoh: "Mulai Ganjil 2024 (20 September 2024)"
    $semHeader    = ucfirst((string)($mahasiswa->semester_awal ?? '-')) . ' ' .
                    $mulaiDate->year . ' (' . $mulaiBadge . ')';

    // Kalimat bawah contoh: "September 2024"
    $mulaiBulanTahun = $mulaiDate->isoFormat('MMMM YYYY');
  @endphp

  {{-- ===== Sisa Masa Studi ===== --}}
  <div class="row mb-4 g-3">
    <div class="col-12">
      <div class="card border-success">
        <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
          <strong>Sisa Masa Studi</strong>
          <span class="small">Mulai {{ $semHeader }}</span>
        </div>
        <div class="card-body">
          <div class="d-flex justify-content-between align-items-baseline mb-2">
            <div class="fs-2 fw-bold">
              {{ $sisaInt }} <span class="fs-6 fw-normal">bulan</span>
            </div>
            <div class="text-muted small">
              Berjalan: {{ $elapsedInt }} bln &nbsp;•&nbsp; Selesai: {{ $selesaiLabel }}
            </div>
          </div>

          <div class="progress" role="progressbar" aria-valuenow="{{ $progressInt }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width: {{ $progressInt }}%"></div>
          </div>

          <div class="small text-muted mt-2">
            Total durasi: {{ $totalBulan }} bulan ({{ $semesterCnt }} semester).
            Perhitungan dimulai dari {{ $mulaiBulanTahun }}.
          </div>
        </div>
      </div>
    </div>
  </div>

  {{-- ===== Ringkasan Tagihan SKS ===== --}}
  <div class="card border-success mb-4">
    <div class="card-header bg-success text-white">
      <strong>Ringkasan Tagihan SKS</strong>
    </div>
    <div class="card-body">
      {{-- Notifikasi belum dibaca --}}
      @foreach (($mahasiswa->notifications ?? collect())->where('dibaca', false) as $notif)
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
          <strong>{{ $notif->judul }}</strong><br>{{ $notif->pesan }}
          <form method="POST" action="{{ route('mahasiswa.notifikasi.baca', $notif->id) }}" class="d-inline">
            @csrf
            <button type="submit" class="btn btn-sm btn-secondary ms-2">Tandai dibaca</button>
          </form>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endforeach

      {{-- Flash message --}}
      @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show">{{ session('success') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @elseif (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      @endif

      @php
        $statusLunas  = ['lunas','lunas (otomatis)','terverifikasi','paid'];
        $totalTagihan = (int) (($invoices ?? collect())->sum('jumlah'));
        $totalLunas   = (int) (($invoices ?? collect())
                          ->filter(fn($i) => in_array(strtolower(trim((string)$i->status)), $statusLunas, true))
                          ->sum('jumlah'));
        $sisaTagihan  = max(0, $totalTagihan - $totalLunas);
      @endphp

      <div class="row mb-3">
        <div class="col-md-4">
          <div class="alert alert-success mb-0">
            <strong>Total Tagihan</strong><br>
            Rp {{ number_format($totalTagihan, 0, ',', '.') }}
          </div>
        </div>
        <div class="col-md-4">
          <div class="alert alert-primary mb-0">
            <strong>Total Lunas</strong><br>
            Rp {{ number_format($totalLunas, 0, ',', '.') }}
          </div>
        </div>
        <div class="col-md-4">
          <div class="alert alert-warning mb-0">
            <strong>Sisa Tagihan</strong><br>
            Rp {{ number_format($sisaTagihan, 0, ',', '.') }}
          </div>
        </div>
      </div>

      {{-- Tabel invoice --}}
      <div class="table-responsive">
        <table class="table table-bordered invoice-table mb-0">
          <thead>
            <tr>
              <th>No</th>
              <th>Bulan</th>
              <th>Jumlah</th>
              <th>Status</th>
            </tr>
          </thead>
          <tbody>
            @forelse (($invoices ?? collect()) as $i => $inv)
              <tr>
                <td>{{ (int)$i + 1 }}</td>
                <td>{{ $inv->bulan }}</td>
                <td>Rp {{ number_format((int)$inv->jumlah, 0, ',', '.') }}</td>
                <td>
                  @php
                    $label = trim((string)($inv->status ?? 'Belum'));
                    $s     = strtolower($label);
                    $badge = match (true) {
                      in_array($s, ['lunas','lunas (otomatis)','terverifikasi','paid','verified'], true) => 'success',
                      in_array($s, ['ditolak','reject','gagal','batal','invalid'], true)                  => 'danger',
                      in_array($s, ['menunggu verifikasi','pending','menunggu','belum diverifikasi','belum','unpaid'], true) => 'secondary',
                      default => 'secondary',
                    };
                  @endphp
                  <span class="badge bg-{{ $badge }}">{{ $label }}</span>
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

      <div class="text-end mt-3">
        <a href="{{ route('mahasiswa.invoice.index') }}" class="btn btn-outline-success">
          Lihat Detail Tagihan
        </a>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
{{-- No extra JS needed --}}
@endpush
