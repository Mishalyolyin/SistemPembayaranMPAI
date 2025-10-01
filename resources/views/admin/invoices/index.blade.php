{{-- resources/views/admin/invoices/index.blade.php --}}
@extends('layouts.admin')
@section('title','Verifikasi Tagihan (RPL)')

@section('content')
<h4 class="mb-3">Verifikasi Tagihan (RPL)</h4>

@php
  // state dari controller / query
  $status = $status ?? request('status','semua');
  $search = $search ?? request('search','');
  $sem    = $sem    ?? request('semester');
  $ta     = $ta     ?? request('tahun_akademik');
  $layout = $layout ?? request('layout','group');
  $per    = $per_page ?? (int) request('per_page', 15);

  // helper
  $fmtRupiah = fn($v) => 'Rp'.number_format((int)preg_replace('/\D+/','',(string)($v ?? 0)),0,',','.');
@endphp

<form method="GET" class="row g-2 mb-3 align-items-end">
  {{-- keep layout when filtering --}}
  <input type="hidden" name="layout" value="{{ $layout }}"/>

  <div class="col-md-4">
    <label class="form-label">Cari</label>
    <input name="search" class="form-control"
           placeholder="Cari nama/NIM/bulan/keterangan"
           value="{{ $search }}">
  </div>

  <div class="col-md-3">
    <label class="form-label">Status</label>
    <select name="status" class="form-select">
      <option value="pending" @selected($status==='pending')>Menunggu</option>
      <option value="lunas"   @selected($status==='lunas')>Lunas</option>
      <option value="ditolak" @selected($status==='ditolak')>Ditolak</option>
      <option value="semua"   @selected($status==='semua')>Semua</option>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label">Semester</label>
    <select name="semester" class="form-select">
      <option value="">-- Semua --</option>
      <option value="ganjil" @selected($sem==='ganjil')>Ganjil</option>
      <option value="genap"  @selected($sem==='genap')>Genap</option>
    </select>
  </div>

  <div class="col-md-2">
    <label class="form-label">Tahun Akademik</label>
    <input name="tahun_akademik" class="form-control" placeholder="2025/2026"
           value="{{ $ta }}">
  </div>

  <div class="col-md-1">
    <label class="form-label">Tampil</label>
    <select name="per_page" class="form-select" onchange="this.form.submit()">
      @for($i=10;$i<=1000;$i+=10)
        <option value="{{ $i }}" @selected($per==$i)>{{ $i }}</option>
      @endfor
    </select>
  </div>

  <div class="col-12 col-md-auto">
    <label class="form-label d-none d-md-block">&nbsp;</label>
    <button class="btn btn-primary w-100">Filter</button>
  </div>
</form>

{{-- Toolbar aksi global --}}
<div class="mb-3 d-flex gap-2 flex-wrap">
  {{-- Export semua mahasiswa yang sudah LUNAS PENUH (RPL) --}}
  @if(Route::has('admin.exports.invoices.rpl'))
    <a href="{{ route('admin.exports.invoices.rpl', ['mode' => 'all']) }}"
       class="btn btn-outline-success">
      Export Semua Mahasiswa Lunas (RPL)
    </a>
  @endif
</div>

{{-- Toggle tampilan --}}
@php $isGroup = ($layout ?? 'group') === 'group'; @endphp
<div class="mb-2 d-flex gap-2">
  <a class="btn btn-sm {{ $isGroup ? 'btn-success' : 'btn-outline-secondary' }}"
     href="{{ request()->fullUrlWithQuery(['layout'=>'group','page'=>1]) }}">
     Tampilan Group
  </a>
  <a class="btn btn-sm {{ !$isGroup ? 'btn-success' : 'btn-outline-secondary' }}"
     href="{{ request()->fullUrlWithQuery(['layout'=>'flat','page'=>1]) }}">
     Tampilan Flat
  </a>
</div>

<style>
  .student-toggle{cursor:pointer;text-decoration:none}
  .student-toggle .chev{transition:transform .25s cubic-bezier(.4,0,.2,1)}
  .student-toggle[aria-expanded="true"] .chev{transform:rotate(90deg)}

  /* Smoothen collapse inside table by fading + height transition */
  .collapse-row {transition: height .28s cubic-bezier(.4,0,.2,1), opacity .2s ease;}
  .collapse-row.collapsing {opacity:.6}
  .collapse-row:not(.show){opacity:0}
  .collapse-row.show{opacity:1}
</style>

