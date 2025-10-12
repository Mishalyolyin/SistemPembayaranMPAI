@extends('layouts.mahasiswa')

@section('title', 'Pilih Skema Angsuran')

@section('content')
@php
  use App\Helpers\SemesterHelper;
  use App\Support\RplBilling;
  use Illuminate\Support\Facades\Route;

  // ====== Semester aktif (handle array/string) ======
  $active   = SemesterHelper::getActiveSemester();
  $semKode  = is_array($active) ? ($active['kode'] ?? $active['name'] ?? '-') : ($active ?? '-');
  $periode  = is_array($active) ? ($active['periode'] ?? null) : null;

  // ====== Sumber data mahasiswa (controller -> fallback auth) ======
  $mhs = $mahasiswa ?? ($user ?? auth('mahasiswa')->user());

  // ====== Ambil total tagihan: prioritas preview dari controller, fallback ke resolver ======
  $totalTagihanSafe = isset($preview['total']) ? (int)$preview['total'] : 0;
  if ($totalTagihanSafe <= 0 && $mhs && class_exists(RplBilling::class)) {
      $totalTagihanSafe = (int) RplBilling::totalTagihanFor($mhs);
  }
  $totalTagihanFmt = 'Rp ' . number_format(max(0,$totalTagihanSafe), 0, ',', '.');

  // ====== Plan terpilih (prioritas controller -> profil -> default 6) ======
  $selectedPlan = (string)($defaultPlan ?? ($mhs->angsuran ?? 6));
  if (!in_array((int)$selectedPlan, [4,6,10], true)) $selectedPlan = '6';

  // ====== Build daftar bulan untuk preview (semua opsi 4/6/10) ======
  // 🔧 FIX: capture $semKode ke closure dengan `use ($semKode)`
  $getMonths = function(int $plan) use ($semKode) {
      if (class_exists(RplBilling::class) && method_exists(RplBilling::class, 'monthsForActiveSemester')) {
          $rows = RplBilling::monthsForActiveSemester($plan);
          return array_map(fn($x) => $x['label'] ?? '', $rows);
      }
      // Fallback super sederhana kalau helper ga ada
      $y = (int)now()->year;

      // Ganjil (20 Sep – 31 Jan): REKAP
      $ganjil = [
        4  => ['September '.$y, 'Desember '.$y, 'Maret '.($y+1), 'Juni '.($y+1)],
        6  => ['September '.$y, 'November '.$y, 'Januari '.($y+1), 'Maret '.($y+1), 'Mei '.($y+1), 'Juni '.($y+1)],
        10 => [
          // Sep–Jun (10 bulan), tanpa Jul–Agu
          'September '.$y,'Oktober '.$y,'November '.$y,'Desember '.$y,
          'Januari '.($y+1),'Februari '.($y+1),'Maret '.($y+1),'April '.($y+1),'Mei '.($y+1),'Juni '.($y+1),
        ],
      ];

      // Genap (20 Feb – 31 Jul): **REKAP FIX**
      $genap = [
        // 4x: Feb, Mei, Agustus, November
        4  => ['Februari '.$y, 'Mei '.$y, 'Agustus '.$y, 'November '.$y],
        // 6x: Feb, Apr, Jun, Ags, Okt, Des
        6  => ['Februari '.$y, 'April '.$y, 'Juni '.$y, 'Agustus '.$y, 'Oktober '.$y, 'Desember '.$y],
        // 10x: Feb–Nov (tanpa Jan & Des akhir)
        10 => ['Februari '.$y,'Maret '.$y,'April '.$y,'Mei '.$y,'Juni '.$y,'Juli '.$y,'Agustus '.$y,'September '.$y,'Oktober '.$y,'November '.$y],
      ];

      $isGanjil = in_array(strtolower($semKode), ['ganjil','g'], true);
      $map = $isGanjil ? $ganjil : $genap;
      return $map[$plan] ?? [];
  };

  $pv = [
    '4'  => $getMonths(4),
    '6'  => $getMonths(6),
    '10' => $getMonths(10),
  ];

  // Sudah ada invoice?
  $sudahAda = isset($sudahAda) ? (bool)$sudahAda : ($mhs?->invoices()->exists() ?? false);

  // Safe action (fallback ke URL kalau route belum didaftarkan)
  $saveAction = Route::has('mahasiswa.angsuran.simpan')
      ? route('mahasiswa.angsuran.simpan')
      : url('/mahasiswa/angsuran/simpan');
@endphp

