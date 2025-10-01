@extends('layouts.admin')
@section('title','Verifikasi Tagihan (Reguler)')

@section('content')
<h4 class="mb-3">Verifikasi Tagihan (Reguler)</h4>

@php
  $status    = $status  ?? request('status','semua');
  $search    = $search  ?? request('search','');
  $sem       = $sem     ?? request('semester');
  $ta        = $ta      ?? request('tahun_akademik');
  $layout    = $layout  ?? request('layout','group');
  $perPage   = (int) ($perPage ?? request('per_page', 15));
  $fmtRupiah = fn($v) => 'Rp'.number_format((int)preg_replace('/\D+/', '', (string)($v ?? 0)),0,',','.');
  $isGroup   = ($layout === 'group');

  // kelompok status badge
  $paidStates     = ['lunas','lunas (otomatis)','paid','terverifikasi'];
  $rejectedStates = ['ditolak','reject','gagal','batal','invalid'];
@endphp

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div> @endif
@if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif

<form method="GET" class="row g-2 mb-3 align-items-end">
  <input type="hidden" name="layout" value="{{ $layout }}"/>

  <div class="col-md-4">
    <label class="form-label">Cari</label>
    <input name="search" class="form-control" placeholder="Nama/NIM/Bulan/Keterangan/TA" value="{{ $search }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <option value="semua"   @selected($status==='semua')>Semua</option>
      <option value="pending" @selected($status==='pending')>Pending</option>
      <option value="lunas"   @selected($status==='lunas')>Lunas</option>
      <option value="ditolak" @selected($status==='ditolak')>Ditolak</option>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label">Semester</label>
    <select name="semester" class="form-select">
      <option value="">-- Semua --</option>
      <option value="ganjil" @selected(($sem ?? '')==='ganjil')>Ganjil</option>
      <option value="genap"  @selected(($sem ?? '')==='genap')>Genap</option>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label">Tahun Akademik</label>
    <input name="tahun_akademik" class="form-control" placeholder="2025/2026" value="{{ $ta }}">
  </div>

  <div class="col-md-1">
    <label class="form-label d-none d-md-block">&nbsp;</label>
    <button class="btn btn-primary w-100">Filter</button>
  </div>

  <div class="col-12"></div>

  <div class="col-md-4 d-flex align-items-end gap-2">
    <div class="d-flex flex-wrap gap-2">
      <a href="{{ request()->fullUrlWithQuery(['layout'=>'group','page'=>1]) }}"
         class="btn btn-sm {{ $isGroup ? 'btn-success' : 'btn-outline-secondary' }}">
        Group (per mahasiswa)
      </a>
      <a href="{{ request()->fullUrlWithQuery(['layout'=>'flat','page'=>1]) }}"
         class="btn btn-sm {{ !$isGroup ? 'btn-success' : 'btn-outline-secondary' }}">
        Flat (semua invoice)
      </a>
    </div>
  </div>

  <div class="col-md-3">
    <label class="form-label">Tampilkan per halaman</label>
    <select name="per_page" class="form-select" onchange="this.form.submit()">
      @for($pp=10; $pp<=1000; $pp+=10)
        <option value="{{ $pp }}" @selected($perPage==$pp)>{{ $pp }}</option>
      @endfor
    </select>
  </div>
</form>

<div class="mb-3 d-flex gap-2 flex-wrap">
  @if(Route::has('admin.exports.invoices.reguler'))
    <a href="{{ route('admin.exports.invoices.reguler', ['mode' => 'all']) }}" class="btn btn-outline-success">
      Export Semua Mahasiswa Lunas (Reguler)
    </a>
  @endif
</div>

<style>
  .student-toggle{cursor:pointer;text-decoration:none}
  .student-toggle .chev{transition:transform .18s ease}
  .student-toggle[aria-expanded="true"] .chev{transform:rotate(90deg)}
  /* kecilin efek ghost */
  .btn.opacity-50.disabled, .btn.opacity-50:disabled { filter: grayscale(30%); }
</style>

