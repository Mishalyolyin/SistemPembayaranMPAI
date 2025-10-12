{{-- resources/views/mahasiswa_reguler/angsuran.blade.php --}}
@extends('layouts.mahasiswa_reguler')
@section('title', 'Pilih Angsuran (Reguler)')

@section('content')
@php
  use App\Helpers\SemesterHelper;
  use App\Support\RegulerBilling;

  // ====== Semester aktif (handle array/string) ======
  $active   = SemesterHelper::getActiveSemester();
  $semKode  = is_array($active) ? ($active['kode'] ?? $active['name'] ?? '-') : ($active ?? '-');
  $periode  = is_array($active) ? ($active['periode'] ?? null) : null;

  // ====== Mahasiswa (controller -> fallback auth) ======
  $mhs = $mahasiswa ?? ($user ?? auth('mahasiswa_reguler')->user());

  // ====== Opsi angsuran reguler (controller boleh override) ======
  $opsiAngsuran = isset($opsiAngsuran) && is_array($opsiAngsuran) ? $opsiAngsuran : [8, 20];

  // ====== Default plan (controller -> profil -> default 8) ======
  $selectedPlan = (string)($defaultPlan ?? ($mhs->angsuran ?? 8));
  if (!in_array((int)$selectedPlan, $opsiAngsuran, true)) $selectedPlan = (string)$opsiAngsuran[0];

  // ====== Total tagihan: prioritas dari preview controller, fallback ke resolver ======
  $previewData = $preview ?? null;
  $totalTagihanSafe = is_array($previewData) && isset($previewData['total']) ? (int)$previewData['total'] : 0;
  if ($totalTagihanSafe <= 0 && $mhs && class_exists(RegulerBilling::class)) {
      $totalTagihanSafe = (int) RegulerBilling::totalTagihanFor($mhs);
  }
  $totalTagihanFmt = 'Rp ' . number_format(max(0,$totalTagihanSafe), 0, ',', '.');

  // ====== Build daftar bulan untuk preview sesuai plan (anchor mahasiswa > semester aktif) ======
  // Prefer helper yang mengembalikan struktur [{ym:'2024-09', label:'September 2024'}, ...]
  $getRows = function(int $plan) use ($mhs) {
      if (class_exists(RegulerBilling::class) && method_exists(RegulerBilling::class, 'monthsForMahasiswa') && $mhs) {
          $rows = RegulerBilling::monthsForMahasiswa($mhs, $plan);
          return array_map(function($x){
              return [
                  'ym'    => $x['ym']    ?? null,
                  'label' => $x['label'] ?? ($x['ym'] ?? '')
              ];
          }, $rows ?? []);
      }
      if (class_exists(RegulerBilling::class) && method_exists(RegulerBilling::class, 'monthsForActiveSemester')) {
          $rows = RegulerBilling::monthsForActiveSemester($plan);
          return array_map(function($x){
              return [
                  'ym'    => $x['ym']    ?? null,
                  'label' => $x['label'] ?? ($x['ym'] ?? '')
              ];
          }, $rows ?? []);
      }
      // Fallback sederhana kalau helper tak ada (pakai pola genap agar aman)
      $y = (int)now()->year;
      if ($plan === 8) {
        $labels = ['Februari '.$y,'Maret '.$y,'April '.$y,'Mei '.$y,'Juni '.$y,'Juli '.$y,'Agustus '.$y,'September '.$y];
      } else {
        $labels = [
          'Februari '.$y,'Maret '.$y,'April '.$y,'Mei '.$y,'Juni '.$y,'Juli '.$y,'Agustus '.$y,'September '.$y,'Oktober '.$y,'November '.$y,
          'Desember '.$y,'Januari '.($y+1),'Februari '.($y+1),'Maret '.($y+1),'April '.($y+1),'Mei '.($y+1),'Juni '.($y+1),'Juli '.($y+1),'Agustus '.($y+1),'September '.($y+1)
        ];
      }
      // biar konsisten struktur {ym,label}
      return array_map(fn($L) => ['ym'=>null, 'label'=>$L], $labels);
  };

  $pv = [];
  foreach ($opsiAngsuran as $opt) {
      $pv[(string)$opt] = $getRows((int)$opt);
  }

  // Sudah ada invoice?
  $sudahAda = isset($sudahAda) ? (bool)$sudahAda : ($mhs?->invoicesReguler()->exists() ?? false);

  // ====== Submit route (fallback aman) ======
  $submitRoute = \Illuminate\Support\Facades\Route::has('mahasiswa_reguler.angsuran.simpan')
      ? 'mahasiswa_reguler.angsuran.simpan'
      : (\Illuminate\Support\Facades\Route::has('reguler.angsuran.simpan')
          ? 'reguler.angsuran.simpan'
          : 'reguler.angsuran.store');
@endphp

