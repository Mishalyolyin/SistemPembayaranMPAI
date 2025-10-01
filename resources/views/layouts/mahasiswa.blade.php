{{-- resources/views/layouts/mahasiswa.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>@yield('title', 'Dashboard Mahasiswa')</title>
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
    .sidebar a { display:block; padding:10px 0 10px 10px; color:#fff; text-decoration:none; font-weight:500; border-radius:6px; transition:.3s; }
    .sidebar a:hover { color:#caffbf; }
    .sidebar a.active-link { background:#fff; color:#004225 !important; font-weight:600; }

    /* MAIN */
    .main { padding:30px; }

    /* TABEL */
    .invoice-table th { background:#56ab2f; color:#fff; }
    .hover-bg:hover { background:#f1f1f1; }

    /* Dropdown profil */
    .profile-menu { position:absolute; left:50%; transform:translateX(-50%); width:75%; z-index:1040; }
    .profile-btn { color:#fff; font-weight:700; }

    /* Mobile tweaks */
    @media (max-width: 767.98px){
      .main { padding:18px; }
      .sidebar { min-height:unset; border-radius:12px; margin:12px; padding:16px; }
      .profile-menu { width:92%; }
    }
    @media (max-width: 576px){
      .invoice-table td.text-nowrap { white-space: normal !important; }
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
        <img src="{{ asset('images/unissula.png') }}" alt="Logo UNISSULA" width="35" height="35" class="me-2">
        <span class="navbar-brand mb-0 h1">Magister Pendidikan Agama Islam</span>
      </div>

      {{-- Toggle sidebar (mobile) --}}
      <button class="navbar-toggler d-lg-none ms-auto me-2" type="button"
              data-bs-toggle="collapse" data-bs-target="#sideMenu"
              aria-controls="sideMenu" aria-expanded="false" aria-label="Buka/tutup menu samping">
        <span class="navbar-toggler-icon"></span>
      </button>

      @php
        use Illuminate\Support\Facades\Route;
        $authMhs = auth('mahasiswa')->user() ?? auth()->user();
        $logoutAvailable = Route::has('logout');
      @endphp

      <div class="d-flex align-items-center">
        <span class="text-white me-3">{{ $authMhs->nama ?? 'Mahasiswa' }}</span>
        @if($logoutAvailable)
          <form method="POST" action="{{ route('logout') }}" class="m-0">
            @csrf
            <button class="btn btn-outline-light btn-sm">Logout</button>
          </form>
        @endif
      </div>
    </div>
  </nav>

  <div class="container-fluid">
    <div class="row">
      {{-- SIDEBAR (collapse di mobile, tetap tampil ≥ md) --}}
      <div class="col-12 col-md-3 col-lg-2 p-0">
        <div id="sideMenu" class="sidebar collapse d-md-block" aria-label="Menu samping">
          <h5 class="px-2 pt-2">Mahasiswa</h5>

          @php
            $user = isset($mahasiswa) && $mahasiswa ? $mahasiswa : ($authMhs ?? null);

            // Pastikan link tidak bikin 500 bila route belum ada
            $dashRoute = Route::has('mahasiswa.dashboard') ? 'mahasiswa.dashboard' : null;

            $invRoute = Route::has('mahasiswa.invoice.index')
                        ? 'mahasiswa.invoice.index'
                        : (Route::has('mahasiswa.invoices.index') ? 'mahasiswa.invoices.index' : null);

            $angsRoute = Route::has('mahasiswa.angsuran.form') ? 'mahasiswa.angsuran.form' : null;

            $isDash = $dashRoute ? request()->routeIs($dashRoute) : false;
            $isInv  = request()->routeIs('mahasiswa.invoice.*') || request()->routeIs('mahasiswa.invoices.*');
            $isAngs = $angsRoute ? request()->routeIs('mahasiswa.angsuran.*') : false;
          @endphp

          {{-- Foto & Dropdown Profil --}}
          <div class="text-center mb-3 position-relative">
            <img
              src="{{ ($user && $user->foto) ? asset('storage/profil/'.$user->foto) : asset('images/profile-default.png') }}"
              class="rounded-circle border border-white" width="80" height="80" style="object-fit:cover;" alt="Foto Profil">
            <div class="mt-2 d-inline-block w-100 text-center">
              <button id="profileToggle" class="btn btn-sm profile-btn w-75" type="button"
                      aria-haspopup="true" aria-expanded="false" aria-controls="profileMenu">
                {{ $user->nama ?? 'Mahasiswa' }} <span aria-hidden="true">▾</span>
              </button>
              <div id="profileMenu" class="bg-white rounded shadow-sm text-start px-2 py-1 profile-menu d-none">
                @if(Route::has('mahasiswa.profil.edit'))
                  <a href="{{ route('mahasiswa.profil.edit') }}" class="d-block text-dark small py-1 px-2 rounded hover-bg">
                    <i class="bi bi-person-gear me-1"></i> Edit Profil
                  </a>
                @endif
                @if($logoutAvailable)
                  <form method="POST" action="{{ route('logout') }}" class="mt-1">
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
          <div class="mt-4">
            <a href="{{ $dashRoute ? route($dashRoute) : '#' }}"
               class="{{ $isDash ? 'active-link' : '' }} {{ $dashRoute ? '' : 'disabled' }}"
               @unless($dashRoute) aria-disabled="true" tabindex="-1" @endunless>
              <i class="bi bi-speedometer2 me-2"></i> Dashboard
            </a>

            <a href="{{ $invRoute ? route($invRoute) : '#' }}"
               class="{{ $isInv ? 'active-link' : '' }} {{ $invRoute ? '' : 'disabled' }}"
               @unless($invRoute) aria-disabled="true" tabindex="-1" @endunless>
              <i class="bi bi-receipt me-2"></i> Invoice
            </a>

            @if($user && empty($user->angsuran))
              <a href="{{ $angsRoute ? route($angsRoute) : '#' }}"
                 class="{{ $isAngs ? 'active-link' : '' }} {{ $angsRoute ? '' : 'disabled' }}"
                 @unless($angsRoute) aria-disabled="true" tabindex="-1" @endunless>
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
      if (btn && menu){
        btn.addEventListener('click', function(e){
          e.stopPropagation();
          const nowHidden = menu.classList.toggle('d-none'); // true = tersembunyi
          btn.setAttribute('aria-expanded', nowHidden ? 'false' : 'true');
        });
        document.addEventListener('click', function(e){
          if(!menu.classList.contains('d-none') && !menu.contains(e.target) && !btn.contains(e.target)){
            menu.classList.add('d-none');
            btn.setAttribute('aria-expanded', 'false');
          }
        });
      }
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  @stack('scripts')
</body>
</html>
