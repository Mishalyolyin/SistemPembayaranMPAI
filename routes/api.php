<?php

use Illuminate\Support\Facades\Route;

// Stub sederhana biar provider nggak error.
// Hapus/ubah nanti kalau kamu benar-benar butuh API.
Route::get('/health', fn () => response()->json(['ok' => true]));
