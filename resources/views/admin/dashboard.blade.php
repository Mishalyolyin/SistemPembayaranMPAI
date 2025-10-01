{{-- resources/views/admin/dashboard.blade.php --}}
@extends('layouts.admin')

@section('title', 'Dashboard Admin')

@section('content')
@include('partials.semester')
@include('partials.kalender')

    <!-- Statistik Mahasiswa RPL -->
    <h5 class="mb-3">📘 Mahasiswa RPL</h5>
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Mahasiswa Aktif</h6>
                    <h3>{{ $jumlahMahasiswaRPL }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Total Tagihan</h6>
                    <h4>Rp{{ number_format($totalTagihanRPL, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Sudah Lunas</h6>
                    <h3>{{ $jumlahLunasRPL }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Menunggu Verifikasi</h6>
                    <h3>{{ $menungguVerifikasiRPL }}</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistik Mahasiswa Reguler -->
    <h5 class="mb-3">📗 Mahasiswa Reguler</h5>
    <div class="row g-3 mb-5">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Mahasiswa Aktif</h6>
                    <h3>{{ $jumlahMahasiswaReguler }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Total Tagihan</h6>
                    <h4>Rp{{ number_format($totalTagihanReguler, 0, ',', '.') }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Sudah Lunas</h6>
                    <h3>{{ $jumlahLunasReguler }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h6>Menunggu Verifikasi</h6>
                    <h3>{{ $menungguVerifikasiReguler }}</h3>
                </div>
            </div>
        </div>
    </div>

    <hr class="my-5">

    <h5>📋 Lihat Tagihan Mahasiswa RPL</h5>
    @foreach ($mahasiswaRPL as $mhs)
        @php
            // Koleksi invoice aman (null-safe)
            $invCol = collect(data_get($mhs, 'invoices', []));
            $semuaLunas = $invCol->isNotEmpty() && $invCol->every(function ($inv) {
                $status = strtolower(trim($inv->status ?? ''));
                return in_array($status, ['lunas', 'lunas (otomatis)']);
            });
        @endphp

        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $mhs->nama }}</strong><br>
                    NIM: {{ $mhs->nim }}<br>
                    <span class="badge {{ $semuaLunas ? 'bg-success' : 'bg-warning text-dark' }}">
                      Status Pembayaran: {{ $semuaLunas ? 'LUNAS' : 'BELUM LUNAS' }}
                    </span>
                </div>
                <button
                  class="btn btn-outline-dark btn-sm"
                  onclick="toggleDetail({{ $mhs->id }})">
                  Lihat Tagihan
                </button>
            </div>

            <div id="detail-{{ $mhs->id }}" class="px-3 pb-3" style="display:none;">
                @forelse ($invCol as $inv)
                    <div class="mt-2 border rounded p-2">
                        <strong>{{ $inv->bulan }}</strong> –
                        Rp{{ number_format($inv->jumlah ?? 0, 0, ',', '.') }} –
                        @php
                            $st = strtolower(trim($inv->status ?? ''));
                            $badge = in_array($st, ['lunas','lunas (otomatis)']) ? 'bg-success'
                                   : ($st === 'belum' ? 'bg-warning text-dark'
                                   : ($st === 'ditolak' ? 'bg-danger' : 'bg-secondary'));
                        @endphp
                        <span class="badge {{ $badge }}">{{ $inv->status ?? '-' }}</span>
                        <br>
                        @if (!empty($inv->bukti))
                            <a href="{{ asset('storage/bukti/' . $inv->bukti) }}" target="_blank">
                                <img src="{{ asset('storage/bukti/' . $inv->bukti) }}" alt="Bukti" width="100">
                            </a>
                        @else
                            <span class="text-muted">Belum upload</span>
                        @endif
                    </div>
                @empty
                    <div class="mt-2 text-muted">Belum ada invoice.</div>
                @endforelse
            </div>
        </div>
    @endforeach

    <hr class="my-5">

    <h5>🏫 Lihat Tagihan Mahasiswa Reguler</h5>
    @foreach ($mahasiswaReguler as $reg)
        @php
            // Koleksi invoice reguler aman (null-safe). Jika relasinya beda nama, tinggal tambah di data_get.
            $invColReg = collect(data_get($reg, 'invoices', data_get($reg, 'invoicesReguler', [])));
            $semuaLunasReg = $invColReg->isNotEmpty() && $invColReg->every(function ($inv) {
                $status = strtolower(trim($inv->status ?? ''));
                return in_array($status, ['lunas', 'lunas (otomatis)']);
            });
        @endphp

        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <strong>{{ $reg->nama }}</strong><br>
                    NIM: {{ $reg->nim }}<br>
                    <span class="badge {{ $semuaLunasReg ? 'bg-success' : 'bg-warning text-dark' }}">
                      Status Pembayaran: {{ $semuaLunasReg ? 'LUNAS' : 'BELUM LUNAS' }}
                    </span>
                </div>
                <button
                  class="btn btn-outline-dark btn-sm"
                  onclick="toggleDetail('reg-{{ $reg->id }}')">
                  Lihat Tagihan
                </button>
            </div>

            <div id="detail-reg-{{ $reg->id }}" class="px-3 pb-3" style="display:none;">
                @forelse ($invColReg as $inv)
                    <div class="mt-2 border rounded p-2">
                        <strong>{{ $inv->bulan }}</strong> –
                        Rp{{ number_format($inv->jumlah ?? 0, 0, ',', '.') }} –
                        @php
                            $st = strtolower(trim($inv->status ?? ''));
                            $badge = in_array($st, ['lunas','lunas (otomatis)']) ? 'bg-success'
                                   : ($st === 'belum' ? 'bg-warning text-dark'
                                   : ($st === 'ditolak' ? 'bg-danger' : 'bg-secondary'));
                        @endphp
                        <span class="badge {{ $badge }}">{{ $inv->status ?? '-' }}</span>
                        <br>
                        @if (!empty($inv->bukti))
                            <a href="{{ asset('storage/bukti_reguler/' . $inv->bukti) }}" target="_blank">
                                <img src="{{ asset('storage/bukti_reguler/' . $inv->bukti) }}" alt="Bukti" width="100">
                            </a>
                        @else
                            <span class="text-muted">Belum upload</span>
                        @endif
                    </div>
                @empty
                    <div class="mt-2 text-muted">Belum ada invoice.</div>
                @endforelse
            </div>
        </div>
    @endforeach
@endsection

@push('scripts')
<script>
    function toggleDetail(id) {
        const el = document.getElementById('detail-' + id);
        if (el) {
            el.style.display = (el.style.display === 'none' || el.style.display === '') ? 'block' : 'none';
        }
    }
</script>
@endpush
