{{-- resources/views/admin/kalender/create.blade.php --}}
@extends('layouts.admin')

@section('title', 'Tambah Event Kalender')

@section('content')
  <h4 class="mb-4">Tambah Event Kalender Akademik</h4>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif

  @if($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach($errors->all() as $err)
          <li>{{ $err }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form action="{{ route('admin.kalender.store') }}" method="POST">
    @csrf

    <div class="mb-3">
      <label for="judul_event" class="form-label">Judul Event</label>
      <input
        type="text"
        id="judul_event"
        name="judul_event"
        class="form-control"
        value="{{ old('judul_event') }}"
        required>
    </div>

    <div class="mb-3">
      <label for="tanggal" class="form-label">Tanggal</label>
      <input
        type="date"
        id="tanggal"
        name="tanggal"
        class="form-control"
        value="{{ old('tanggal') }}"
        required>
    </div>

    <div class="mb-3">
      <label for="untuk" class="form-label">Untuk</label>
      <select id="untuk" name="untuk" class="form-select" required>
        <option value="all" {{ old('untuk')==='all'?'selected':'' }}>Semua Mahasiswa</option>
        <option value="rpl" {{ old('untuk')==='rpl'?'selected':'' }}>Mahasiswa RPL</option>
        <option value="reguler" {{ old('untuk')==='reguler'?'selected':'' }}>Mahasiswa Reguler</option>
      </select>
    </div>

    <div class="d-flex">
      <button type="submit" class="btn btn-success">Simpan Event</button>
      <a href="{{ route('admin.kalender.index') }}" class="btn btn-secondary ms-2">Batal</a>
    </div>
  </form>
@endsection