{{-- =================== MODE GROUP =================== --}}
@if(isset($students))
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:60px">#</th>
          <th>Mahasiswa</th>
          <th class="text-center">Ringkasan</th>
          <th class="text-end" style="width:260px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($students as $idx => $m)
          @php
            $sum = $summary[$m->id] ?? ['count'=>0,'total'=>0,'pending'=>false];
            $collapseId = 'mhs-'.$m->id;
          @endphp

          <tr class="{{ $sum['pending'] ? 'table-warning' : '' }}">
            <td class="text-muted">{{ ($students->firstItem() ?? 1) + $idx }}</td>
            <td>
              <a class="student-toggle d-inline-flex align-items-center gap-2" data-bs-toggle="collapse"
                 data-bs-target="#{{ $collapseId }}" role="button" aria-expanded="false" aria-controls="{{ $collapseId }}">
                <span class="chev">▶</span>
                <span>
                  <span class="fw-semibold">{{ $m->nama }}</span>
                  <div class="small text-muted">{{ $m->nim }}</div>
                </span>
              </a>
              @if($sum['pending'])
                <span class="badge bg-warning text-dark ms-2">Menunggu Verifikasi</span>
              @endif
            </td>
            <td class="text-center">
              <div class="d-inline-flex gap-3 align-items-center">
                <span class="badge bg-secondary">Total Angsuran: {{ $sum['count'] }}</span>
                <span class="badge bg-info">Total Tagihan: {{ $fmtRupiah($sum['total']) }}</span>
              </div>
            </td>
            <td class="text-end">
              @if(Route::has('admin.mahasiswa-reguler.reset-angsuran'))
                <form action="{{ route('admin.mahasiswa-reguler.reset-angsuran',$m->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Reset angsuran mahasiswa ini? Semua invoice pending akan dihapus. Lanjut?')">
                  @csrf
                  <button class="btn btn-outline-warning btn-sm">Reset Angsuran</button>
                </form>
              @endif
              @if(Route::has('admin.exports.invoices.reguler') && !empty($m->nim))
                <a href="{{ route('admin.exports.invoices.reguler', ['mode' => 'single', 'nim' => $m->nim]) }}"
                   class="btn btn-outline-primary btn-sm">Export Mahasiswa Ini</a>
              @endif
            </td>
          </tr>

          <tr>
            <td colspan="4" class="p-0">
              <div id="{{ $collapseId }}" class="collapse">
                <div class="p-3 border-top">
                  <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                      <thead>
                        <tr class="small text-muted">
                          <th style="width:50px">#</th>
                          <th>Semester / TA</th>
                          <th>Bulan</th>
                          <th class="text-end">Nominal</th>
                          <th>Status</th>
                          <th class="text-end" style="width:360px">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($m->invoicesReguler as $k => $inv)
                          @php
                            $semLbl = $inv->semester ?: ($m->semester_awal ?? '—');
                            $taLbl  = $inv->tahun_akademik ?: ($m->tahun_akademik ?? '—');
                            $st     = mb_strtolower(trim($inv->status ?? ''));
                            $badge  = in_array($st,$paidStates) ? 'success'
                                     : (in_array($st,$rejectedStates) ? 'danger'
                                     : ($st==='menunggu verifikasi' ? 'warning text-dark' : 'secondary'));

                            $hasBukti        = !empty($inv->bukti) || !empty($inv->bukti_pembayaran);
                            $isPendingAction = ($st === 'menunggu verifikasi'); // <— kunci: cuma ini yang aktif
                          @endphp
                          <tr @class(['table-warning'=> $st==='menunggu verifikasi'])>
                            <td class="text-muted">{{ $k+1 }}</td>
                            <td>{{ ucfirst($semLbl) }} / {{ $taLbl }}</td>
                            <td>{{ $inv->bulan ?? '—' }}</td>
                            <td class="text-end">{{ $fmtRupiah($inv->nominal ?? $inv->jumlah) }}</td>
                            <td><span class="badge bg-{{ $badge }}">{{ $inv->status ?? 'Belum' }}</span></td>
                            <td class="text-end">
                              {{-- Bukti (ghost kalau kosong) --}}
                              <a class="btn btn-sm btn-light border {{ $hasBukti ? '' : 'opacity-50 disabled pe-none' }}"
                                 @if($hasBukti) href="{{ route('admin.invoices-reguler.bukti',$inv->id) }}" target="_blank" @endif>
                                Bukti
                              </a>
                              @if(Route::has('admin.invoices-reguler.bukti.download'))
                                <a class="btn btn-sm btn-light border {{ $hasBukti ? '' : 'opacity-50 disabled pe-none' }}"
                                   @if($hasBukti) href="{{ route('admin.invoices-reguler.bukti.download',$inv->id) }}" @endif>
                                  Download
                                </a>
                              @endif>

                              {{-- Detail --}}
                              @if(Route::has('admin.invoices-reguler.show'))
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.invoices-reguler.show', $inv->id) }}">Detail</a>
                              @endif

                              {{-- Verifikasi/Tolak: aktif hanya jika "menunggu verifikasi" --}}
                              <form action="{{ route('admin.invoices-reguler.verify',$inv->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success {{ $isPendingAction ? '' : 'opacity-50 disabled pe-none' }}"
                                        @if($isPendingAction) onclick="return confirm('Set LUNAS untuk invoice ini?')" @endif>
                                  Verifikasi
                                </button>
                              </form>

                              <form action="{{ route('admin.invoices-reguler.reject',$inv->id) }}" method="POST" class="d-inline"
                                    onsubmit="if(this.querySelector('button').classList.contains('disabled')){return false;}
                                              const a=prompt('Alasan penolakan?'); if(a===null){return false;} this.alasan.value=a; return true;">
                                @csrf
                                <input type="hidden" name="alasan" value="">
                                <button class="btn btn-sm btn-danger {{ $isPendingAction ? '' : 'opacity-50 disabled pe-none' }}">
                                  Tolak
                                </button>
                              </form>

                              {{-- Reset selalu ada --}}
                              <form action="{{ route('admin.invoices-reguler.reset',$inv->id) }}" method="POST" class="d-inline"
                                    onsubmit="return confirm('Reset invoice ini?{{ $hasBukti ? ' Bukti akan DIHAPUS &' : '' }} status kembali Belum.')">
                                @csrf
                                <button class="btn btn-sm btn-outline-warning">Reset Status</button>
                              </form>
                            </td>
                          </tr>
                        @empty
                          <tr><td colspan="6" class="text-center text-muted py-3">Tidak ada angsuran sesuai filter.</td></tr>
                        @endforelse
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </td>
          </tr>
        @empty
          <tr><td colspan="4" class="text-center text-muted py-4">Data tidak ditemukan.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="d-flex justify-content-between align-items-center">
    <div class="small text-muted">Menampilkan {{ $students->firstItem() }}–{{ $students->lastItem() }} dari {{ $students->total() }} mahasiswa</div>
    <div>{{ $students->onEachSide(1)->withQueryString()->links() }}</div>
  </div>

