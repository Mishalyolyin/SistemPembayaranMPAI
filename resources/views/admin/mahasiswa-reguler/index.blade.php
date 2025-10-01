@extends('layouts.admin')
@section('title','Data Mahasiswa Reguler')

@section('content')
@include('partials.semester')


<h4 class="mb-3">Data Mahasiswa Reguler</h4>

@if(session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if(session('error'))   <div class="alert alert-danger">{{ session('error') }}</div>   @endif
@if(session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
@if($errors->any())
  <div class="alert alert-danger">
    <strong>Oops!</strong> Periksa input:
    <ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

{{-- Filter --}}
<form method="GET" action="{{ route('admin.mahasiswa-reguler.index') }}" class="row g-2 mb-3 align-items-end">
  <div class="col-md-4">
    <label class="form-label">Cari nama/NIM/email…</label>
    <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="misal: Dinda / 2312345">
  </div>
  <div class="col-md-3">
    <label class="form-label">Semester</label>
    <select name="semester" class="form-select" id="filterSemester">
      <option value="">-- Semua Semester --</option>
      <option value="ganjil" @selected(request('semester')==='ganjil')>Ganjil</option>
      <option value="genap"  @selected(request('semester')==='genap')>Genap</option>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Tahun Akademik</label>
    <select name="tahun_akademik" class="form-select" id="filterTA">
      <option value="">-- Semua --</option>
      @foreach(($tahunAkademikList ?? []) as $ta)
        <option value="{{ $ta }}" @selected(request('tahun_akademik')===$ta)>{{ $ta }}</option>
      @endforeach
    </select>
  </div>
  <div class="col-md-2">
    <label class="form-label">Tampil</label>
    <select name="per_page" class="form-select" onchange="this.form.submit()">
      @foreach(($perPageOptions ?? [120,240,480,960,1500,3000]) as $opt)
        <option value="{{ $opt }}" {{ (string)($perPage ?? 120)===(string)$opt ? 'selected' : '' }}>
          {{ number_format($opt,0,',','.') }}
        </option>
      @endforeach
    </select>
  </div>
  <div class="col-12 col-md-4 ms-auto d-grid d-md-block">
    <div class="d-flex gap-2 justify-content-md-end mt-2 mt-md-0">
      <button class="btn btn-primary">Filter</button>
      <a class="btn btn-outline-secondary" href="{{ route('admin.mahasiswa-reguler.index') }}">Reset</a>
    </div>
  </div>
</form>

{{-- Action bar --}}
<div class="d-flex flex-wrap gap-2 mb-2">
  <a href="{{ route('admin.settings.reguler-settings.edit') }}" class="btn btn-outline-secondary">
    <i class="bi bi-gear"></i> Atur Total Tagihan
  </a>

  <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
    <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
  </button>

  <button form="bulkForm" type="submit" class="btn btn-outline-danger" onclick="return confirmBulkDelete()">
    <i class="bi bi-trash"></i> Hapus Terpilih
  </button>
</div>

{{-- TABEL --}}
<form id="bulkForm" action="{{ route('admin.mahasiswaReguler.bulkDelete') }}" method="POST">
  @csrf
  <div class="table-responsive">
    <table class="table table-striped align-middle" id="tbl-reguler">
      <thead class="table-light">
        <tr>
          <th style="width:32px">
            {{-- master pilih semua (halaman aktif) --}}
            <input type="checkbox" id="ck-all-reg">
          </th>
          <th style="width:48px">#</th>
          <th>Nama</th>
          <th>NIM</th>
          <th>Sem. Awal</th>
          <th>Thn. Akd.</th>
          <th style="width:140px">Aksi</th>
        </tr>
      </thead>
      <tbody>
      @php
        $bulanID = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $i = ($rowStart ?? 0); // firstItem()-1
      @endphp

      @forelse(($groups ?? collect()) as $tanggal => $items)
        @php
          // date key (YYYY-mm-dd) utk data-date
          try {
            $t = \Carbon\Carbon::parse($tanggal)->timezone(config('app.timezone','Asia/Jakarta'));
            $dateKey = $t->format('Y-m-d');
            $hdr     = $t->day.' '.$bulanID[$t->month-1].' '.$t->year;
          } catch (\Throwable $e) {
            $dateKey = 'no-date';
            $hdr     = $tanggal;
          }
        @endphp

        {{-- header tanggal: ada checkbox pilih-per-tanggal + barisnya clickable --}}
        <tr class="table-secondary date-header" data-date="{{ $dateKey }}" style="cursor:pointer">
          <td>
            <input type="checkbox" class="ck-date-reg" data-date="{{ $dateKey }}">
          </td>
          <td colspan="6"><strong>Diimpor: {{ $hdr }}</strong></td>
        </tr>

        @foreach($items as $row)
          @php
            $i++;
            // row datekey—fallback ke header kalau perlu
            try {
              $rk = ($row->created_at ?? null)
                ? \Carbon\Carbon::parse($row->created_at)->timezone(config('app.timezone','Asia/Jakarta'))->format('Y-m-d')
                : $dateKey;
            } catch (\Throwable $e) { $rk = $dateKey; }
          @endphp
          <tr class="row-item" data-date="{{ $rk }}">
            <td>
              {{-- keep class row-check (biar kompatibel dgn fungsi lama), plus ck-row-reg utk handler baru --}}
              <input type="checkbox" class="row-check ck-row-reg" name="ids[]" value="{{ $row->id }}" data-date="{{ $rk }}">
            </td>
            <td>{{ $i }}</td>
            <td>{{ $row->nama }}</td>
            <td><code>{{ $row->nim }}</code></td>
            <td class="text-capitalize">{{ $row->semester_awal ?? '-' }}</td>
            <td>{{ $row->tahun_akademik ?? '-' }}</td>
            <td>
              <a href="{{ route('admin.mahasiswaReguler.tagihan', $row->id) }}" class="btn btn-sm btn-outline-primary">
                Lihat Tagihan
              </a>
            </td>
          </tr>
        @endforeach
      @empty
        <tr><td colspan="7" class="text-center text-muted py-4">Belum ada data. Coba import CSV atau ubah filter.</td></tr>
      @endforelse
      </tbody>
    </table>
  </div>
</form>

{{-- Pagination & info --}}
@if(isset($paginator) && method_exists($paginator, 'links'))
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mt-2">
    <div class="small text-muted">
      @if($paginator->total())
        Menampilkan <strong>{{ number_format($paginator->firstItem()) }}</strong>
        s/d <strong>{{ number_format($paginator->lastItem()) }}</strong>
        dari <strong>{{ number_format($paginator->total()) }}</strong> data
      @else
        Tidak ada data untuk filter ini
      @endif
    </div>
    <div>
      {{ $paginator->withQueryString()->onEachSide(1)->links() }}
    </div>
  </div>
@endif

{{-- MODAL IMPORT --}}
<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form action="{{ route('admin.mahasiswaReguler.import') }}" method="POST" enctype="multipart/form-data" class="modal-content">
      @csrf
      <input type="hidden" name="jenis_mahasiswa" value="Reguler">
      <div class="modal-header">
        <h5 class="modal-title">Import Mahasiswa Reguler (CSV)</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">File CSV</label>
          <input type="file" class="form-control" name="csv_file" accept=".csv,text/csv" required>
          <div class="form-text">Minimal kolom: <code>NIM, NAMA</code>. <em>Email opsional</em>.</div>
        </div>
        <div class="row">
          <div class="col-md-6 mb-3">
            <label class="form-label">Semester Awal <span class="text-danger">*</span></label>
            <select name="semester_awal" id="semImport" class="form-select" required>
              <option value="">Pilih…</option>
              <option value="ganjil">Ganjil</option>
              <option value="genap">Genap</option>
            </select>
          </div>
          <div class="col-md-6 mb-3">
            <label class="form-label">Tahun Akademik <span class="text-danger">*</span></label>
            <select name="tahun_akademik" id="taImport" class="form-select" required>
              <option value="">-- pilih TA --</option>
            </select>
            <div class="form-text">Ikuti daftar ini biar konsisten dengan cohort.</div>
          </div>
        </div>
        <div class="alert alert-warning">
          Sistem menolak baris <strong>duplikat</strong> berdasarkan <code>NIM + Tahun Akademik</code>.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Batal</button>
        <button class="btn btn-primary" type="submit">Import</button>
      </div>
    </form>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function(){
  const $$ = sel => Array.from(document.querySelectorAll(sel));
  const $  = sel => document.querySelector(sel);
  const cssEscape = s => s?.replace(/["\\]/g,'\\$&') ?? '';

  const table = $('#tbl-reguler');
  if(!table) return;

  const ckAll = $('#ck-all-reg');

  // ===== GLOBAL: pilih semua di halaman aktif
  ckAll?.addEventListener('change', e => {
    const on = e.target.checked;
    $$('.ck-row-reg').forEach(i => i.checked = on);
    syncDateBoxes();
  });

  // ===== PER TANGGAL & PER BARIS
  table.addEventListener('change', e => {
    const el = e.target;

    // checkbox header tanggal
    if(el.classList.contains('ck-date-reg')){
      const key = el.dataset.date;
      const on  = el.checked;
      $$('.ck-row-reg[data-date="'+cssEscape(key)+'"]').forEach(i => i.checked = on);
      ckAll.checked = $$('.ck-row-reg').length>0 && $$('.ck-row-reg').every(i=>i.checked);
      syncOneDate(key);
    }

    // checkbox baris
    if(el.classList.contains('ck-row-reg')){
      ckAll.checked = $$('.ck-row-reg').length>0 && $$('.ck-row-reg').every(i=>i.checked);
      syncOneDate(el.dataset.date);
    }
  });

  // klik baris header tanggal = toggle
  table.addEventListener('click', e => {
    const row = e.target.closest('.date-header');
    if(!row) return;
    if(e.target.matches('input, label, a, button')) return; // biar gak double toggle
    const box = row.querySelector('.ck-date-reg');
    if(box){
      box.checked = !box.checked;
      box.dispatchEvent(new Event('change', {bubbles:true}));
    }
  });

  // KONFIRMASI HAPUS MASSAL (pakai class lama row-check juga tetap ke-detect)
  window.confirmBulkDelete = function(){
    const anyChecked = $$('.ck-row-reg, .row-check').some(cb => cb.checked);
    if(!anyChecked){ alert('Pilih minimal satu data terlebih dahulu.'); return false; }
    return confirm('Hapus data terpilih beserta seluruh tagihan regulernya?');
  };

  // ====== Modal import: isi TA dinamis
  const semSel = $('#semImport');
  const taSel  = $('#taImport');

  function anchorTA(sem){
    const now = new Date();
    const Y = now.getFullYear(), M = now.getMonth()+1, D = now.getDate();
    const after = (m,d) => (M>m) || (M===m && D>=d);
    if(sem==='ganjil') return after(9,20) ? Y : (Y-1);
    if(sem==='genap')  return Y-1;
    return null;
  }
  function buildTAList(sem){
    const base = anchorTA(sem);
    if(base==null) return [];
    return [`${base-1}/${base}`, `${base}/${base+1}`, `${base+1}/${base+2}`];
  }
  function fillTA(){
    const sem = (semSel?.value || '').toLowerCase();
    const list = buildTAList(sem);
    taSel.innerHTML = '';
    if(!sem){ taSel.appendChild(new Option('-- pilih semester dulu --','')); taSel.disabled=true; return; }
    taSel.disabled=false;
    taSel.appendChild(new Option('-- pilih TA --',''));
    list.forEach(v=>taSel.appendChild(new Option(v,v)));
    if(list[1]) taSel.value = list[1];
  }
  semSel?.addEventListener('change', fillTA);
  document.getElementById('importModal')?.addEventListener('shown.bs.modal', fillTA);

  // ===== helpers indeterminate utk checkbox tanggal
  function syncOneDate(key){
    if(!key) return;
    const rows = $$('.ck-row-reg[data-date="'+cssEscape(key)+'"]');
    const head = $('.ck-date-reg[data-date="'+cssEscape(key)+'"]');
    if(!head){ return; }
    if(rows.length===0){ head.indeterminate=false; head.checked=false; return; }
    const checked = rows.filter(i=>i.checked).length;
    head.checked = checked===rows.length;
    head.indeterminate = checked>0 && checked<rows.length;
  }
  function syncDateBoxes(){
    const uniqueDates = [...new Set($$('.ck-row-reg').map(i=>i.dataset.date||''))];
    uniqueDates.forEach(syncOneDate);
  }

  // init
  syncDateBoxes();
  if(ckAll) ckAll.checked = $$('.ck-row-reg').length>0 && $$('.ck-row-reg').every(i=>i.checked);
})();
</script>
@endpush
