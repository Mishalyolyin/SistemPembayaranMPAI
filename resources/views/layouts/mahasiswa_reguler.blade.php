{{-- resources/views/layouts/mahasiswa_reguler.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>@yield('title', 'Dashboard Mahasiswa Reguler')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">

  {{-- Bootstrap & Icons --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body { font-family: 'Segoe UI', sans-serif; background:#f5f5f5; margin:0; }

    /* NAVBAR */
    .navbar-dark .navbar-brand { color:#fff; }

    /* SIDEBAR */
    .sidebar { background:#004225; min-height:100vh; padding:20px; color:#fff; font-size:16px; }
    .sidebar h5 { font-weight:700; font-size:18px; margin-bottom:30px; }
    .sidebar a { display:block; padding:10px 0 10px 10px; color:#fff; text-decoration:none; font-weight:500; border-radius:6px; transition:.2s; }
    .sidebar a:hover { color:#caffbf; }
    .sidebar a.active-link { background:#fff; color:#004225 !important; font-weight:600; }

    /* MAIN */
    .main { padding:30px; }

    /* KOMPONEN */
    .invoice-table th { background:#56ab2f; color:#fff; }
    .hover-bg:hover { background:#f1f1f1; }

    /* Dropdown profil */
    .profile-wrap { position:relative; }
    .profile-btn  { color:#fff; font-weight:700; }
    .profile-menu {
      position:absolute; top: calc(100% + 6px); left:50%; transform:translateX(-50%);
      width: 90%; z-index:1050; background:#fff; border-radius:.5rem; box-shadow:0 8px 22px rgba(0,0,0,.15);
    }

    /* Responsif */
    @media (max-width: 991.98px){
      .sidebar { min-height:auto; border-radius:12px; margin:12px; padding:16px; }
      .main { padding:16px; }
      .profile-menu { width:100%; left:0; transform:none; }
    }
    @media (max-width: 576px){
      .invoice-table td.text-nowrap { white-space:normal !important; }
      .invoice-table .btn { margin-bottom:6px; }
    }
  </style>

  @stack('styles')
</head>
<body>

  {{-- NAVBAR --}}
  <nav class="navbar navbar-expand-lg navbar-dark" style="background:#56ab2f;">
    <div class="container-fluid">
      <div class="d-flex align-items-center">
        <img src="{{ asset('images/unissula.png') }}" alt="Logo" width="35" height="35" class="me-2">
        <span class="navbar-brand mb-0 h1">Magister Pendidikan Agama Islam</span>
      </div>

      {{-- Toggler sidebar (mobile) --}}
      <button class="navbar-toggler d-lg-none ms-auto me-2" type="button"
              data-bs-toggle="collapse" data-bs-target="#sideMenu"
              aria-controls="sideMenu" aria-expanded="false" aria-label="Toggle navigasi">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="d-flex align-items-center">
        @php
          $authReg = auth('mahasiswa_reguler')->user();
          $user = $mahasiswaReguler ?? $mahasiswa ?? $authReg ?? auth()->user();
          $displayName = $user->nama ?? 'Mahasiswa';
        @endphp
        <span class="text-white me-3">{{ $displayName }}</span>
        @if(\Illuminate\Support\Facades\Route::has('logout'))
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="btn btn-outline-light btn-sm">Logout</button>
          </form>
        @endif
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="row">
      {{-- SIDEBAR: collapse di mobile, tampil tetap ≥ md --}}
      <div class="col-12 col-md-3 col-lg-2 p-0">
        <div id="sideMenu" class="sidebar collapse d-md-block">
          <h5 class="px-2 pt-2">Mahasiswa Reguler</h5>

          {{-- Foto & Dropdown Profil --}}
          <div class="text-center mb-3 profile-wrap">
            <img
              src="{{ ($user && $user->foto) ? asset('storage/profil/'.$user->foto) : asset('images/profile-default.png') }}"
              class="rounded-circle border border-white" width="80" height="80" style="object-fit:cover;" alt="Foto Profil">
            <div class="mt-2 d-inline-block w-100 text-center">
              <button id="profileToggle" class="btn btn-sm profile-btn w-75" type="button"
                      aria-expanded="false" aria-controls="profileMenu">
                {{ $user->nama ?? 'Mahasiswa' }} <span>▾</span>
              </button>
              <div id="profileMenu" class="profile-menu d-none">
                @php
                  $editProfilRoute = null;
                  foreach (['mahasiswa_reguler.edit.profil','reguler.profil.edit'] as $r) {
                    if (\Illuminate\Support\Facades\Route::has($r)) { $editProfilRoute = $r; break; }
                  }
                @endphp
                @if($editProfilRoute)
                  <a href="{{ route($editProfilRoute) }}" class="d-block text-dark small py-2 px-3 rounded hover-bg">
                    <i class="bi bi-person-gear me-1"></i> Edit Profil
                  </a>
                @endif
                @if(\Illuminate\Support\Facades\Route::has('logout'))
                  <form method="POST" action="{{ route('logout') }}" class="mt-1 px-2 pb-2">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger w-100">
                      <i class="bi bi-box-arrow-right me-1"></i> Keluar
                    </button>
                  </form>
                @endif
              </div>
            </div>
          </div>

          {{-- Menu --}}
          @php
            $dashHref = \Illuminate\Support\Facades\Route::has('mahasiswa_reguler.dashboard')
                        ? route('mahasiswa_reguler.dashboard')
                        : (\Illuminate\Support\Facades\Route::has('reguler.dashboard')
                            ? route('reguler.dashboard') : url('/reguler/dashboard'));

            $invoiceHref = \Illuminate\Support\Facades\Route::has('mahasiswa_reguler.invoice.index')
                        ? route('mahasiswa_reguler.invoice.index')
                        : (\Illuminate\Support\Facades\Route::has('reguler.invoices.index')
                            ? route('reguler.invoices.index') : url('/reguler/invoices'));

            $angsHref = null;
            foreach (['mahasiswa_reguler.angsuran.form','reguler.angsuran.create','reguler.angsuran.form'] as $cand) {
              if (\Illuminate\Support\Facades\Route::has($cand)) { $angsHref = route($cand); break; }
            }

            $isDashActive = request()->routeIs('mahasiswa_reguler.dashboard') || request()->routeIs('reguler.dashboard');
            $isInvActive  = request()->routeIs('mahasiswa_reguler.invoice.*') || request()->routeIs('reguler.invoices.*') || request()->routeIs('reguler.invoice.*');
            $isAngsActive = request()->routeIs('mahasiswa_reguler.angsuran.*') || request()->routeIs('reguler.angsuran.*');
          @endphp

          <div class="mt-4">
            <a href="{{ $dashHref }}" class="{{ $isDashActive ? 'active-link' : '' }}">
              <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>

            <a href="{{ $invoiceHref }}" class="{{ $isInvActive ? 'active-link' : '' }}">
              <i class="bi bi-receipt me-2"></i> Invoice
            </a>

            @if($user && empty($user->angsuran) && $angsHref)
              <a href="{{ $angsHref }}" class="{{ $isAngsActive ? 'active-link' : '' }}">
                <i class="bi bi-wallet2 me-2"></i> Pilih Angsuran
              </a>
            @endif
          </div>
        </div>
      </div>

      {{-- CONTENT --}}
      <div class="col-12 col-md-9 col-lg-10 main">
        @includeWhen(View::exists('partials.semester'), 'partials.semester')
        @yield('content')
      </div>
    </div>
  </div>

  {{-- JS --}}
  <script>
    // Toggle dropdown profil
    (function(){
      const btn  = document.getElementById('profileToggle');
      const menu = document.getElementById('profileMenu');
      if (btn && menu) {
        btn.addEventListener('click', function(e){
          e.stopPropagation();
          const wasHidden = menu.classList.contains('d-none');
          menu.classList.toggle('d-none', !wasHidden);
          btn.setAttribute('aria-expanded', wasHidden ? 'true' : 'false');
        });
        document.addEventListener('click', function(e){
          if (!menu.classList.contains('d-none') && !menu.contains(e.target) && !btn.contains(e.target)) {
            menu.classList.add('d-none');
            btn.setAttribute('aria-expanded','false');
          }
        });
      }
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
