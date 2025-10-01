{{-- resources/views/admin/mahasiswa/tagihan.blade.php --}}
@extends('layouts.admin')
@section('title','Tagihan Mahasiswa RPL')

@section('content')
  <h4 class="mb-3">Tagihan: {{ $mahasiswa->nama }} ({{ $mahasiswa->nim }})</h4>

  {{-- Flash message --}}
  @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show">
      {{ session('success') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show">
      {{ session('error') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif
  @if(session('info'))
    <div class="alert alert-info alert-dismissible fade show">
      {{ session('info') }}
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
  @endif

  {{-- Kartu Ubah Total Tagihan --}}
  <div class="card mb-3">
    <div class="card-body d-flex flex-wrap align-items-end gap-3">
      <div>
        <div class="text-muted small">Total Tagihan Saat Ini</div>
        <div class="h4 mb-0">Rp{{ number_format((float)($mahasiswa->total_tagihan ?? 0), 0, ',', '.') }}</div>
      </div>

      <form class="ms-auto d-flex align-items-end gap-2"
            action="{{ route('admin.mahasiswa.update-total-tagihan', $mahasiswa->id) }}"
            method="POST">
        @csrf @method('PATCH')
        <div>
          <label class="form-label mb-1">Ubah Total (Rp)</label>
          <input type="number" name="total_tagihan" class="form-control" min="0" step="1000"
                 value="{{ old('total_tagihan', (int)($mahasiswa->total_tagihan ?? 0)) }}">
        </div>
        <button class="btn btn-primary"><i class="bi bi-save"></i> Simpan</button>
      </form>
    </div>
  </div>

  @php
    // Tanggal upload aman (Carbon)
    $tglUpload = $mahasiswa->tanggal_upload ?: $mahasiswa->created_at;
    if ($tglUpload && !($tglUpload instanceof \Carbon\Carbon)) {
        $tglUpload = \Carbon\Carbon::parse($tglUpload);
    }

    // Ambil & sort invoice RPL
    $rows = ($mahasiswa->invoices ?? collect());
    if ($rows instanceof \Illuminate\Support\Collection) {
        $first = $rows->first();
        if ($first && isset($first->angsuran_ke)) {
            $rows = $rows->sortBy('angsuran_ke')->values();
        } else {
            $rows = $rows->sortBy('created_at')->values();
        }
    }
  @endphp

  {{-- Info singkat --}}
  <div class="card mb-3">
    <div class="card-body">
      <p><strong>Semester Awal:</strong> {{ ucfirst($mahasiswa->semester_awal) }} / {{ $mahasiswa->tahun_akademik }}</p>
      <p><strong>Tanggal Upload:</strong> {{ optional($tglUpload)->format('d M Y') ?? '-' }}</p>
    </div>
  </div>

  {{-- ✅ Banner Kelulusan (global partial, aksi hanya untuk admin) --}}
  @include('partials.kelulusan-banner', [
    'entity'      => $mahasiswa,
    'type'        => 'rpl',
    'showActions' => true,
    'lulusRoute'  => route('admin.kelulusan.rpl.lulus', $mahasiswa->id),
    'tolakRoute'  => route('admin.kelulusan.rpl.tolak', $mahasiswa->id),       // fallback POST
    'extendRoute' => route('admin.perpanjangan.rpl.form', $mahasiswa->id),     // utama (GET)
  ])

  {{-- Tombol akses cepat Perpanjangan (opsional) --}}
  <a class="btn btn-sm btn-outline-dark mb-3"
     href="{{ route('admin.perpanjangan.rpl.form', $mahasiswa->id) }}">
    ➕ Tambah Semester (Perpanjangan)
  </a>

  {{-- Tabel invoice milik mahasiswa ini --}}
  <div class="table-responsive">
    <table class="table table-hover table-bordered align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Bulan</th>
          <th>Jumlah</th>
          <th>Status</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($rows as $i => $inv)
          @php $status = strtolower((string)($inv->status ?? '')); @endphp
          <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $inv->bulan }}</td>
            <td>Rp{{ number_format($inv->jumlah ?? $inv->nominal ?? 0, 0, ',', '.') }}</td>
            <td>
              <span class="badge
                @if(in_array($status, ['lunas','lunas (otomatis)','terverifikasi'])) bg-success
                @elseif(in_array($status, ['belum','pending','menunggu','menunggu verifikasi'])) bg-warning text-dark
                @else bg-secondary @endif">
                {{ $inv->status ?? '-' }}
              </span>
            </td>
            <td>
              @if(!empty($inv->id))
                <a href="{{ route('admin.invoices.show', $inv->id) }}" class="btn btn-sm btn-outline-primary">Detail</a>
              @endif
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center text-muted">Belum ada invoice.</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