<style>
  .glass{background:linear-gradient(135deg,rgba(255,255,255,.75),rgba(255,255,255,.6));box-shadow:0 10px 30px rgba(0,0,0,.08);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.35);}
  .plan{transition:transform .18s,box-shadow .18s,border-color .18s;border:1.5px solid rgba(0,0,0,.08);border-radius:14px;cursor:pointer;}
  .plan:hover{transform:translateY(-2px);box-shadow:0 12px 28px rgba(0,0,0,.10);border-color:rgba(25,135,84,.35);}
  .plan.selected{border-color:#198754;box-shadow:0 14px 32px rgba(25,135,84,.22);}
  .plan .badge-round{width:28px;height:28px;border-radius:9999px;display:inline-flex;align-items:center;justify-content:center;background:rgba(25,135,84,.12);font-weight:700;}
  .month-pill{display:inline-block;padding:.35rem .6rem;border-radius:999px;border:1px dashed rgba(0,0,0,.12);font-size:.9rem;transition:.15s;}
  .month-pill:hover{background:rgba(25,135,84,.06);border-color:#198754;}
  .cta-sticky{position:sticky;top:12px;z-index:10;}
  .preview-list{list-style:none;padding:0;margin:0;}
  .preview-list li{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding:.35rem .25rem;border-bottom:1px dashed rgba(0,0,0,.08);}
  .preview-list li:last-child{border-bottom:0;}
  .amount{font-weight:700;white-space:nowrap;}
</style>

@if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
@if (session('info'))    <div class="alert alert-info">{{ session('info') }}</div> @endif
@if (session('warning')) <div class="alert alert-warning">{{ session('warning') }}</div> @endif
@if (session('error'))   <div class="alert alert-danger">{{ session('error') }}</div>   @endif
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
      <span class="badge bg-success fs-6">Mahasiswa RPL</span>
    </div>
  </div>

  <h4 class="mb-3">
    Pilih Skema Angsuran
    <small class="badge bg-success ms-2 text-uppercase">{{ strtoupper($semKode) }}</small>
  </h4>

  <div class="row g-3">
    {{-- Kolom kiri: pilihan plan --}}
    <div class="col-lg-5">
      <form id="angsuranForm" method="POST" action="{{ $saveAction }}">
        @csrf

        {{-- 4x --}}
        <label class="plan d-block p-3 mb-3" data-plan="4">
          <div class="d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="radio" name="angsuran" value="4" @checked($selectedPlan==='4')>
            <div class="badge-round">4</div>
            <div>
              <div class="fw-semibold">4x</div>
              <div class="text-muted small">Ringkas, cicilan per invoice lebih besar</div>
            </div>
          </div>
        </label>

        {{-- 6x --}}
        <label class="plan d-block p-3 mb-3" data-plan="6">
          <div class="d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="radio" name="angsuran" value="6" @checked($selectedPlan==='6')>
            <div class="badge-round">6</div>
            <div>
              <div class="fw-semibold">6x</div>
              <div class="text-muted small">Seimbang antara jumlah & nominal cicilan</div>
            </div>
          </div>
        </label>

        {{-- 10x --}}
        <label class="plan d-block p-3" data-plan="10">
          <div class="d-flex align-items-center gap-2">
            <input class="form-check-input me-2" type="radio" name="angsuran" value="10" @checked($selectedPlan==='10')>
            <div class="badge-round">10</div>
            <div>
              <div class="fw-semibold">10x</div>
              <div class="text-muted small">Cicilan paling ringan per invoice</div>
            </div>
          </div>
        </label>

        @error('angsuran')
          <div class="text-danger small mt-2">{{ $message }}</div>
        @enderror

        <div class="mt-3 cta-sticky">
          <button id="btnSubmit" class="btn btn-primary" type="submit"
            @if($sudahAda || $totalTagihanSafe <= 0) disabled @endif>
            Simpan & Generate Invoice
          </button>
          <div class="small text-muted mt-1">
            * Invoice dibuat sesuai mapping recap & semester aktif. Duplikasi bulan otomatis dicegah.
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
  const PREVIEWS = @json($pv); // label bulan untuk 4/6/10
  const TOTAL    = {{ (int)max(0,$totalTagihanSafe) }};
  const radioEls = document.querySelectorAll('input[name="angsuran"]');
  const cards    = document.querySelectorAll('.plan');
  const ulList   = document.getElementById('previewList');
  const pvTag    = document.getElementById('previewTag');
  const pvTotal  = document.getElementById('previewTotal');
  const submitBtn= document.getElementById('btnSubmit');

  function rupiah(n){ return 'Rp ' + (n||0).toLocaleString('id-ID'); }

  // Bagi rata; sisa (jika ada) masuk ke item terakhir
  function splitAmount(total, n, index, lastIndex){
    const base = Math.floor(total / n);
    const sisa = total - (base * n);
    return index === lastIndex ? base + sisa : base;
  }

  function renderPreview(planKey){
    const labels = PREVIEWS[planKey] || [];
    const n = labels.length > 0 ? labels.length : Math.max(1, parseInt(planKey,10) || 1);

    ulList.innerHTML = '';
    labels.forEach((label, i) => {
      const amt = splitAmount(TOTAL, n, i, labels.length - 1);
      const li  = document.createElement('li');
      li.innerHTML = `
        <span class="month-pill">${label ?? ''}</span>
        <span class="amount">${rupiah(amt)}</span>
      `;
      ulList.appendChild(li);
    });

    pvTag.textContent = planKey ? (planKey + 'x') : '—';
    pvTotal.textContent = rupiah(TOTAL);
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
    }
  }

  cards.forEach(card => {
    card.addEventListener('click', ()=>{
      const input = card.querySelector('input[type=radio]');
      if(!input.checked){
        input.checked = true;
        input.dispatchEvent(new Event('change'));
      }
    });
  });

  radioEls.forEach(r => r.addEventListener('change', syncSelectedUI));

  // Init
  syncSelectedUI();
</script>
@endsection
