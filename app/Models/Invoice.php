<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $table = 'invoices';

    // 🔒 Whitelist kolom yang boleh di-mass assign
    protected $fillable = [
        'mahasiswa_id',
        'bulan',
        'jumlah',
        'status',
        'kode',
        'semester',
        'tahun_akademik',
        'jatuh_tempo',
        'bukti',
        'uploaded_at',
        'angsuran_ke',   // aman: controller sudah cek kolom sebelum set
        'va_cust_code',  // ✅ hanya cust_code yang kita isi saat create
    ];

    // ❌ JANGAN pakai $guarded = [] (itu bikin semua kolom bisa diisi).
    // Sengaja tidak memasukkan va_full, va_briva_no, va_expired_at ke $fillable.

    protected $casts = [
        'verified_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'uploaded_at'   => 'datetime',
        'jatuh_tempo'   => 'date',
        'va_expired_at' => 'datetime', // kalau kolomnya ada
    ];

    /** Relasi balik ke mahasiswa RPL */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(Mahasiswa::class, 'mahasiswa_id');
    }

    /** Accessor opsional: pakai 'jumlah' kalau 'nominal' kosong */
    public function getNominalFinalAttribute(): int
    {
        $n = $this->attributes['nominal'] ?? null;
        $j = $this->attributes['jumlah']  ?? null;
        return (int) ($n ?? $j ?? 0);
    }
}
