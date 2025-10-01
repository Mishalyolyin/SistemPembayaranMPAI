{{-- resources/views/partials/kelulusan-banner.blade.php --}}
@php
  /**
   * Props dari include():
   * - $entity       : Mahasiswa | MahasiswaReguler (Wajib)
   * - $type         : 'reguler'|'rpl' (default 'reguler')
   * - $showActions  : bool (default false) -> tampilkan tombol admin
   * - $lulusRoute   : string|null (route POST untuk "✅ Sudah Lulus")
   * - $tolakRoute   : string|null (route POST untuk "❌ Tolak" - fallback)
   * - $extendRoute  : string|null (route GET ke form Perpanjangan - utama)
   */

  $type        = $type ?? 'reguler';
  $showActions = (bool)($showActions ?? false);

  try {
    $eligible = $type === 'reguler'
      ? \App\Services\Graduation::eligibleReg($entity)
      : \App\Services\Graduation::eligibleRPL($entity);
  } catch (\Throwable $e) {
    // kalau service error, sembunyikan banner biar UI aman
    $eligible = false;
  }

  $extendLabel = $type === 'rpl'
    ? '❌ Tolak → Tambah Semester'
    : '❌ Tolak → Tambah Bulan';
@endphp

@if ($eligible)
  <div class="alert alert-success d-flex flex-wrap align-items-center justify-content-between mt-3 gap-2">
    <div>✅ Syarat kelulusan terpenuhi (semua invoice Lunas + semester cukup).</div>

    @if ($showActions && auth('admin')->check())
      <div class="d-flex gap-2">
        {{-- ✅ Sudah Lulus (POST) --}}
        @if(!empty($lulusRoute))
          <form method="POST" action="{{ $lulusRoute }}" class="m-0 p-0">
            @csrf
            <button class="btn btn-success btn-sm">✅ Sudah Lulus</button>
          </form>
        @endif

        {{-- ❌ Tolak → Perpanjangan (GET). Jika extendRoute kosong, fallback ke POST tolakRoute --}}
        @if(!empty($extendRoute))
          <a href="{{ $extendRoute }}" class="btn btn-outline-danger btn-sm">{{ $extendLabel }}</a>
        @elseif(!empty($tolakRoute))
          <form method="POST" action="{{ $tolakRoute }}" class="m-0 p-0">
            @csrf
            <button class="btn btn-outline-danger btn-sm">{{ $extendLabel }}</button>
          </form>
        @endif
      </div>
    @endif
  </div>
@endif