<style>
  .glass{background:linear-gradient(135deg,rgba(255,255,255,.75),rgba(255,255,255,.6));box-shadow:0 10px 30px rgba(0,0,0,.08);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.35);}
  .plan{transition:transform .18s,box-shadow .18s,border-color .18s;border:1.5px solid rgba(0,0,0,.08);border-radius:14px;cursor:pointer;}
  .plan:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.10);border-color:rgba(25,135,84,.35);}
  .plan.selected{border-color:#198754;box-shadow:0 14px 32px rgba(25,135,84,.22);}
  .plan .badge-round{width:28px;height:28px;border-radius:999px;display:inline-flex;align-items:center;justify-content:center;background:rgba(25,135,84,.12);font-weight:700;}
  .month-pill{display:inline-block;padding:.35rem .6rem;border-radius:999px;border:1px dashed rgba(0,0,0,.12);font-size:.9rem;transition:.15s;}
  .month-pill:hover{background:rgba(25,135,84,.06);border-color:#198754;}
  .cta-sticky{position:sticky;top:12px;z-index:10;}
  .preview-list{list-style:none;padding:0;margin:0;}
  .preview-list li{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.35rem .25rem;border-bottom:1px dashed rgba(0,0,0,.08);}
  .preview-list li:last-child{border-bottom:0;}
  .amount{font-weight:700;white-space:nowrap;}
</style>

@if (session('success')) <div class="alert alert-success alert-dismissible fade show">{{ session('success') }} <button class="btn-close" data-bs-dismiss="alert"></button></div> @endif
@if (session('info'))    <div class="alert alert-info alert-dismissible fade show">{{ session('info') }} <button class="btn-close" data-bs-dismiss="alert"></button></div> @endif
@if (session('warning')) <div class="alert alert-warning alert-dismissible fade show">{{ session('warning') }} <button class="btn-close" data-bs-dismiss="alert"></button></div> @endif
@if (session('error'))   <div class="alert alert-danger alert-dismissible fade show">{{ session('error') }} <button class="btn-close" data-bs-dismiss="alert"></button></div> @endif
@if ($errors->any())
  <div class="alert alert-danger">
    <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
  </div>
@endif

<div class="container py-4">
  {{-- Banner semester --}}
  <div class="p-4 mb-4 glass rounded-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
      <div>
        <div class="fw-semibold text-uppercase small text-muted">Semester Aktif</div>
        <div class="fs-5 fw-bold">{{ strtoupper($semKode) }}</div>
        @if($periode)
          <div class="small text-muted">Periode {{ $periode }}</div>
        @endif
      </div>
      <span class="badge bg-success fs-6">Mahasiswa Reguler</span>
    </div>
  </div>

  <h4 class="mb-3">
    Pilih Skema Angsuran
    <small class="badge bg-success ms-2 text-uppercase">{{ strtoupper($semKode) }}</small>
  </h4>

  <div class="row g-3">
    {{-- Kolom kiri: pilihan plan --}}
    <div class="col-lg-5">
      <form id="angsuranForm" method="POST" action="{{ route($submitRoute) }}">
        @csrf

        {{-- Hidden bulan_mulai -> diisi JS dengan NAMA BULAN (bukan YYYY-MM) agar kompat ke fallback controller --}}
        <input type="hidden" name="bulan_mulai" id="bulanMulaiInput" value="">

        @foreach($opsiAngsuran as $opt)
          <label class="plan d-block p-3 mb-3" data-plan="{{ $opt }}">
            <div class="d-flex align-items-center gap-2">
              <input class="form-check-input me-2" type="radio" name="angsuran" value="{{ $opt }}" @checked($selectedPlan==(string)$opt)>
              <div class="badge-round">{{ $opt }}</div>
              <div>
                <div class="fw-semibold">{{ $opt }}x</div>
                <div class="text-muted small">
                  {{ $opt === 8 ? 'Balance: tidak terlalu lama' : 'Lebih ringan per invoice, durasi lebih panjang' }}
                </div>
              </div>
            </div>
          </label>
        @endforeach

        @error('angsuran')
          <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror

        <div class="mt-3 cta-sticky">
          <button id="btnSubmit" class="btn btn-primary" type="submit"
            @if($sudahAda || $totalTagihanSafe <= 0) disabled @endif>
            Simpan & Generate Invoice
          </button>
          <div class="small text-muted mt-1">
            * Invoice dibuat sesuai kebijakan TA & anchor mahasiswa. Duplikasi bulan otomatis dicegah.
          </div>
          @if($sudahAda)
            <div class="text-danger small mt-2">Invoice sudah pernah dibuat untuk akun ini.</div>
          @endif
          @if($totalTagihanSafe <= 0)
            <div class="text-warning small mt-2">Tagihan cohort ini belum diset admin. Coba lagi nanti.</div>
          @endif
        </div>
      </form>
    </div>

    {{-- Kolom kanan: live preview (bulan • harga) --}}
    <div class="col-lg-7">
      <div class="card h-100">
        <div class="card-body">
          <div class="d-flex align-items-center justify-content-between">
            <h6 class="card-title mb-0">Preview Bulan Tagihan</h6>
            <span id="previewTag" class="badge bg-secondary">—</span>
          </div>

          <hr class="my-2">

          <ul id="previewList" class="preview-list"></ul>

          <hr class="my-2">

          <div class="d-flex align-items-center justify-content-between">
            <div class="text-muted">Total Tagihan (aktif)</div>
            <strong id="previewTotal">{{ $totalTagihanFmt }}</strong>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

{{-- Data & interaksi --}}
<script>
  // PREVIEWS: [{ym:'YYYY-MM' (opsional), label:'Bulan YYYY'}]
  const PREVIEWS = @json($pv);
  const TOTAL    = {{ (int)max(0,$totalTagihanSafe) }};
  const radioEls = document.querySelectorAll('input[name="angsuran"]');
  const cards    = document.querySelectorAll('.plan');
  const ulList   = document.getElementById('previewList');
  const pvTag    = document.getElementById('previewTag');
  const pvTotal  = document.getElementById('previewTotal');
  const submitBtn= document.getElementById('btnSubmit');
  const bulanMulaiInput = document.getElementById('bulanMulaiInput');

  function rupiah(n){ return 'Rp ' + (n||0).toLocaleString('id-ID'); }

  function splitAmount(total, n, index, lastIndex){
    const base = Math.floor(total / n);
    const sisa = total - (base * n);
    return index === lastIndex ? base + sisa : base;
  }

  // Ambil NAMA BULAN dari label "September 2024" -> "September"
  function firstMonthNameFromLabel(label){
    if(!label) return '';
    const m = String(label).trim().match(/^([A-Za-zÀ-ÿ]+)\b/);
    return m ? m[1] : '';
  }

  // Ambil label pertama dari PREVIEWS plan saat ini
  function getFirstLabel(planKey){
    const rows = PREVIEWS[planKey] || [];
    return rows.length ? (rows[0]?.label || '') : '';
  }

  function renderPreview(planKey){
    const rows = PREVIEWS[planKey] || [];
    const n = rows.length > 0 ? rows.length : Math.max(1, parseInt(planKey,10) || 1);

    ulList.innerHTML = '';
    rows.forEach((row, i) => {
      const label = row?.label ?? '';
      const amt = splitAmount(TOTAL, n, i, rows.length - 1);
      const li  = document.createElement('li');
      li.innerHTML = `
        <span class="month-pill">${label}</span>
        <span class="amount">${rupiah(amt)}</span>
      `;
      ulList.appendChild(li);
    });

    pvTag.textContent = planKey ? (planKey + 'x') : '—';
    pvTotal.textContent = rupiah(TOTAL);

    // isi hidden bulan_mulai dengan NAMA BULAN (kompat ke fallback controller generateInvoices(string))
    const firstLabel = getFirstLabel(planKey);
    const monthName  = firstMonthNameFromLabel(firstLabel);
    bulanMulaiInput.value = monthName || '';
  }

  function syncSelectedUI(){
    const current = document.querySelector('input[name="angsuran"]:checked');
    cards.forEach(c => c.classList.remove('selected'));
    if(current){
      document.querySelector(`.plan[data-plan="${current.value}"]`)?.classList.add('selected');
      if (TOTAL > 0) submitBtn.removeAttribute('disabled');
      renderPreview(current.value);
    }else{
      submitBtn.setAttribute('disabled','disabled');
      ulList.innerHTML = '';
      pvTag.textContent = '—';
      pvTotal.textContent = rupiah(TOTAL);
      bulanMulaiInput.value = '';
    }
  }

  // Pastikan sebelum submit, bulan_mulai terisi (nama bulan)
  document.getElementById('angsuranForm').addEventListener('submit', function(e){
    const current = document.querySelector('input[name="angsuran"]:checked');
    if(!current){
      e.preventDefault();
      return;
    }
    if(!bulanMulaiInput.value){
      const monthName = firstMonthNameFromLabel(getFirstLabel(current.value));
      if(monthName){ bulanMulaiInput.value = monthName; }
    }
    if(!bulanMulaiInput.value){
      e.preventDefault();
      alert('Bulan mulai tidak valid. Silakan refresh halaman dan coba lagi.');
    }
  });

  cards.forEach(card => {
    card.addEventListener('click', ()=>{
      const input = card.querySelector('input[type="radio"]');
      if(!input.checked){
        input.checked = true;
        input.dispatchEvent(new Event('change', { bubbles: true }));
      }
    });
  });

  radioEls.forEach(r => r.addEventListener('change', syncSelectedUI));

  // Init
  syncSelectedUI();
</script>
@endsection
