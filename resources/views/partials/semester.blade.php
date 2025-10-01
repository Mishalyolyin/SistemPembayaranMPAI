@php
  // Ambil data dari variabel global (composer/Controller), bisa array/string/null
  $s = $semesterAktif ?? null;

  // Normalisasi: ambil kode semester dan periode apa pun bentuknya
  $kode    = is_array($s) ? ($s['kode']   ?? $s['name']  ?? null) : (is_string($s) ? $s : null);
  $start   = is_array($s) ? ($s['start']  ?? null) : null;
  $end     = is_array($s) ? ($s['end']    ?? null) : null;
  $periode = is_array($s) ? ($s['periode'] ?? (($start && $end) ? ($start.' - '.$end) : null)) : null;
@endphp

@if($kode || $periode)
  <div class="alert alert-primary text-center mb-4">
    <strong>Semester Aktif:</strong> {{ strtoupper($kode ?? '-') }}
    @if($periode)
      <br>
      <small>Periode {{ $periode }}</small>
    @endif
  </div>
@endif
