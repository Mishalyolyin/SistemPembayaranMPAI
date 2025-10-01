{{-- resources/views/layouts/landing.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>@yield('title', 'Landing Page | Pembayaran SKS')</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  {{-- Google Fonts --}}
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@600;700&display=swap" rel="stylesheet">
  {{-- Bootstrap & Icons --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    /* Reset & Global */
    html, body {
      margin: 0; padding: 0;
      width: 100%; overflow-x: hidden;
      background: #14532d;
      font-family: 'Poppins', sans-serif;
    }

    /* Navbar */
    .custom-navbar {
      background: rgb(68,145,17);
      padding: 1.8rem 1rem;
      height: 100px;
      transition: all .4s ease;
    }
    .custom-navbar .navbar-brand,
    .custom-navbar .nav-link {
      color: #fff !important;
    }
    .custom-navbar.scrolled {
      background: #fff !important;
      padding: .8rem 1rem;
      box-shadow: 0 2px 10px rgba(0,0,0,.1);
    }
    .custom-navbar.scrolled .navbar-brand,
    .custom-navbar.scrolled .nav-link {
      color: #14532d !important;
    }

    /* Hero Banner */
    .banner-container {
      padding-top: 100px;
      background: #14532d;
      overflow: hidden;
    }
    #heroBanner {
      max-height: 75vh;
    }
    #heroBanner .carousel-inner,
    #heroBanner .carousel-item {
      height: 75vh;
    }
    #heroBanner .carousel-inner img {
      height: 100%;
      width: auto;
      object-fit: cover;
      margin: 0 auto;
    }

    /* Sections */
    .kata-pengantar-section,
    .visi-misi-section {
      background: linear-gradient(to bottom, #14532d 90%, #0f4225 100%);
      color: #fff;
      padding: 5rem 1rem;
    }

    .kata-pengantar-section h2,
    .visi-misi-section .section-heading-yellow {
      color: #ffc107;
      text-align: center;
      margin-bottom: 2.5rem;
      font-size: 2.4rem;
    }

    .kata-pengantar-img {
      display: block;
      max-width: 1100px;
      width: 100%;
      margin: 0 auto 2.5rem;
      border-radius: 16px;
      box-shadow: 0 6px 30px rgba(0,0,0,.3);
    }

    .deskripsi {
      max-width: 1100px;
      margin: 0 auto;
      font-size: 1.5rem;
      line-height: 1.9;
      color: #f0f0f0;
      text-align: justify;
    }

    .visi-misi-img {
      width: 230px; height: 230px;
      object-fit: cover;
      border: 6px solid #fff;
      border-radius: 50%;
      box-shadow: 0 5px 20px rgba(0,0,0,.3);
      transition: transform .4s;
    }
    .visi-misi-img:hover {
      transform: scale(1.08);
    }
    .subheading-white {
      color: #fff;
      font-size: 1.8rem;
      margin-bottom: 1rem;
    }
    .visi-misi-text,
    .visi-misi-list {
      color: #f0f0f0;
      font-size: 1.3rem;
      line-height: 1.8;
    }

    /* Poster Section */
    #posterSection {
      background: #14532d;
      padding: 3rem 1rem;
      text-align: center;
    }
    .section-title {
      display: inline-block;
      color: #fff;
      font-size: 2.2rem;
      position: relative;
      padding-bottom: 1.5rem;
    }
    .section-title::after {
      content: '';
      position: absolute;
      bottom: 0; left: 0;
      width: 100%; height: 4px;
      background: #ffc107;
      border-radius: 2px;
    }
    .poster-row {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      gap: 2rem;
    }
    .poster-item {
      max-width: 320px;
      overflow: hidden;
      border-radius: 12px;
      box-shadow: 0 5px 20px rgba(0,0,0,.3);
      background: #fff;
      cursor: pointer;
    }
    .poster-item img {
      width: 100%;
      display: block;
    }

    /* Responsive tweaks */
    @media (max-width:768px) {
      .custom-navbar { padding: 1rem; }
      #heroBanner .carousel-inner img { height: 60vh; }
    }
  </style>

  @stack('styles')
</head>
<body>

  {{-- Navbar --}}
  @include('partials.landing-navbar')

  {{-- Main Content --}}
  <main>
    @yield('content')
  </main>

  {{-- Bootstrap JS --}}
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  @stack('scripts')
</body>
</html>
