{{-- resources/views/layouts/admin.blade.php --}}
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>@yield('title', 'Dashboard Admin')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  {{-- Bootstrap CSS --}}
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
  >
  {{-- Bootstrap Icons --}}
  <link
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
    rel="stylesheet"
  >

  <style>
    body { font-family: 'Segoe UI', sans-serif; background-color: #f0f2f5; margin: 0; }
    .sidebar { width: 270px; height: 100vh; background-color: #024d2e;
      padding: 30px 20px; display: flex; flex-direction: column; color:#fff;
      position:fixed; z-index: 1000;
    }
    .sidebar .logo { display:flex; align-items:center; gap:10px; margin-bottom:30px; }
    .sidebar .logo img { width:50px; }
    .sidebar .logo span { font-size:16px; font-weight:bold; color:#fff; line-height:1.3; }
    .sidebar a { display:flex; align-items:center; gap:12px;
      padding:12px 8px; color:#fff; text-decoration:none; border-radius:6px;
      margin-bottom:4px; transition: background-color .2s;
    }
    .sidebar a:hover { background-color: rgba(255,255,255,0.15); }
    .sidebar a.active { background-color: #fff; color: #024d2e; font-weight:bold; }
    .logout-btn { margin-top:auto; background:#fff; color:#024d2e; border:none;
      padding:10px; border-radius:6px; transition: background-color .2s;
    }
    .logout-btn:hover { background:#f0f0f0; }
    .topbar { margin-left:270px; background:#69c081; height:56px;
      padding:0 20px; display:flex; align-items:center; justify-content:space-between;
    }
    .toggle-btn { display:none; background:transparent; border:none;
      font-size:24px; color:#fff;
    }
    .main-content { margin-left:270px; padding:20px; }
    @media (max-width:768px) {
      .sidebar { transform:translateX(-100%); }
      .sidebar.show { transform:translateX(0); }
      .topbar, .main-content { margin-left:0; }
      .toggle-btn { display:inline-block; }
    }
  </style>

  @stack('styles')
</head>
<body>
  {{-- Sidebar --}}
  <div class="sidebar" id="sidebar">
    <div class="logo">
      <img src="{{ asset('images/unissula.png') }}" alt="Logo">
      <span>Magister<br>Pendidikan Agama Islam</span>
    </div>
    <a href="{{ route('admin.dashboard') }}"
       class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
      <i class="bi bi-grid"></i> Dashboard
    </a>
    <a href="{{ route('admin.invoices.index') }}"
       class="{{ request()->routeIs('admin.invoices.*') ? 'active' : '' }}">
      <i class="bi bi-file-earmark-text"></i> Verifikasi Tagihan
    </a>
    <a href="{{ route('admin.invoices-reguler.index') }}"
       class="{{ request()->routeIs('admin.invoices-reguler.*') ? 'active' : '' }}">
      <i class="bi bi-file-earmark-text-fill"></i> Tagihan Reguler
    </a>
    <a href="{{ route('admin.mahasiswa.index') }}"
       class="{{ request()->routeIs('admin.mahasiswa.*') ? 'active' : '' }}">
      <i class="bi bi-people"></i> Mahasiswa RPL
    </a>
    <a href="{{ route('admin.mahasiswa-reguler.index') }}"
       class="{{ request()->routeIs('admin.mahasiswa-reguler.*') ? 'active' : '' }}">
      <i class="bi bi-people-fill"></i> Mahasiswa Reguler
    </a>
    <a href="{{ route('admin.kalender.index') }}"
       class="{{ request()->routeIs('admin.kalender.*') ? 'active' : '' }}">
      <i class="bi bi-calendar3"></i> Kalender
    </a>

    <form action="{{ route('admin.logout') }}" method="POST">
      @csrf
      <button type="submit" class="logout-btn">
        <i class="bi bi-box-arrow-right"></i> Logout
      </button>
    </form>
  </div>

  {{-- Topbar --}}
  <div class="topbar">
    <button class="toggle-btn" onclick="toggleSidebar()">
      <i class="bi bi-list"></i>
    </button>
    <div class="text-white">
      Hi, {{ Auth::guard('admin')->user()->nama }}
    </div>
  </div>

  {{-- Partial semester (jika ada) --}}
  @include('partials.semester')

  {{-- Main content --}}
  <div class="main-content">
    @yield('content')
  </div>

  {{-- Inject all pushed modals --}}
  @stack('modals')

  {{-- Bootstrap JS Bundle (Popper + JS) --}}
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
  </script>

  <script>
    function toggleSidebar() {
      document.getElementById('sidebar').classList.toggle('show');
    }
  </script>

  @stack('scripts')
</body>
</html>