{{-- =================== MODE GROUP (1 baris per mahasiswa + dropdown) =================== --}}
@if(isset($students))
  <div class="table-responsive">
    <table class="table table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th style="width:60px">#</th>
          <th>Mahasiswa</th>
          <th class="text-center">Ringkasan</th>
          <th class="text-end" style="width:240px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($students as $idx => $m)
          @php
            $rows =
              collect($m->invoices ?? null ?: [])
              ->when(empty($m->invoices ?? null) && isset($m->invoices_rpl), fn($c)=>collect($m->invoices_rpl))
              ->when(empty($m->invoices ?? null) && method_exists($m,'invoicesReguler') && isset($m->invoicesReguler), fn($c)=>collect($m->invoicesReguler));

            $sum = $summary[$m->id] ?? [
              'count'   => $rows->count(),
              'total'   => $rows->sum(fn($i)=>(int)preg_replace('/\D+/','',(string)($i->nominal ?? $i->jumlah ?? 0))),
              'pending' => $rows->contains(fn($i)=>mb_strtolower($i->status ?? '')==='menunggu verifikasi'),
            ];
            $collapseId = 'mhs-'.$m->id;
          @endphp

          {{-- Baris utama (klik NAMA untuk expand) --}}
          <tr class="{{ $sum['pending'] ? 'table-warning' : '' }}">
            <td class="text-muted">{{ ($students->firstItem() ?? 1) + $idx }}</td>
            <td>
              <a class="student-toggle d-inline-flex align-items-center gap-2"
                 data-bs-toggle="collapse"
                 data-bs-target="#{{ $collapseId }}"
                 role="button"
                 aria-expanded="false"
                 aria-controls="{{ $collapseId }}">
                <span class="chev">▶</span>
                <span>
                  <span class="fw-semibold">{{ $m->nama }}</span>
                  <div class="small text-muted">{{ $m->nim }}</div>
                </span>
              </a>
              @if($sum['pending'])
                <span class="badge bg-warning text-dark ms-2 align-middle">Menunggu Verifikasi</span>
              @endif
            </td>
            <td class="text-center">
              <div class="d-inline-flex gap-3 align-items-center">
                <span class="badge bg-secondary">Total Angsuran: {{ $sum['count'] }}</span>
                <span class="badge bg-info">Total Tagihan: {{ $fmtRupiah($sum['total']) }}</span>
              </div>
            </td>
            <td class="text-end">
              @isset($m->id)
                @if(Route::has('admin.mahasiswa.reset-angsuran'))
                  <form action="{{ route('admin.mahasiswa.reset-angsuran', $m->id) }}" method="POST" class="d-inline"
                        onsubmit="return confirm('Reset pilihan angsuran mahasiswa ini?')">
                    @csrf
                    <button class="btn btn-outline-warning btn-sm">Reset Angsuran</button>
                  </form>
                @endif
              @endisset

              {{-- ===== Export Mahasiswa Ini (RPL) ===== --}}
              @if(Route::has('admin.exports.invoices.rpl') && !empty($m->nim))
                <a href="{{ route('admin.exports.invoices.rpl', ['mode' => 'single', 'nim' => $m->nim]) }}"
                   class="btn btn-outline-primary btn-sm">
                  Export Mahasiswa Ini
                </a>
              @endif
            </td>
          </tr>

          {{-- Dropdown detail angsuran --}}
          <tr>
            <td colspan="4" class="p-0">
              <div id="{{ $collapseId }}" class="collapse collapse-row">
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
                          <th class="text-end" style="width:320px">Aksi</th>
                        </tr>
                      </thead>
                      <tbody>
                        @forelse($rows as $k => $inv)
                          @php
                            $semLbl = $inv->semester ?? ($m->semester_awal ?? '—');
                            $taLbl  = $inv->tahun_akademik ?? ($m->tahun_akademik ?? '—');
                            $st = strtolower(trim($inv->status ?? ''));
                            $badge = match (true) {
                              in_array($st, ['lunas','lunas (otomatis)','terverifikasi','paid']) => 'success',
                              in_array($st, ['ditolak','reject','gagal','batal','invalid'])     => 'danger',
                              in_array($st, ['menunggu verifikasi','pending','belum','belum lunas','menunggu','unpaid']) => 'warning text-dark',
                              default => 'secondary',
                            };

                            $hasBukti  = !empty($inv->bukti ?? null);
                            $publicUrl = null;
                            if ($hasBukti) {
                              $path = ltrim($inv->bukti,'/');
                              $publicUrl = (str_contains($path,'/')) ? asset('storage/'.$path)
                                           : (asset('storage/bukti/'.$path));
                            }
                          @endphp
                          <tr @class(['table-warning'=> $st==='menunggu verifikasi'])>
                            <td class="text-muted">{{ $k+1 }}</td>
                            <td>{{ ucfirst($semLbl) }} / {{ $taLbl }}</td>
                            <td>{{ $inv->bulan ?? '—' }}</td>
                            <td class="text-end">{{ $fmtRupiah($inv->nominal ?? $inv->jumlah) }}</td>
                            <td><span class="badge bg-{{ $badge }}">{{ $inv->status ?? 'Belum' }}</span></td>
                            <td class="text-end">
                              @if($hasBukti && $publicUrl)
                                <a class="btn btn-sm btn-light border" href="{{ $publicUrl }}" target="_blank">Bukti</a>
                              @else
                                <button class="btn btn-sm btn-light border" disabled>Bukti</button>
                              @endif

                              <a class="btn btn-sm btn-outline-secondary" href="{{ route('admin.invoices.show', $inv) }}">Detail</a>

                              @if(in_array($st,['menunggu verifikasi','pending','belum','belum lunas','menunggu','unpaid']))
                                <form action="{{ route('admin.invoices.verify',$inv) }}" method="POST" class="d-inline">@csrf
                                  <button class="btn btn-sm btn-success" onclick="return confirm('Set LUNAS?')">Verifikasi</button>
                                </form>
                                <form action="{{ route('admin.invoices.reject',$inv) }}" method="POST" class="d-inline"
                                      onsubmit="return (this.alasan.value = prompt('Alasan penolakan?')) !== null;">
                                  @csrf <input type="hidden" name="alasan" value="">
                                  <button class="btn btn-sm btn-danger">Tolak</button>
                                </form>
                                <form action="{{ route('admin.invoices.reset',$inv) }}" method="POST" class="d-inline">@csrf
                                  <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset status?')">Reset Status</button>
                                </form>
                              @elseif(in_array($st,['lunas','lunas (otomatis)','paid','terverifikasi']))
                                <span class="btn btn-sm btn-success disabled">Terverifikasi</span>
                                <form action="{{ route('admin.invoices.reset',$inv) }}" method="POST" class="d-inline">@csrf
                                  <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset status?')">Reset Status</button>
                                </form>
                              @elseif(in_array($st,['ditolak','reject','gagal','batal','invalid']))
                                <span class="btn btn-sm btn-danger disabled">Ditolak</span>
                                <form action="{{ route('admin.invoices.verify',$inv) }}" method="POST" class="d-inline">@csrf
                                  <button class="btn btn-sm btn-success" onclick="return confirm('Set LUNAS?')">Verifikasi</button>
                                </form>
                                <form action="{{ route('admin.invoices.reset',$inv) }}" method="POST" class="d-inline">@csrf
                                  <button class="btn btn-sm btn-outline-warning" onclick="return confirm('Reset status?')">Reset Status</button>
                                </form>
                              @endif
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

  {{-- Pagination per MAHASISWA --}}
  <div class="d-flex justify-content-between align-items-center">
    <div class="small text-muted">
      Menampilkan {{ $students->firstItem() }}–{{ $students->lastItem() }} dari {{ $students->total() }} mahasiswa
    </div>
    <div>{{ $students->onEachSide(1)->appends(['per_page'=>$per])->links() }}</div>
  </div>

