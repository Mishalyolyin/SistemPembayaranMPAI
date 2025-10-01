{{-- resources/views/auth/mahasiswa/passwords/email.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Lupa Password – Mahasiswa (RPL)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  {{-- Bootstrap 5 + Icons --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --bg1:#0f2027; --bg2:#203a43; --bg3:#2c5364;
      --card:rgba(255,255,255,.16); --bglass:rgba(255,255,255,.35);
      --focus:#60a5fa; --focus2:#2563eb;
    }
    html,body{height:100%}
    body{
      min-height:100vh;
      background:
        radial-gradient(1200px 700px at 12% -10%, rgba(96,165,250,.18), transparent 60%) no-repeat,
        radial-gradient(900px 600px at 90% 0%, rgba(14,165,233,.16), transparent 55%) no-repeat,
        linear-gradient(135deg, var(--bg1), var(--bg2) 50%, var(--bg3));
      display:flex; align-items:center; justify-content:center; padding:24px;
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
    }
    .shell{width:100%; max-width: 560px;}
    .glass-card{
      background: var(--card);
      backdrop-filter: blur(14px) saturate(160%);
      -webkit-backdrop-filter: blur(14px) saturate(160%);
      border: 1px solid var(--bglass);
      border-radius: 18px; box-shadow: 0 20px 60px rgba(0,0,0,.28); overflow:hidden;
      animation: pop .35s ease;
    }
    @keyframes pop{from{transform:translateY(6px);opacity:.0}to{transform:translateY(0);opacity:1}}

    .card-head{
      display:flex; align-items:center; justify-content:space-between;
      padding: 18px 22px; border-bottom: 1px solid rgba(255,255,255,.28);
      background: linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,.06));
    }
    .headline{color:#f8fafc; margin:0; font-weight:800; letter-spacing:.3px; font-size:1.15rem}
    .btn-ghost{ color:#e5e7eb; border-color: rgba(255,255,255,.35)!important; border-radius:12px }
    .btn-ghost:hover{ color:#111827; background:#fff; border-color:#fff!important }

    .content{ padding:22px; background: linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.06)) }
    .alert-soft{
      background: rgba(255,255,255,.8);
      border:1px solid rgba(255,255,255,.95);
      border-radius:12px; color:#0f172a
    }

    .form-label{ color:#0f172a; font-weight:600 }
    .form-control{
      background: rgba(255,255,255,.92);
      border:1px solid rgba(2,6,23,.08);
      border-radius:12px; transition: box-shadow .15s ease, transform .06s ease;
    }
    .form-control:focus{
      background:#fff; border-color: var(--focus);
      box-shadow: 0 0 0 .2rem rgba(59,130,246,.22)
    }
    .form-control.is-invalid{ animation: shake .25s linear }
    @keyframes shake{25%{transform:translateX(2px)}50%{transform:translateX(-2px)}75%{transform:translateX(1px)}}

    .btn-primary{
      border-radius: 12px; padding:.85rem 1rem; font-weight:800;
      background: linear-gradient(135deg, var(--focus), var(--focus2));
      border:none; color:#f8fafc; box-shadow: 0 10px 30px rgba(37,99,235,.25)
    }
    .btn-primary:hover{ filter:brightness(1.03); transform: translateY(-1px) }
    .btn-primary:disabled{ opacity:.9 }

    .btn-outline{
      border-radius:12px; font-weight:700; color:#e5e7eb; border-color:#e5e7eb
    }
    .btn-outline:hover{ background:#e5e7eb; color:#111827 }

    .footer-note{ color:#e5e7eb; opacity:.9; text-align:center; font-size:.9rem; margin-top:12px }
  </style>
</head>
<body>
  <div class="shell">
    <div class="glass-card" role="region" aria-labelledby="page-title">
      <div class="card-head">
        <h1 id="page-title" class="headline">
          <i class="bi bi-envelope-check me-2"></i>Lupa Password (Mahasiswa RPL)
        </h1>
        <a href="{{ route('login') }}" class="btn btn-sm btn-ghost">
          <i class="bi bi-box-arrow-in-left me-1"></i>Login
        </a>
      </div>

      <div class="content">
        {{-- success --}}
        @if (session('status'))
          <div class="alert alert-success alert-soft d-flex align-items-center" role="status" aria-live="polite">
            <i class="bi bi-check-circle-fill me-2"></i><div>{{ session('status') }}</div>
          </div>
        @endif

        {{-- errors --}}
        @if ($errors->any())
          <div class="alert alert-danger alert-soft" role="alert" aria-live="assertive">
            <strong>Periksa input berikut:</strong>
            <ul class="mb-0 mt-2">
              @foreach ($errors->all() as $e) <li>{{ $e }}</li> @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="{{ route('mahasiswa.password.email') }}" class="needs-validation" novalidate>
          @csrf

          <div class="mb-3">
            <label class="form-label">Email terdaftar</label>
            <input
              type="email"
              name="email"
              class="form-control @error('email') is-invalid @enderror"
              value="{{ old('email') }}"
              placeholder="nama@email.com"
              required
              autofocus
              inputmode="email"
              autocomplete="email"
            >
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text text-light">Kami akan mengirim link reset ke email ini.</div>
          </div>

          <div class="d-grid gap-2 d-sm-flex justify-content-end">
            <a href="{{ route('login') }}" class="btn btn-outline px-3">
              <i class="bi bi-x-circle me-1"></i>Batal
            </a>
            <button id="btnSend" type="submit" class="btn btn-primary px-3">
              <i class="bi bi-send-check me-1"></i>Kirim Link Reset
            </button>
          </div>
        </form>

        <div class="footer-note">
          <i class="bi bi-shield-lock"></i> Mode DEV: email dikirim ke Mailtrap / log file.
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // one-click submit → set loading state
    document.getElementById('btnSend')?.addEventListener('click', function(e){
      const form = this.closest('form');
      if (form && form.checkValidity()) {
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Mengirim...';
      }
    });
  </script>
</body>
</html>
