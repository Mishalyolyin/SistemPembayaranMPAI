<nav id="mainNavbar" class="navbar navbar-expand-lg custom-navbar">
  <div class="container">
    <a class="navbar-brand text-white fw-bold" href="{{ route('landing') }}">Pembayaran SKS</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navCollapse">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div id="navCollapse" class="collapse navbar-collapse">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item me-2"><a class="nav-link text-white" href="{{ route('login') }}">Login Mahasiswa</a></li>
        <li class="nav-item">
          <a href="{{ route('admin.login') }}" class="btn btn-cta btn-admin"><i class="bi bi-gear"></i> Login Admin</a>
        </li>
      </ul>
    </div>
  </div>
</nav>