{{-- =================== MODE FLAT =================== --}}
@else
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Mahasiswa</th>
          <th>Semester / TA</th>
          <th>Bulan</th>
          <th class="text-end">Nominal</th>
          <th>Status</th>
          <th class="text-end" style="width:480px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($invoices as $i => $inv)
          @php
            $rowNo = ($invoices->firstItem() ?? 1) + $i;
            $mhs   = $inv->mahasiswaReguler ?? $inv->mahasiswa ?? null;
            $semV  = $inv->semester ?? ($mhs->semester_awal ?? null);
            $taV   = $inv->tahun_akademik ?? ($mhs->tahun_akademik ?? null);
            $st    = mb_strtolower(trim($inv->status ?? 'Belum'));
            $cls   = in_array($st,$paidStates) ? 'bg-success'
                    : (in_array($st,$rejectedStates) ? 'bg-danger'
                    : ($st==='menunggu verifikasi' ? 'bg-warning text-dark' : 'bg-secondary'));

            $hasBukti        = !empty($inv->bukti) || !empty($inv->bukti_pembayaran);
            $isPendingAction = ($st === 'menunggu verifikasi');
          @endphp
          <tr @class(['table-warning'=> $st==='menunggu verifikasi'])>
            <td>{{ $rowNo }}</td>
            <td>
              <div class="fw-semibold">{{ $mhs->nama ?? '—' }}</div>
              <div class="text-muted small">{{ $mhs->nim ?? '' }}</div>
            </td>
            <td>{{ $semV ? ucfirst($semV) : '—' }} / {{ $taV ?: '—' }}</td>
            <td>{{ $inv->bulan ?? '—' }}</td>
            <td class="text-end">{{ $fmtRupiah($inv->jumlah ?? $inv->nominal) }}</td>
            <td><span class="badge {{ $cls }}">{{ $inv->status ?? 'Belum' }}</span></td>
            <td class="text-end">
              <a class="btn btn-outline-secondary btn-sm {{ $hasBukti ? '' : 'opacity-50 disabled pe-none' }}"
                 @if($hasBukti) href="{{ route('admin.invoices-reguler.bukti',$inv->id) }}" target="_blank" @endif>
                Bukti
              </a>
              @if(Route::has('admin.invoices-reguler.bukti.download'))
                <a class="btn btn-outline-secondary btn-sm {{ $hasBukti ? '' : 'opacity-50 disabled pe-none' }}"
                   @if($hasBukti) href="{{ route('admin.invoices-reguler.bukti.download',$inv->id) }}" @endif>
                  Download
                </a>
              @endif

              @if(Route::has('admin.invoices-reguler.show'))
                <a class="btn btn-outline-dark btn-sm" href="{{ route('admin.invoices-reguler.show', $inv->id) }}">Detail</a>
              @endif

              <form action="{{ route('admin.invoices-reguler.verify',$inv->id) }}" method="POST" class="d-inline">
                @csrf
                <button class="btn btn-success btn-sm {{ $isPendingAction ? '' : 'opacity-50 disabled pe-none' }}"
                        @if($isPendingAction) onclick="return confirm('Set LUNAS untuk invoice ini?')" @endif>
                  Verifikasi
                </button>
              </form>

              <form action="{{ route('admin.invoices-reguler.reject',$inv->id) }}" method="POST" class="d-inline"
                    onsubmit="if(this.querySelector('button').classList.contains('disabled')){return false;}
                              const a=prompt('Alasan penolakan?'); if(a===null){return false;} this.alasan.value=a; return true;">
                @csrf
                <input type="hidden" name="alasan" value="">
                <button class="btn btn-danger btn-sm {{ $isPendingAction ? '' : 'opacity-50 disabled pe-none' }}">
                  Tolak
                </button>
              </form>

              <form action="{{ route('admin.invoices-reguler.reset',$inv->id) }}" method="POST" class="d-inline"
                    onsubmit="return confirm('Reset invoice ini?{{ $hasBukti ? ' Bukti akan DIHAPUS &' : '' }} status kembali Belum.')">
                @csrf
                <button class="btn btn-warning btn-sm">Reset</button>
              </form>

              @if(($mhs ?? null) && Route::has('admin.mahasiswa-reguler.reset-angsuran'))
                <form action="{{ route('admin.mahasiswa-reguler.reset-angsuran',$mhs->id) }}" method="POST" class="d-inline"
                      onsubmit="return confirm('Reset ANGSURAN mahasiswa ini? Semua invoice pending akan dihapus. Lanjut?')">
                  @csrf
                  <button class="btn btn-outline-warning btn-sm">Reset Angsuran</button>
                </form>
              @endif

              @if(Route::has('admin.exports.invoices.reguler') && !empty($mhs?->nim))
                <a class="btn btn-outline-primary btn-sm"
                   href="{{ route('admin.exports.invoices.reguler', ['mode' => 'single', 'nim' => $mhs->nim]) }}">
                  Export Mahasiswa Ini
                </a>
              @endif
            </td>
          </tr>
        @empty
          <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  <div class="mt-3">
    {{ $invoices->withQueryString()->links() }}
  </div>
@endif
@endsection
