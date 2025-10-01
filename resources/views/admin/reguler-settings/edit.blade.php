@extends('layouts.admin')
@section('title','Pengaturan Tarif Mahasiswa Reguler')

@section('content')
@include('partials.semester')
@include('partials.kalender')

<h4 class="mb-3">Pengaturan Tarif — Mahasiswa Reguler</h4>

@if(session('success'))
  <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
  <div class="alert alert-danger">{{ session('error') }}</div>
@endif
@if(session('warning'))
  <div class="alert alert-warning">{{ session('warning') }}</div>
@endif
@if($errors->any())
  <div class="alert alert-danger">
    <strong>Oops!</strong> Periksa input:
    <ul class="mb-0">
      @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
    </ul>
  </div>
@endif

@php
  // fallback TA kalau controller belum kirim $taBySemester
  $now = now()->year;
  $genTA = [];
  for ($i=-1; $i<=5; $i++) {
    $y1 = $now + $i; $y2 = $y1 + 1;
    $genTA[] = $y1 . '/' . $y2;
  }
  $TA_MAP = $taBySemester ?? ['ganjil' => $genTA, 'genap' => $genTA];
  $oldSem = old('semester_awal');
  $oldTA  = old('tahun_akademik');
@endphp

<div class="row g-4">
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" action="{{ route('admin.settings.reguler-settings.update') }}">
          @csrf
          @method('PATCH')

          {{-- Scope: sama kayak RPL --}}
          <div class="mb-3">
            <label class="form-label d-block">Scope Pengaturan</label>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="scope" id="scopeGlobal" value="global" {{ old('scope','global')==='global'?'checked':'' }}>
              <label class="form-check-label" for="scopeGlobal">Global (Default)</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="scope" id="scopeCohort" value="cohort" {{ old('scope')==='cohort'?'checked':'' }}>
              <label class="form-check-label" for="scopeCohort">Cohort (TA & Semester)</label>
            </div>
          </div>

          {{-- Field Cohort (TA & Semester) --}}
          <div id="cohortFields" class="border rounded p-3 mb-3 {{ old('scope')==='cohort' ? '' : 'd-none' }}">
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Semester</label>
                <select name="semester_awal" id="semesterSelect" class="form-select">
                  <option value="">-- pilih --</option>
                  <option value="ganjil" {{ $oldSem==='ganjil'?'selected':'' }}>Ganjil</option>
                  <option value="genap"  {{ $oldSem==='genap'?'selected':'' }}>Genap</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Tahun Akademik</label>
                <select name="tahun_akademik" id="taSelect" class="form-select">
                  {{-- options akan diisi via JS sesuai semester --}}
                </select>
                <div class="form-text">Pilih dari daftar agar konsisten dengan cohort.</div>
              </div>
            </div>
          </div>

          {{-- Total Tagihan --}}
          <div class="mb-3">
            <label class="form-label">Total Tagihan</label>
            <input type="text" name="total_tagihan" class="form-control"
                   placeholder="misal: 30.000.000 atau 30 jt"
                   value="{{ old('total_tagihan', number_format($total ?? 0, 0, ',', '.')) }}">
            <div class="form-text">Boleh pakai “jt/juta/m”, koma/titik bebas. Sistem otomatis menormalkan ke angka murni.</div>
          </div>

          <div class="d-flex gap-2">
            <button class="btn btn-primary">Simpan</button>
            <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Batal</a>
          </div>
        </form>
      </div>
    </div>
  </div>

  {{-- Panel policy cohort --}}
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h6 class="mb-3">Kebijakan Cohort Tersimpan</h6>
        <div class="table-responsive">
          <table class="table align-middle">
            <thead>
              <tr><th>Key</th><th class="text-end">Nominal</th></tr>
            </thead>
            <tbody>
              @forelse(($policies ?? []) as $p)
                <tr>
                  <td><code>{{ $p->key ?? $p->name ?? '-' }}</code></td>
                  <td class="text-end">Rp{{ number_format((int)($p->value ?? 0), 0, ',', '.') }}</td>
                </tr>
              @empty
                <tr><td colspan="2" class="text-muted">Belum ada policy cohort tersimpan.</td></tr>
              @endforelse
            </tbody>
          </table>
        </div>

        <div class="alert alert-warning mt-3">
          <strong>Catatan:</strong> Perubahan tarif <em>tidak</em> mengubah invoice yang sudah terbit.
          Jika perlu penyesuaian massal (mis. hanya invoice <em>pending</em>), siapkan aksi khusus.
        </div>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
(function(){
  const radioGlobal = document.getElementById('scopeGlobal');
  const radioCohort = document.getElementById('scopeCohort');
  const cohortBox   = document.getElementById('cohortFields');
  const semSelect   = document.getElementById('semesterSelect');
  const taSelect    = document.getElementById('taSelect');

  const TA_MAP = @json($TA_MAP);
  const OLD_TA = @json($oldTA);

  function toggleCohort(){ cohortBox.classList.toggle('d-none', !radioCohort.checked); }

  function fillTA(){
    const sem = (semSelect.value || '').toLowerCase();
    const list = (TA_MAP[sem] || []);
    taSelect.innerHTML = '';
    if (!sem) {
      const opt = document.createElement('option');
      opt.value = ''; opt.textContent = '-- pilih semester dulu --';
      taSelect.appendChild(opt);
      taSelect.disabled = true;
      return;
    }
    taSelect.disabled = false;
    taSelect.appendChild(new Option('-- pilih TA --',''));
    list.forEach(v => taSelect.appendChild(new Option(v, v)));
    if (OLD_TA) taSelect.value = OLD_TA;
  }

  radioGlobal?.addEventListener('change', toggleCohort);
  radioCohort?.addEventListener('change', toggleCohort);
  semSelect?.addEventListener('change', fillTA);

  // init
  toggleCohort();
  fillTA();
})();
</script>
@endpush

@endsection
