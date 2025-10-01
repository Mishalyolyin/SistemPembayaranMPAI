{{-- resources/views/admin/settings/edit-total-tagihan.blade.php --}}
@extends('layouts.admin')

@section('title', 'Pengaturan Total Tagihan RPL')

@section('content')
  <div class="mb-4 p-4 rounded-3 shadow-sm text-white"
       style="background:linear-gradient(135deg,#0ea5e9,#2563eb);">
    <h3 class="mb-1 fw-bold">Pengaturan Total Tagihan Mahasiswa RPL</h3>
    <p class="mb-0 opacity-75">
      Atur <b>kebijakan</b> total tagihan RPL secara <b>Global</b> atau per <b>Cohort (TA + Semester)</b>.
    </p>
  </div>

  @if (session('success'))
    <div class="alert alert-success shadow-sm">{{ session('success') }}</div>
  @endif
  @if (session('error'))
    <div class="alert alert-danger shadow-sm">{{ session('error') }}</div>
  @endif

  @if ($errors->any())
    <div class="alert alert-danger shadow-sm">
      <div class="fw-semibold mb-2">Periksa input berikut:</div>
      <ul class="mb-0">
        @foreach ($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="card shadow-sm border-0">
        <div class="card-body p-4">
          {{-- Arahkan ke route update yang sudah ada di controller --}}
          <form method="POST" action="{{ route('admin.settings.tagihan.update') }}" id="formMassUpdate">
            @csrf

            {{-- ===== Cakupan Perubahan (scope) ===== --}}
            @php
              $scopeOld = old('scope','global');
              $oldSem   = old('semester_awal','');
              $oldTA    = old('tahun_akademik','');
            @endphp
            <div class="mb-4">
              <label class="form-label fw-semibold">Terapkan ke</label>
              <div class="row g-2">
                <div class="col-md-6">
                  <div class="form-check p-3 border rounded-3 h-100">
                    <input class="form-check-input" type="radio" name="scope" id="scopeGlobal"
                           value="global" {{ $scopeOld === 'global' ? 'checked' : '' }}>
                    <label class="form-check-label d-block fw-semibold" for="scopeGlobal">
                      Global (Default RPL)
                    </label>
                    <small class="text-muted">
                      Menetapkan <code>total_tagihan_rpl</code> sebagai default RPL.
                    </small>
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-check p-3 border rounded-3 h-100">
                    <input class="form-check-input" type="radio" name="scope" id="scopeCohort"
                           value="cohort" {{ $scopeOld === 'cohort' ? 'checked' : '' }}>
                    <label class="form-check-label d-block fw-semibold" for="scopeCohort">
                      Cohort (TA + Semester)
                    </label>
                    <small class="text-muted">
                      Menetapkan kebijakan untuk kombinasi <b>Tahun Akademik</b> & <b>Semester</b> tertentu.
                    </small>
                  </div>
                </div>
              </div>
            </div>

            {{-- ===== Filter Cohort (aktif jika scope=cohort) ===== --}}
            <div id="filterBox" class="mb-4 border rounded-3 p-3 {{ $scopeOld==='cohort' ? '' : 'd-none' }}">
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label">Semester Awal</label>
                  <select name="semester_awal" id="semester_awal" class="form-select">
                    <option value="">— pilih —</option>
                    <option value="ganjil" {{ $oldSem==='ganjil' ? 'selected' : '' }}>Ganjil</option>
                    <option value="genap"  {{ $oldSem==='genap'  ? 'selected' : '' }}>Genap</option>
                  </select>
                </div>
                <div class="col-md-6">
                  <label class="form-label">Tahun Akademik</label>
                  <select name="tahun_akademik" id="tahun_akademik" class="form-select">
                    <option value="">— pilih —</option>
                    {{-- opsi diisi via JS sesuai semester --}}
                  </select>
                </div>
              </div>
              <small class="text-muted d-block mt-2">
                Contoh key yang akan dibuat: <code>rpl:25/26:ganjil</code>
              </small>
            </div>

            {{-- ===== Nilai Tagihan ===== --}}
            <div class="mb-4">
              <label for="total_tagihan" class="form-label fw-semibold">Total Tagihan (Rp)</label>
              <div class="input-group input-group-lg">
                <span class="input-group-text">Rp</span>
                <input type="number" min="0" step="1000" class="form-control"
                       id="total_tagihan" name="total_tagihan"
                       value="{{ old('total_tagihan', $total ?? ($total_tagihan ?? 0)) }}" required>
              </div>
              <small class="text-muted">
                Masukkan angka tanpa titik/koma. Contoh: <code>25000000</code>.
              </small>
            </div>

            <div class="d-flex gap-2">
              <button class="btn btn-primary btn-lg px-4" type="submit">
                <i class="bi bi-check2-circle me-1"></i> Simpan
              </button>
              <a href="{{ route('admin.mahasiswa.index') }}" class="btn btn-outline-secondary btn-lg">
                Batal
              </a>
            </div>
          </form>
        </div>
      </div>
    </div>

    {{-- Panel Preview / Info --}}
    <div class="col-lg-5">
      <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
          <h5 class="fw-bold mb-3">Pratinjau Perubahan</h5>
          <div class="p-3 rounded-3 bg-light mb-3">
            <div class="small text-muted">Nominal baru</div>
            <div id="previewNominal" class="display-6 fw-semibold">Rp 0</div>
          </div>
          <ul class="list-unstyled small text-muted mb-0">
            <li class="mb-1">
              <i class="bi bi-gear me-1"></i>
              Perubahan ini <b>menetapkan kebijakan</b> di tabel <code>settings</code>
              (<code>total_tagihan_rpl</code> atau <code>rpl:{TA}:{semester}</code>).
            </li>
            <li class="mb-1">
              <i class="bi bi-shield-check me-1"></i>
              <b>Tidak</b> mengubah kolom per-mahasiswa atau invoice yang sudah ada.
              Mahasiswa baru otomatis ikut kebijakan sesuai TA & semester.
            </li>
            <li>
              <i class="bi bi-info-circle me-1"></i>
              Jika cohort sudah punya invoice, pengubahan akan <b>ditolak</b>.
            </li>
          </ul>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
<script>
  // ===== Data TA by semester dari controller (fallback ke array kosong kalau null) =====
  const TA_BY_SEMESTER = @json($taBySemester ?? ['ganjil'=>[], 'genap'=>[]]);

  // Toggle filter box
  const scopeGlobal   = document.getElementById('scopeGlobal');
  const scopeCohort   = document.getElementById('scopeCohort');
  const filterBox     = document.getElementById('filterBox');

  // Elemen cohort
  const selSem = document.getElementById('semester_awal');
  const selTA  = document.getElementById('tahun_akademik');

  // Old values dari server
  const OLD_SEM = @json($oldSem);
  const OLD_TA  = @json($oldTA);

  function toggleFilter() {
    const isCohort = scopeCohort.checked;
    filterBox.classList.toggle('d-none', !isCohort);

    // disable/enable field cohort
    [selSem, selTA].forEach(el => { if (el) el.disabled = !isCohort; });
  }

  function populateTA() {
    const sem = (selSem?.value || '').toLowerCase();
    const list = (TA_BY_SEMESTER && TA_BY_SEMESTER[sem]) ? TA_BY_SEMESTER[sem] : [];
    selTA.innerHTML = '<option value="">— pilih —</option>';

    if (!Array.isArray(list) || list.length === 0) {
      const opt = document.createElement('option');
      opt.value = '';
      opt.textContent = 'Tidak ada TA untuk semester ini';
      opt.disabled = true;
      selTA.appendChild(opt);
      return;
    }

    list.forEach(ta => {
      const opt = document.createElement('option');
      opt.value = ta;
      opt.textContent = ta;
      if (OLD_TA && OLD_TA === ta) opt.selected = true;
      selTA.appendChild(opt);
    });
  }

  // Preview nominal
  const inputTotal = document.getElementById('total_tagihan');
  const preview    = document.getElementById('previewNominal');
  function rupiah(x) {
    try { return 'Rp ' + Number(x||0).toLocaleString('id-ID'); }
    catch { return 'Rp 0'; }
  }
  function updatePreview(){ preview.textContent = rupiah(inputTotal.value); }

  // Init
  document.addEventListener('DOMContentLoaded', () => {
    // restore old semester (jika ada), lalu populate TA
    if (selSem && OLD_SEM) selSem.value = OLD_SEM;
    populateTA();

    // listeners
    scopeGlobal?.addEventListener('change', toggleFilter);
    scopeCohort?.addEventListener('change', toggleFilter);
    selSem?.addEventListener('change', populateTA);
    inputTotal?.addEventListener('input', updatePreview);

    // first render
    toggleFilter();
    updatePreview();
  });
</script>
@endpush
