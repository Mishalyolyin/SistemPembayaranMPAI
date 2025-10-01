<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

        protected $fillable = [
        'mahasiswa_id','bulan','jumlah','status','kode',
        'semester','tahun_akademik','jatuh_tempo','bukti','uploaded_at',
    ];

    protected $table = 'invoices';

    protected $guarded = [];

    protected $casts = [
        'verified_at' => 'datetime',
        'created_at'  => 'datetime',
        'updated_at'  => 'datetime',
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
