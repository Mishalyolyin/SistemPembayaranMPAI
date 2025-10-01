{{-- resources/views/admin/login.blade.php --}}
@extends('layouts.auth')

@section('title', 'Login Admin')

@section('content')
  <h3 class="text-center mb-3">Login Admin</h3>

  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if($errors->any())
    <div class="alert alert-danger">{{ $errors->first() }}</div>
  @endif

  {{-- PENTING: pakai route('admin.login.submit') untuk POST --}}
  <form action="{{ route('admin.login.submit') }}" method="POST" autocomplete="on">
    @csrf
    <div class="mb-3">
      <label class="form-label">Email Admin</label>
      <input type="email" name="email" class="form-control" required value="{{ old('email') }}">
    </div>
    <div class="mb-3">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-control" required>
    </div>
    <div class="mb-3 form-check">
      <input type="checkbox" class="form-check-input" id="remember" name="remember">
      <label class="form-check-label" for="remember">Ingat saya</label>
    </div>
    <button class="btn btn-primary w-100">Masuk</button>
  </form>
@endsection