{{-- =================== MODE FLAT (fallback tampilan lama) =================== --}}
@else
  <div class="table-responsive">
    <table class="table table-striped align-middle">
      <thead>
        <tr>
          <th>#</th>
          <th>Mahasiswa</th>
          <th>Semester/TA</th>
          <th>Bulan</th>
          <th>Nominal</th>
          <th>Status</th>
          <th style="width:340px">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($invoices as $i)
          @php
            $sem_ = $i->semester ?: ($i->mahasiswa->semester_awal ?? null);
            $ta_  = $i->tahun_akademik ?: ($i->mahasiswa->tahun_akademik ?? null);
            $semLabel = $sem_ ? ucfirst($sem_) : '—';
            $taLabel  = $ta_  ?: '—';

            $st = strtolower(trim($i->status ?? ''));
            $badge = match (true) {
              in_array($st, ['lunas','lunas (otomatis)','terverifikasi','paid']) => 'bg-success',
              in_array($st, ['ditolak','reject','gagal','batal','invalid'])       => 'bg-danger',
              in_array($st, ['belum','belum lunas','pending','menunggu','menunggu verifikasi','unpaid']) => 'bg-secondary',
              default => 'bg-secondary',
            };

            $hasBukti  = !empty($i->bukti ?? null);
            $publicUrl = null;
            if ($hasBukti) {
              $path = ltrim($i->bukti,'/');
              $publicUrl = (str_contains($path,'/')) ? asset('storage/'.$path) : asset('storage/bukti/'.$path);
            }
          @endphp

          <tr>
            <td>{{ $loop->iteration + ($invoices->currentPage()-1)*$invoices->perPage() }}</td>
            <td>
              <div class="fw-semibold">{{ $i->mahasiswa->nama ?? '-' }}</div>
              <div class="small text-muted">{{ $i->mahasiswa->nim ?? '' }}</div>
            </td>
            <td>{{ $semLabel }} / {{ $taLabel }}</td>
            <td>{{ $i->bulan ?? '—' }}</td>
            <td>{{ $fmtRupiah($i->jumlah ?? $i->nominal) }}</td>
            <td><span class="badge {{ $badge }}">{{ $i->status ?? 'Belum' }}</span></td>
            <td class="d-flex flex-wrap gap-1">
              @if($hasBukti && $publicUrl)
                <a class="btn btn-sm btn-outline-primary" href="{{ $publicUrl }}" target="_blank">Bukti</a>
              @else
                <button class="btn btn-sm btn-outline-secondary" disabled>Bukti</button>
              @endif

              <a class="btn btn-sm btn-outline-dark" href="{{ route('admin.invoices.show', $i) }}">Detail</a>

              @if(isset($i->mahasiswa_id))
                <form action="{{ route('admin.mahasiswa.reset-angsuran', $i->mahasiswa_id) }}" method="POST"
                      onsubmit="return confirm('Reset pilihan angsuran mahasiswa ini? Invoice yang masih pending akan dihapus. Lanjutkan?')">
                  @csrf
                  <button class="btn btn-sm btn-outline-warning">Reset Angsuran</button>
                </form>
              @endif

              @if(in_array($st,['menunggu verifikasi','pending','belum','belum lunas','menunggu','unpaid']))
                <form action="{{ route('admin.invoices.verify',$i) }}" method="POST">@csrf
                  <button class="btn btn-sm btn-success" onclick="return confirm('Set LUNAS?')">Verifikasi</button>
                </form>
                <form action="{{ route('admin.invoices.reject',$i) }}" method="POST"
                      onsubmit="return (this.alasan.value = prompt('Alasan penolakan?')) !== null;">
                  @csrf <input type="hidden" name="alasan" value="">
                  <button class="btn btn-sm btn-danger">Tolak</button>
                </form>
                <form action="{{ route('admin.invoices.reset',$i) }}" method="POST">@csrf
                  <button class="btn btn-sm btn-warning" onclick="return confirm('Reset status?')">Reset Status</button>
                </form>
              @elseif(in_array($st,['lunas','lunas (otomatis)','paid','terverifikasi']))
                <span class="btn btn-sm btn-success disabled">Terverifikasi</span>
                <form action="{{ route('admin.invoices.reset',$i) }}" method="POST">@csrf
                  <button class="btn btn-sm btn-warning" onclick="return confirm('Reset status?')">Reset Status</button>
                </form>
              @elseif(in_array($st,['ditolak','reject','gagal','batal','invalid']))
                <span class="btn btn-sm btn-danger disabled">Ditolak</span>
                <form action="{{ route('admin.invoices.verify',$i) }}" method="POST">@csrf
                  <button class="btn btn-sm btn-success" onclick="return confirm('Set LUNAS?')">Verifikasi</button>
                </form>
                <form action="{{ route('admin.invoices.reset',$i) }}" method="POST">@csrf
                  <button class="btn btn-sm btn-warning" onclick="return confirm('Reset status?')">Reset Status</button>
                </form>
              @endif

              {{-- ===== Export Mahasiswa Ini (RPL) di tampilan flat ===== --}}
              @if(Route::has('admin.exports.invoices.rpl') && !empty($i->mahasiswa?->nim))
                <a class="btn btn-sm btn-outline-primary"
                   href="{{ route('admin.exports.invoices.rpl', ['mode' => 'single', 'nim' => $i->mahasiswa->nim]) }}">
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

  <div class="d-flex justify-content-between align-items-center">
    <div class="small text-muted">
      Menampilkan {{ $invoices->firstItem() }}–{{ $invoices->lastItem() }} dari {{ $invoices->total() }} invoice
    </div>
    <div>{{ $invoices->onEachSide(1)->appends(['per_page'=>$per])->links() }}</div>
  </div>
@endif
@endsection
