{{-- resources/views/mahasiswa/reguler/edit-profil.blade.php --}}
@extends('layouts.mahasiswa_reguler')
@section('title', 'Edit Profil Mahasiswa Reguler')

@push('styles')
<style>
  :root{
    --bg1:#0f2027;
    --bg2:#203a43;
    --bg3:#2c5364;
    --card-bg: rgba(255,255,255,0.18);
    --border-glass: rgba(255,255,255,0.35);
  }
  .page-wrap{
    min-height: calc(100vh - 120px);
    background: linear-gradient(135deg, var(--bg1), var(--bg2) 50%, var(--bg3));
    padding: 32px 16px;
    display:flex; align-items:center; justify-content:center;
  }
  .shell{ max-width: 960px; width:100%; margin:auto; }
  .glass-card{
    background: var(--card-bg);
    backdrop-filter: blur(12px) saturate(140%);
    -webkit-backdrop-filter: blur(12px) saturate(140%);
    border: 1px solid var(--border-glass);
    border-radius: 18px;
    box-shadow: 0 18px 60px rgba(0,0,0,0.25);
    overflow: hidden;
  }
  .card-head{
    background: linear-gradient(135deg, rgba(255,255,255,0.22), rgba(255,255,255,0.06));
    border-bottom: 1px solid rgba(255,255,255,0.25);
    padding: 18px 22px;
  }
  .headline{ color:#f8fafc; font-weight:700; letter-spacing:.3px; margin:0; }
  .btn-ghost{ color:#e5e7eb; border-color: rgba(255,255,255,0.35)!important; }
  .btn-ghost:hover{ color:#111827; background:#ffffff; border-color:#ffffff!important; }

  .content{ padding: 22px; background: linear-gradient(180deg, rgba(255,255,255,0.18), rgba(255,255,255,0.06)); }
  .pfp{
    width: 140px; height: 140px; object-fit: cover;
    border-radius: 50%;
    border: 4px solid rgba(255,255,255,.85);
    box-shadow: 0 10px 30px rgba(0,0,0,.2);
    transition: transform .25s ease;
  }
  .pfp:hover{ transform: scale(1.02); }

  .badge-soft{
    background: rgba(255,255,255,.25);
    color:#0f172a;
    border:1px solid rgba(255,255,255,.4);
    border-radius: 999px;
    padding:.25rem .6rem;
    font-size:.8rem;
  }

  .form-label{ color:#0f172a; font-weight:600; }
  .form-control, .form-select{
    background: rgba(255,255,255,.85);
    border-radius: 12px;
    border:1px solid rgba(15,23,42,.08);
  }
  .form-control:focus{
    background:#fff; border-color:#86efac;
    box-shadow: 0 0 0 .2rem rgba(34,197,94,.2);
  }
  .divider{ height:1px; background: rgba(255,255,255,.45); margin: 20px 0; }
  .pw-hint{ font-size:.85rem; color:#374151; }
  .btn-save{
    border-radius: 12px; padding:.8rem 1rem; font-weight:700;
    background: linear-gradient(135deg, #34d399, #22c55e);
    border: none; color:#062a15; transition: transform .15s ease, filter .15s ease;
  }
  .btn-save:hover{ filter: brightness(1.03); transform: translateY(-1px); }
  .btn-outline{
    border-radius: 12px; font-weight:600; color:#e5e7eb; border-color:#e5e7eb;
  }
  .btn-outline:hover{ background:#e5e7eb; color:#111827; }
  .alert-soft{
    background: rgba(255,255,255,.6);
    border:1px solid rgba(255,255,255,.8);
    border-radius: 12px; color:#0f172a;
  }
  .footer-note{
    color:#e5e7eb; font-size:.85rem; text-align:center; margin-top:14px; opacity:.85;
  }

  /* show/hide password */
  .has-eye{ position:relative; }
  .has-eye > input{ padding-right:42px; }
  .pw-eye-btn{
    position:absolute; right:.5rem; top:50%; transform:translateY(-50%);
    border:0; background:transparent; padding:0 .25rem; line-height:1;
    color:#6b7280; cursor:pointer;
  }
  .pw-eye-btn:hover{ color:#111827; }
</style>
@endpush

@section('content')
  <div class="mb-3 d-flex justify-content-between align-items-center">
    <h4 class="mb-0 text-light">Edit Profil Mahasiswa Reguler</h4>
    <a href="{{ route('mahasiswa_reguler.dashboard') }}" class="btn btn-sm btn-secondary">
      <i class="bi bi-arrow-left me-1"></i>Kembali
    </a>
  </div>

  <div class="page-wrap">
    <div class="shell">
      <div class="glass-card">
        <div class="card-head d-flex align-items-center justify-content-between">
          <h1 class="headline fs-4 mb-0">
            <i class="bi bi-person-gear me-2"></i>Kelola Profil
          </h1>
          <a href="{{ route('mahasiswa_reguler.dashboard') }}" class="btn btn-sm btn-ghost">
            <i class="bi bi-grid me-1"></i> Dashboard
          </a>
        </div>

        <div class="content">
          {{-- Flash --}}
          @if (session('success'))
            <div class="alert alert-success alert-soft d-flex align-items-center" role="alert">
              <i class="bi bi-check-circle-fill me-2"></i>
              <div>{{ session('success') }}</div>
            </div>
          @elseif (session('error'))
            <div class="alert alert-danger alert-soft d-flex align-items-center" role="alert">
              <i class="bi bi-x-circle-fill me-2"></i>
              <div>{{ session('error') }}</div>
            </div>
          @endif

          {{-- Errors --}}
          @if ($errors->any())
            <div class="alert alert-danger alert-soft">
              <strong>Periksa input berikut:</strong>
              <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $e)
                  <li>{{ $e }}</li>
                @endforeach
              </ul>
            </div>
          @endif

          @php
            $m = ($mahasiswaReguler ?? $mahasiswa ?? auth()->guard('mahasiswa_reguler')->user());
          @endphp

          <form action="{{ route('mahasiswa_reguler.update.profil') }}" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate>
            @csrf
            @method('PUT')

            {{-- Header --}}
            <div class="d-flex align-items-center gap-3 mb-3">
              <img id="previewFotoReg"
                   src="{{ $m?->foto ? asset('storage/profil_reguler/'.$m->foto) : 'https://images.unsplash.com/photo-1527980965255-d3b416303d12?q=80&w=300&auto=format&fit=crop' }}"
                   alt="Foto Profil" class="pfp">
              <div>
                <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                  <span class="badge-soft"><i class="bi bi-hash me-1"></i>NIM: {{ $m?->nim }}</span>
                  <span class="badge-soft"><i class="bi bi-person-badge me-1"></i>{{ $m?->nama }}</span>
                  @if (!empty($m?->angkatan))
                    <span class="badge-soft"><i class="bi bi-mortarboard me-1"></i>Angkatan {{ $m?->angkatan }}</span>
                  @endif
                </div>
                <small class="text-light-50 d-block" style="color:#e5e7eb;">Perbarui data kontak & keamanan akunmu di sini.</small>
              </div>
            </div>

            <div class="divider"></div>

            {{-- Foto --}}
            <div class="mb-3">
              <label class="form-label">Foto Profil <span class="text-muted">(opsional)</span></label>
              <input type="file" name="foto" id="fotoReg" class="form-control @error('foto') is-invalid @enderror" accept="image/*">
              @error('foto') <div class="invalid-feedback">{{ $message }}</div> @enderror
              <div class="form-text text-light" style="opacity:.9">Format: JPG/PNG, maks 2 MB.</div>
            </div>

            {{-- Identitas (readonly) --}}
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Nama</label>
                <input type="text" class="form-control" value="{{ $m?->nama }}" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">NIM</label>
                <input type="text" class="form-control" value="{{ $m?->nim }}" readonly>
              </div>
            </div>

            {{-- Kontak --}}
            <div class="row g-3 mt-1">
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                       value="{{ old('email', $m?->email) }}" placeholder="nama@email.com">
                @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-md-6">
                <label class="form-label">No. HP</label>
                <input type="text" name="no_hp" class="form-control @error('no_hp') is-invalid @enderror"
                       value="{{ old('no_hp', $m?->no_hp) }}" placeholder="08xxxxxxxxxx">
                @error('no_hp') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
              <div class="col-12">
                <label class="form-label">Alamat</label>
                <textarea name="alamat" rows="2" class="form-control @error('alamat') is-invalid @enderror"
                          placeholder="Alamat domisili">{{ old('alamat', $m?->alamat) }}</textarea>
                @error('alamat') <div class="invalid-feedback">{{ $message }}</div> @enderror
              </div>
            </div>

            <div class="divider"></div>

            {{-- Password (opsional) --}}
            <div class="d-flex align-items-center justify-content-between">
              <h5 class="mb-0 text-light" style="color:#f8fafc;">Keamanan Akun</h5>
              <button class="btn btn-sm btn-outline" type="button" data-bs-toggle="collapse" data-bs-target="#ubahPasswordReg">
                <i class="bi bi-shield-lock me-1"></i> Ubah Password (Opsional)
              </button>
            </div>

            <div id="ubahPasswordReg" class="collapse mt-3">
              <div class="alert alert-info alert-soft d-flex align-items-start">
                <i class="bi bi-info-circle me-2"></i>
                <div>
                  Kosongkan bagian ini jika tidak ingin mengganti password.
                  <div class="pw-hint mt-1">Minimal 6 karakter. Wajib isi <strong>Password Sekarang</strong> untuk verifikasi jika mengganti.</div>
                </div>
              </div>

              <div class="row g-3">
                <div class="col-12">
                  <label class="form-label">Password Sekarang</label>
                  <div class="has-eye">
                    <input type="password" name="current_password" id="reg_current_password"
                           class="form-control @error('current_password') is-invalid @enderror"
                           autocomplete="current-password">
                    <button type="button" class="pw-eye-btn toggle-pw" data-target="#reg_current_password" aria-label="Lihat password">
                      <i class="bi bi-eye"></i>
                    </button>
                    @error('current_password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Password Baru</label>
                  <div class="has-eye">
                    <input type="password" name="password" id="reg_new_password"
                           class="form-control @error('password') is-invalid @enderror"
                           autocomplete="new-password">
                    <button type="button" class="pw-eye-btn toggle-pw" data-target="#reg_new_password" aria-label="Lihat password baru">
                      <i class="bi bi-eye"></i>
                    </button>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                  </div>
                </div>

                <div class="col-md-6">
                  <label class="form-label">Konfirmasi Password Baru</label>
                  <div class="has-eye">
                    <input type="password" name="password_confirmation" id="reg_new_password_confirmation"
                           class="form-control" autocomplete="new-password">
                    <button type="button" class="pw-eye-btn toggle-pw" data-target="#reg_new_password_confirmation" aria-label="Lihat konfirmasi password">
                      <i class="bi bi-eye"></i>
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div class="mt-4 d-grid gap-2 d-sm-flex justify-content-end">
              <a href="{{ route('mahasiswa_reguler.dashboard') }}" class="btn btn-outline px-3">
                <i class="bi bi-x-circle me-1"></i> Batal
              </a>
              <button type="submit" class="btn btn-save px-3">
                <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
              </button>
            </div>
          </form>

          <div class="footer-note">
            <i class="bi bi-lock"></i> Perubahan sensitif membutuhkan verifikasi password. Data kamu aman.
          </div>
        </div>
      </div>
    </div>
  </div>
@endsection

@push('scripts')
{{-- Live preview foto reguler --}}
<script>
  (function(){
    const input = document.getElementById('fotoReg');
    const preview = document.getElementById('previewFotoReg');
    if(!input || !preview) return;
    input.addEventListener('change', (e)=>{
      const file = e.target.files?.[0];
      if(!file || !file.type.startsWith('image/')) return;
      const reader = new FileReader();
      reader.onload = ev => { preview.src = ev.target.result; }
      reader.readAsDataURL(file);
    });
  })();
</script>

{{-- Toggle show/hide password --}}
<script>
  (function(){
    document.querySelectorAll('.toggle-pw').forEach(function(btn){
      btn.addEventListener('click', function(){
        const target = btn.getAttribute('data-target');
        const input  = document.querySelector(target);
        if(!input) return;
        const toText = input.type === 'password';
        input.type = toText ? 'text' : 'password';
        const icon = btn.querySelector('i');
        if(icon){
          icon.classList.toggle('bi-eye', !toText);
          icon.classList.toggle('bi-eye-slash', toText);
        }
        btn.setAttribute('aria-pressed', toText ? 'true' : 'false');
      });
    });
  })();
</script>
@endpush
