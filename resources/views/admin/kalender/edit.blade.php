{{-- resources/views/admin/kalender/edit.blade.php --}}
@extends('layouts.admin')

@section('title', 'Edit Event Kalender')

@section('content')
  <h4 class="mb-4">Edit Event Kalender Akademik</h4>

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

  <form action="{{ route('admin.kalender.update', $kalender) }}" method="POST">
    @csrf
    @method('PUT')

    <div class="mb-3">
      <label for="judul_event" class="form-label">Judul Event</label>
      <input
        type="text"
        id="judul_event"
        name="judul_event"
        class="form-control"
        value="{{ old('judul_event', $kalender->judul_event) }}"
        required>
    </div>

    <div class="mb-3">
      <label for="tanggal" class="form-label">Tanggal</label>
      <input
        type="date"
        id="tanggal"
        name="tanggal"
        class="form-control"
        value="{{ old('tanggal', $kalender->tanggal->format('Y-m-d')) }}"
        required>
    </div>

    <div class="mb-3">
      <label for="untuk" class="form-label">Untuk</label>
      <select id="untuk" name="untuk" class="form-select" required>
        <option value="all" {{ old('untuk', $kalender->untuk)==='all'?'selected':'' }}>Semua Mahasiswa</option>
        <option value="rpl" {{ old('untuk', $kalender->untuk)==='rpl'?'selected':'' }}>Mahasiswa RPL</option>
        <option value="reguler" {{ old('untuk', $kalender->untuk)==='reguler'?'selected':'' }}>Mahasiswa Reguler</option>
      </select>
    </div>

    <div class="d-flex">
      <button type="submit" class="btn btn-primary">Perbarui Event</button>
      <a href="{{ route('admin.kalender.index') }}" class="btn btn-secondary ms-2">Batal</a>
    </div>
  </form>
@endsection
