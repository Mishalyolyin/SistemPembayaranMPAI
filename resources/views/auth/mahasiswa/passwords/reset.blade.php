{{-- resources/views/auth/mahasiswa/passwords/reset.blade.php --}}
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Reset Password – Mahasiswa (RPL)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  {{-- Bootstrap 5 + Icons --}}
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    :root{
      --bg1:#0f2027; --bg2:#203a43; --bg3:#2c5364;
      --glass:rgba(255,255,255,.16); --bglass:rgba(255,255,255,.35);
      --focus:#34d399; --focus2:#22c55e;
    }
    html,body{height:100%}
    body{
      min-height:100vh;
      background:
        radial-gradient(1100px 650px at 10% 10%, rgba(31,41,55,.25), transparent 60%) no-repeat,
        radial-gradient(900px 600px at 90% 0%, rgba(34,197,94,.2), transparent 55%) no-repeat,
        linear-gradient(135deg, var(--bg1), var(--bg2) 50%, var(--bg3));
      display:flex; align-items:center; justify-content:center; padding:24px;
      font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial;
    }
    .shell{max-width: 560px; width:100%}
    .cardx{
      background: var(--glass);
      backdrop-filter: blur(14px) saturate(160%);
      -webkit-backdrop-filter: blur(14px) saturate(160%);
      border:1px solid var(--bglass);
      border-radius:18px; overflow:hidden;
      box-shadow: 0 20px 60px rgba(0,0,0,.28);
      animation: pop .35s ease;
    }
    @keyframes pop{from{transform:translateY(6px);opacity:0}to{transform:translateY(0);opacity:1}}

    .head{display:flex;align-items:center;justify-content:space-between;
      padding:18px 22px;border-bottom:1px solid rgba(255,255,255,.28);
      background:linear-gradient(135deg, rgba(255,255,255,.22), rgba(255,255,255,.06))}
    .title{color:#f8fafc;font-weight:800;margin:0;letter-spacing:.3px;font-size:1.15rem}
    .btn-ghost{color:#e5e7eb;border-color:rgba(255,255,255,.35)!important;border-radius:12px}
    .btn-ghost:hover{background:#fff;color:#111827;border-color:#fff!important}

    .content{padding:22px;background:linear-gradient(180deg, rgba(255,255,255,.14), rgba(255,255,255,.06))}
    .alert-soft{background:rgba(255,255,255,.8);border:1px solid rgba(255,255,255,.95);border-radius:12px;color:#0f172a}

    .form-label{color:#0f172a;font-weight:600}
    .form-control{background:rgba(255,255,255,.92);border:1px solid rgba(2,6,23,.08);border-radius:12px}
    .form-control:focus{background:#fff;border-color:#86efac;box-shadow:0 0 0 .2rem rgba(34,197,94,.22)}
    .input-group-text{background:rgba(255,255,255,.92);border:1px solid rgba(2,6,23,.08);border-radius:12px}

    .btn-save{
      border-radius:12px;font-weight:800;padding:.85rem 1rem;
      background:linear-gradient(135deg,var(--focus),var(--focus2));
      border:none;color:#062a15;box-shadow:0 10px 30px rgba(34,197,94,.22)
    }
    .btn-save:hover{filter:brightness(1.03);transform:translateY(-1px)}
    .btn-outline{border-radius:12px;font-weight:700;color:#e5e7eb;border-color:#e5e7eb}
    .btn-outline:hover{background:#e5e7eb;color:#111827}

    .pw-hint{font-size:.9rem;color:#0f172a;opacity:.85}
  </style>
</head>
<body>
  <div class="shell">
    <div class="cardx" role="region" aria-labelledby="page-title">
      <div class="head">
        <h1 id="page-title" class="title">
          <i class="bi bi-shield-lock me-2"></i>Reset Password (Mahasiswa RPL)
        </h1>
        <a href="{{ route('login') }}" class="btn btn-sm btn-ghost">
          <i class="bi bi-box-arrow-in-left me-1"></i>Login
        </a>
      </div>

      <div class="content">
        @if ($errors->any())
          <div class="alert alert-danger alert-soft" role="alert" aria-live="assertive">
            <strong>Periksa input berikut:</strong>
            <ul class="mb-0 mt-2">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
          </div>
        @endif

        <form method="POST" action="{{ route('mahasiswa.password.update') }}" class="needs-validation" novalidate>
          @csrf
          <input type="hidden" name="token" value="{{ $token }}">

          <div class="mb-3">
            <label class="form-label">Email</label>
            <input
              type="email"
              name="email"
              class="form-control @error('email') is-invalid @enderror"
              value="{{ old('email', $email ?? '') }}"
              required
              autocomplete="email"
              inputmode="email"
            >
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
          </div>

          <div class="mb-3">
            <label class="form-label">Password Baru</label>
            <div class="input-group">
              <input
                type="password"
                name="password"
                id="pwd"
                class="form-control @error('password') is-invalid @enderror"
                autocomplete="new-password"
                required
                minlength="8"
              >
              <span class="input-group-text" role="button" onclick="toggle('pwd','eye1')">
                <i id="eye1" class="bi bi-eye"></i>
              </span>
            </div>
            @error('password') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
            <div class="pw-hint mt-1">Minimal 8 karakter. Disarankan kombinasi huruf, angka, dan simbol.</div>
          </div>

          <div class="mb-3">
            <label class="form-label">Konfirmasi Password Baru</label>
            <div class="input-group">
              <input
                type="password"
                name="password_confirmation"
                id="pwd2"
                class="form-control"
                autocomplete="new-password"
                required
                minlength="8"
              >
              <span class="input-group-text" role="button" onclick="toggle('pwd2','eye2')">
                <i id="eye2" class="bi bi-eye"></i>
              </span>
            </div>
          </div>

          <div class="d-grid gap-2 d-sm-flex justify-content-end">
            <a href="{{ route('login') }}" class="btn btn-outline px-3">
              <i class="bi bi-x-circle me-1"></i>Batal
            </a>
            <button id="btnSave" class="btn btn-save px-3" type="submit">
              <i class="bi bi-check2-circle me-1"></i>Reset Password
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    function toggle(id, eyeId){
      const input = document.getElementById(id);
      const icon  = document.getElementById(eyeId);
      if(!input || !icon) return;
      input.type = input.type === 'password' ? 'text' : 'password';
      icon.classList.toggle('bi-eye'); icon.classList.toggle('bi-eye-slash');
    }
    // loading state saat submit valid
    document.getElementById('btnSave')?.addEventListener('click', function(){
      const form = this.closest('form');
      if(form && form.checkValidity()){
        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Menyimpan...';
      }
    });
  </script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
