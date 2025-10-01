<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Login Mahasiswa</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  {{-- Bootstrap 5 + Icons --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --bg1:#0f2027; --bg2:#203a43; --bg3:#2c5364;
      --card-bg: rgba(255,255,255,.16);
      --border-glass: rgba(255,255,255,.35);
      --brand:#16a34a; /* emerald vibes */
    }
    *{box-sizing:border-box}

    /* Background: gradient + subtle animation */
    body{
      min-height:100vh; margin:0;
      background:
        radial-gradient(1000px 600px at 10% 10%, rgba(20,83,45,.45) 0%, transparent 60%) no-repeat,
        radial-gradient(900px 600px at 85% 0%, rgba(34,197,94,.25) 0%, transparent 55%) no-repeat,
        linear-gradient(135deg, var(--bg1), var(--bg2) 50%, var(--bg3));
      display:flex; align-items:center; justify-content:center;
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
      overflow-x:hidden;
    }
    .glow-orb{
      position:fixed; inset:auto auto -120px -120px; width:360px; height:360px; border-radius:50%;
      background: radial-gradient(circle at 30% 30%, rgba(34,197,94,.35), transparent 60%);
      filter: blur(30px); animation: floaty 12s ease-in-out infinite alternate;
      pointer-events:none;
    }
    .glow-orb.right{
      left:auto; right:-120px; bottom:-140px;
      background: radial-gradient(circle at 70% 70%, rgba(59,130,246,.28), transparent 60%);
      animation-delay: -2.5s;
    }
    @keyframes floaty{
      from{ transform: translateY(0) translateX(0) scale(1); }
      to{   transform: translateY(-30px) translateX(20px) scale(1.05); }
    }

    /* Card: glassmorphism */
    .card-login{
      width:100%; max-width: 440px;
      background: var(--card-bg);
      backdrop-filter: blur(14px) saturate(160%);
      -webkit-backdrop-filter: blur(14px) saturate(160%);
      border: 1px solid var(--border-glass);
      border-radius: 18px;
      box-shadow: 0 18px 60px rgba(0,0,0,.25);
      overflow:hidden;
    }
    .card-head{
      display:flex; align-items:center; justify-content:space-between;
      padding: 18px 22px;
      background: linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,.06));
      border-bottom: 1px solid rgba(255,255,255,.28);
    }
    .brand{
      font-weight: 900; letter-spacing:.3px; margin:0;
      color:#f8fafc; display:flex; align-items:center; gap:.5rem;
    }
    .brand .dot{ width:10px; height:10px; border-radius:999px; background: var(--brand); display:inline-block; box-shadow:0 0 12px rgba(16,185,129,.9); }

    .content{ padding: 22px; background: linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.06)); }

    .form-label{ font-weight: 700; color:#0f172a; }
    .form-control, .form-check-input{
      background: rgba(255,255,255,.92); border:1px solid rgba(15,23,42,.08); border-radius:12px;
    }
    .form-control:focus{
      background:#fff; border-color:#86efac;
      box-shadow: 0 0 0 .2rem rgba(34,197,94,.25);
    }
    .input-group-text{
      background: rgba(255,255,255,.92); border:1px solid rgba(15,23,42,.08); border-radius:12px;
    }
    .btn-primary{
      border-radius:12px; padding:.85rem 1rem; font-weight:800;
      background: linear-gradient(135deg, #34d399, #22c55e);
      border:none; color:#062a15;
      transition: transform .12s ease, filter .12s ease;
    }
    .btn-primary:hover{ filter: brightness(1.03); transform: translateY(-1px); }
    .btn-primary:active{ transform: translateY(0); }
    .btn-outline{
      border-radius:12px; font-weight:600; color:#e5e7eb; border-color:#e5e7eb;
    }
    .btn-outline:hover{ background:#e5e7eb; color:#111827; }

    .helper-links a{
      color:#e5e7eb; text-decoration:none; font-size:.9rem;
    }
    .helper-links a:hover{ text-decoration:underline; }

    .alert-soft{
      background: rgba(255,255,255,.82); border:1px solid rgba(255,255,255,.95);
      border-radius:12px; color:#0f172a;
    }

    .muted{ color:#e5e7eb; opacity:.9; font-size:.9rem; text-align:center; }
    .caps-hint{ font-size:.85rem; color:#7f1d1d; display:none; margin-top:.35rem; }

    /* Tiny loading spinner for submit */
    .spinner-border.spinner-border-sm{ margin-right:.5rem; display:none; }
    .btn-loading .spinner-border { display:inline-block; }
  </style>
</head>
<body>
  <div class="glow-orb"></div>
  <div class="glow-orb right"></div>

  <div class="card-login">
    <div class="card-head">
      <h1 class="brand fs-5">
        <span class="dot"></span>
        Login Mahasiswa
      </h1>
      <a href="{{ route('landing') }}" class="btn btn-sm btn-outline"><i class="bi bi-house-door me-1"></i> Home</a>
    </div>

    <div class="content">
      {{-- Flash --}}
      @if(session('success'))
        <div class="alert alert-success alert-soft d-flex align-items-center mb-3">
          <i class="bi bi-check-circle-fill me-2"></i><div>{{ session('success') }}</div>
        </div>
      @endif
      @if($errors->any())
        <div class="alert alert-danger alert-soft d-flex align-items-center mb-3">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><div>{{ $errors->first() }}</div>
        </div>
      @endif

      <form method="POST" action="{{ route('login.submit') }}" id="loginForm" novalidate>
        @csrf

        <div class="mb-3">
          <label class="form-label">Email atau NIM</label>
          <input
            type="text"
            name="email"
            class="form-control"
            placeholder="cth: 22.11.1234 atau user@mail.com"
            value="{{ old('email') }}"
            required
            autofocus
          >
        </div>

        <div class="mb-2">
          <label class="form-label">Password</label>
          <div class="input-group">
            <input
              type="password"
              name="password"
              id="password"
              class="form-control"
              required
              autocomplete="current-password"
            >
            <span class="input-group-text" role="button" id="togglePwd">
              <i class="bi bi-eye" id="eyeIcon"></i>
            </span>
          </div>
          <div id="capsHint" class="caps-hint">
            <i class="bi bi-exclamation-octagon-fill me-1"></i>Caps Lock aktif
          </div>
        </div>

        <div class="mb-3 form-check">
          <input type="checkbox" class="form-check-input" id="remember" name="remember" {{ old('remember') ? 'checked' : '' }}>
          <label class="form-check-label" for="remember">Ingat saya</label>
        </div>

        <button class="btn btn-primary w-100" type="submit" id="btnSubmit">
          <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
          Masuk
        </button>
      </form>

      <div class="d-flex justify-content-between align-items-center mt-3 helper-links">
        <a href="{{ route('mahasiswa.password.request') }}"><i class="bi bi-question-circle me-1"></i>Lupa password RPL?</a>
        <a href="{{ route('reguler.password.request') }}"><i class="bi bi-question-circle me-1"></i>Lupa password Reguler?</a>
      </div>

      <div class="muted mt-3">Form ini dipakai bersama untuk <strong>RPL</strong> & <strong>Reguler</strong>.</div>
    </div>
  </div>

  <script>
    (function(){
      const pwd = document.getElementById('password');
      const toggle = document.getElementById('togglePwd');
      const eye = document.getElementById('eyeIcon');
      const caps = document.getElementById('capsHint');
      const form = document.getElementById('loginForm');
      const btn = document.getElementById('btnSubmit');

      // toggle password
      toggle?.addEventListener('click', ()=>{
        if (!pwd) return;
        const isHidden = pwd.type === 'password';
        pwd.type = isHidden ? 'text' : 'password';
        eye.classList.toggle('bi-eye', !isHidden);
        eye.classList.toggle('bi-eye-slash', isHidden);
      });

      // caps lock detector
      pwd?.addEventListener('keyup', (e)=>{
        if (!caps) return;
        const on = e.getModifierState && e.getModifierState('CapsLock');
        caps.style.display = on ? 'block' : 'none';
      });
      pwd?.addEventListener('keydown', (e)=>{
        if (!caps) return;
        const on = e.getModifierState && e.getModifierState('CapsLock');
        caps.style.display = on ? 'block' : 'none';
      });

      // loading state on submit
      form?.addEventListener('submit', ()=>{
        btn?.classList.add('btn-loading');
        btn?.setAttribute('disabled', 'disabled');
      });
    })();
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
