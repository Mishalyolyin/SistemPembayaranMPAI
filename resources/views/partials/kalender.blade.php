{{-- resources/views/partials/kalender.blade.php --}}
<div class="card mb-4">
  <div class="card-header">🗓️ Kalender Akademik</div>
  <ul class="list-group list-group-flush">
    @forelse($kalenderEvents as $e)
      <li class="list-group-item d-flex justify-content-between">
        {{ \Carbon\Carbon::parse($e->tanggal)->format('d M Y') }}
        <span>{{ $e->judul_event }}</span>
      </li>
    @empty
      <li class="list-group-item text-center text-muted">Tidak ada event</li>
    @endforelse
  </ul>
</div>
