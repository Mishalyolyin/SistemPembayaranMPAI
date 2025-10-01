@extends('layouts.admin')
@section('title', 'Data Mahasiswa RPL')

@section('content')
  <h4 class="mb-4">Data Mahasiswa RPL</h4>

  {{-- === Flash notifications === --}}
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

  @php
    $perPageCur     = isset($perPage) ? (int)$perPage : (int)request('per_page', 120);
    $perPageOptions = $perPageOptions ?? range(10, 1000, 10);
  @endphp

  {{-- ===== Filter ===== --}}
  <form id="filterForm" method="GET" action="{{ route('admin.mahasiswa.index') }}">
    <input type="hidden" name="jenis_mahasiswa" value="RPL">
    <div class="row mb-3 align-items-end">
      <div class="col-md-4">
        <label class="form-label">Cari</label>
        <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="Cari nama/NIM..." />
      </div>

      <div class="col-md-3">
        <label class="form-label">Semester</label>
        <select name="semester" class="form-select">
          <option value="">-- Semua Semester --</option>
          <option value="ganjil" {{ request('semester')=='ganjil' ? 'selected':'' }}>Ganjil</option>
          <option value="genap"  {{ request('semester')=='genap'  ? 'selected':'' }}>Genap</option>
        </select>
      </div>

      <div class="col-md-3">
        <label class="form-label">Tahun Akademik</label>
        <select name="tahun_akademik" class="form-select">
          <option value="">-- Semua Tahun Akademik --</option>
          @foreach($tahunAkademikList as $th)
            <option value="{{ $th }}" {{ request('tahun_akademik')==$th ? 'selected':'' }}>{{ $th }}</option>
          @endforeach
        </select>
      </div>

      <div class="col-md-2">
        <label class="form-label">Tampil</label>
        <select name="per_page" class="form-select" onchange="document.getElementById('filterForm').submit()">
          @foreach($perPageOptions as $opt)
            <option value="{{ $opt }}" @selected($perPageCur==$opt)>{{ $opt }}</option>
          @endforeach
        </select>
      </div>
    </div>

    <div class="row mb-3">
      <div class="col-md-2 ms-auto d-grid">
        <button type="submit" class="btn btn-primary">Filter</button>
      </div>
    </div>
  </form>

  {{-- Aksi --}}
  <div class="d-flex align-items-center mb-2">
    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#importModal">
      <i class="bi bi-upload"></i> Import CSV
    </button>

    <form id="bulkDeleteFormTop" action="{{ route('admin.mahasiswa.bulkDelete') }}" method="POST" class="ms-2">
      @csrf
      <button type="submit" class="btn btn-danger">
        <i class="bi bi-trash"></i> Hapus Terpilih
      </button>
    </form>

    <a href="{{ route('admin.settings.total-tagihan') }}" class="btn btn-warning ms-2">
      <i class="bi bi-pencil-square"></i> Edit Tagihan (Semua RPL)
    </a>
  </div>

  {{-- Tabel --}}
  <div class="table-responsive">
    <table class="table table-striped align-middle" id="tbl-mahasiswa">
      <thead class="table-light">
        <tr>
          <th style="width:36px;">
            {{-- master pilih semua (halaman ini) --}}
            <input type="checkbox" id="ck-all" />
          </th>
          <th style="width:50px;">#</th>
          <th>Nama</th>
          <th>NIM</th>
          <th>Sem. Awal</th>
          <th>Thn. Akd.</th>
          <th class="text-center" style="width:140px;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        @php
          $noStart = method_exists($paginator ?? null, 'firstItem') ? ($paginator->firstItem() ?? 1) : 1;
          $no = $noStart;
        @endphp

        @forelse($mahasiswas as $key => $group)
          @php
            $first = $group->first();
            $raw   = $first->tanggal_upload ?? $first->created_at ?? null;

            // key tanggal (untuk data-date)
            try {
              $dateKey = $raw
                ? (\Carbon\Carbon::parse($raw))->timezone(config('app.timezone','Asia/Jakarta'))->format('Y-m-d')
                : 'no-date';
            } catch (\Throwable $e) { $dateKey = 'no-date'; }

            // label tanggal untuk tampilan
            $labelTanggal = 'Tanpa Tanggal';
            try {
              if ($raw) {
                $labelTanggal = (\Carbon\Carbon::parse($raw))
                  ->timezone(config('app.timezone','Asia/Jakarta'))
                  ->locale('id')
                  ->translatedFormat('d MMMM Y');
              }
            } catch (\Throwable $e) { /* ignore */ }

            // bersihkan duplikasi nama bulan kalau ada
            $bulanPattern = '(Januari|Februari|Maret|April|Mei|Juni|Juli|Agustus|September|Oktober|November|Desember)';
            $labelTanggal = preg_replace(
              ['/\\b'.$bulanPattern.'\\b(?:\\s*\\1\\b)+/u', '/'.$bulanPattern.'(?:\\1)+/u'],
              '$1', $labelTanggal ?? ''
            );
          @endphp

          {{-- header tanggal: bisa klik utk pilih semua pada tanggal ini --}}
          <tr class="table-secondary date-header" data-date="{{ $dateKey }}" style="cursor:pointer">
            <td>
              <input type="checkbox" class="ck-date" data-date="{{ $dateKey }}">
            </td>
            <td colspan="6" class="fw-semibold">{{ $labelTanggal }}</td>
          </tr>

          {{-- baris data --}}
          @foreach($group as $mhs)
            @php
              try {
                $rowDateKey = ($mhs->tanggal_upload ?? $mhs->created_at)
                  ? (\Carbon\Carbon::parse($mhs->tanggal_upload ?? $mhs->created_at))->timezone(config('app.timezone','Asia/Jakarta'))->format('Y-m-d')
                  : 'no-date';
              } catch (\Throwable $e) { $rowDateKey = 'no-date'; }
            @endphp
            <tr class="row-item" data-date="{{ $rowDateKey }}">
              <td>
                {{-- penting: name="ids[]" utk submit --}}
                <input type="checkbox" class="ck-row" name="ids[]" value="{{ $mhs->id }}" data-date="{{ $rowDateKey }}">
              </td>
              <td>{{ $no++ }}</td>
              <td>{{ $mhs->nama ?? '-' }}</td>
              <td>{{ $mhs->nim ?? '-' }}</td>
              <td>{{ isset($mhs->semester_awal) ? ucfirst($mhs->semester_awal) : '-' }}</td>
              <td>{{ $mhs->tahun_akademik ?? '-' }}</td>
              <td class="text-center">
                <a href="{{ route('admin.mahasiswa.show', $mhs) }}" class="btn btn-sm btn-info px-3">
                  <i class="bi bi-receipt"></i> Tagihan
                </a>
              </td>
            </tr>
          @endforeach
        @empty
          <tr><td colspan="7" class="text-center text-muted">Tidak ada data.</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>

  {{-- Pagination --}}
  @if(isset($paginator) && method_exists($paginator,'links'))
    <div class="d-flex justify-content-between align-items-center">
      <div class="small text-muted">
        Menampilkan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }} mahasiswa
      </div>
      <div>{{ $paginator->onEachSide(1)->appends(request()->query())->links() }}</div>
    </div>
  @endif

  {{-- Modal Import CSV --}}
  <div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="{{ route('admin.mahasiswa.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="jenis_mahasiswa" value="RPL">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Import Mahasiswa RPL (CSV)</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">File CSV</label>
              <input type="file" name="file" class="form-control" accept=".csv" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Semester Awal</label>
              <select name="semester_awal" id="semesterSelect" class="form-select" required>
                <option value="">Pilih...</option>
                <option value="ganjil">Ganjil</option>
                <option value="genap">Genap</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Tahun Akademik</label>
              <select name="tahun_akademik" id="tahunSelect" class="form-select" required>
                <option value="">Pilih semester dulu</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
            <button type="submit" class="btn btn-primary">Import</button>
          </div>
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

  const table = $('#tbl-mahasiswa');
  if(!table) return;

  const ckAll = $('#ck-all');

  // ===== Master: pilih semua di halaman ini
  ckAll?.addEventListener('change', e => {
    const on = e.target.checked;
    $$('.ck-row').forEach(i => i.checked = on);
    syncDateBoxes();
  });

  // ===== Change handlers utk checkbox tanggal & baris
  table.addEventListener('change', e => {
    const el = e.target;

    if(el.classList.contains('ck-date')){
      const key = el.dataset.date;
      const on  = el.checked;
      $$('.ck-row[data-date="'+cssEscape(key)+'"]').forEach(i => i.checked = on);
      ckAll.checked = $$('.ck-row').length>0 && $$('.ck-row').every(i => i.checked);
      syncOneDate(key);
    }

    if(el.classList.contains('ck-row')){
      ckAll.checked = $$('.ck-row').length>0 && $$('.ck-row').every(i => i.checked);
      syncOneDate(el.dataset.date);
    }
  });

  // ===== Klik baris tanggal untuk toggle
  table.addEventListener('click', e => {
    const row = e.target.closest('.date-header');
    if(!row) return;
    if(e.target.matches('input, label, a, button')) return; // biar gak double
    const box = row.querySelector('.ck-date');
    if(box){
      box.checked = !box.checked;
      box.dispatchEvent(new Event('change', {bubbles:true}));
    }
  });

  // ===== Bulk delete: kumpulin ids yang tercentang
  $('#bulkDeleteFormTop')?.addEventListener('submit', function(e){
    this.querySelectorAll('input[name="ids[]"]').forEach(el => el.remove());
    const ids = $$('.ck-row').filter(cb => cb.checked).map(cb => cb.value);
    if(ids.length === 0){ e.preventDefault(); alert('Pilih minimal 1 mahasiswa.'); return; }
    ids.forEach(id => {
      const i = document.createElement('input');
      i.type='hidden'; i.name='ids[]'; i.value=id;
      this.appendChild(i);
    });
    if(!confirm('Hapus semua yang dipilih?')) e.preventDefault();
  });

  // ===== Modal import: dropdown TA dinamis
  const semesterSelect = $('#semesterSelect');
  const tahunSelect    = $('#tahunSelect');
  const importModalEl  = $('#importModal');

  function updateTahunAkademik() {
    const sem = semesterSelect.value;
    if (!sem) { tahunSelect.innerHTML = `<option value="">Pilih semester dulu</option>`; return; }
    const now = new Date().getFullYear();
    let choices = sem === 'ganjil'
      ? [`${now-1}/${now}`, `${now}/${now+1}`, `${now+1}/${now+2}`]
      : [`${now-2}/${now-1}`, `${now-1}/${now}`, `${now}/${now+1}`];
    tahunSelect.innerHTML = choices.map(t => `<option value="${t}">${t}</option>`).join('');
  }
  importModalEl?.addEventListener('shown.bs.modal', () => {
    semesterSelect.value = '';
    tahunSelect.innerHTML = `<option value="">Pilih semester dulu</option>`;
  });
  semesterSelect?.addEventListener('change', updateTahunAkademik);

  // ===== Helpers untuk sinkronisasi state checkbox tanggal
  function syncOneDate(key){
    if(!key) return;
    const rows = $$('.ck-row[data-date="'+cssEscape(key)+'"]');
    const head = $('.ck-date[data-date="'+cssEscape(key)+'"]');
    if(!head){ return; }
    if(rows.length===0){ head.indeterminate=false; head.checked=false; return; }
    const checked = rows.filter(i=>i.checked).length;
    head.checked = checked===rows.length;
    head.indeterminate = checked>0 && checked<rows.length;
  }
  function syncDateBoxes(){
    const uniqueDates = [...new Set($$('.ck-row').map(i=>i.dataset.date||''))];
    uniqueDates.forEach(syncOneDate);
  }

  // init
  syncDateBoxes();
  if(ckAll) ckAll.checked = $$('.ck-row').length>0 && $$('.ck-row').every(i=>i.checked);
})();
</script>
@endpush
