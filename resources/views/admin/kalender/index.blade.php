{{-- resources/views/admin/kalender/index.blade.php --}}
@extends('layouts.admin')

@section('title', 'Kalender Akademik')

@section('content')
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0">Kalender Akademik</h4>
    <a href="{{ route('admin.kalender.create') }}" class="btn btn-success">
      <i class="bi bi-plus-lg"></i> Tambah Event
    </a>
  </div>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  <div class="table-responsive">
    <table class="table table-hover hover-bg">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Tanggal</th>
          <th>Judul Event</th>
          <th>Untuk</th>
          <th>Aksi</th>
        </tr>
      </thead>
      <tbody>
        @forelse($events as $idx => $event)
          <tr>
            <td>{{ $idx + 1 }}</td>
            <td>{{ \Carbon\Carbon::parse($event->tanggal)->format('d M Y') }}</td>
            <td>{{ $event->judul_event }}</td>
            <td>{{ strtoupper($event->untuk) }}</td>
            <td>
              <a href="{{ route('admin.kalender.edit', $event) }}"
                 class="btn btn-sm btn-warning">
                <i class="bi bi-pencil-square"></i> Edit
              </a>
              <form action="{{ route('admin.kalender.destroy', $event) }}"
                    method="POST"
                    class="d-inline"
                    onsubmit="return confirm('Yakin hapus event ini?')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-danger">
                  <i class="bi bi-trash"></i> Hapus
                </button>
              </form>
            </td>
          </tr>
        @empty
          <tr>
            <td colspan="5" class="text-center">Belum ada event kalender</td>
          </tr>
        @endforelse
      </tbody>
    </table>
  </div>
@endsection
