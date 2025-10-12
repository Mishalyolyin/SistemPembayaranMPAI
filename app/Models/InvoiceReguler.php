<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class InvoiceReguler extends Model
{
    use HasFactory;

    /** Nama tabel sesuai skema proyek */
    protected $table = 'invoices_reguler';

    /**
     * Tetap pakai guarded = [] sesuai kode kamu
     * (biar tidak merusak flow mass-assignment yang sudah ada).
     */
    protected $guarded = [];

    /**
     * Cast bawaan kamu dipertahankan,
     * plus tambahan yang aman: jumlah -> integer, jatuh_tempo -> date.
     */
    protected $casts = [
        'verified_at'   => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
        'jatuh_tempo'   => 'date',
        'jumlah'        => 'integer',
        // aman ditambah; kalau kolom nggak ada, Eloquent nggak error
        'va_expired_at' => 'datetime',
    ];

    /* -----------------------------------------------------------------
     | Boot: set default status + sapu atribut yang kolomnya gak ada
     * -----------------------------------------------------------------*/
    protected static function booted(): void
    {
        // default status 'Belum' kalau belum diisi
        static::creating(function (self $m) {
            if (empty($m->status)) {
                $m->status = 'Belum';
            }
        });

        // penyapu atribut opsional yang sering bikin error saat INSERT/UPDATE
        $stripIfNoColumn = function (self $m) {
            // daftar atribut opsional yang mungkin ada di model / request lama
            $maybeCols = ['keterangan', 'keterangan_tolak'];

            foreach ($maybeCols as $col) {
                if (! Schema::hasColumn($m->getTable(), $col)) {
                    // pastikan tidak ikut terkirim ke query
                    $m->offsetUnset($col);
                }
            }
        };

        static::creating($stripIfNoColumn);
        static::updating($stripIfNoColumn);
        static::saving($stripIfNoColumn); // jaga-jaga kalau ada save() langsung
    }

    /* -----------------------------------------------------------------
     | Relationships
     * -----------------------------------------------------------------*/
    /** Relasi balik ke mahasiswa reguler (nama method dipertahankan) */
    public function mahasiswaReguler(): BelongsTo
    {
        return $this->belongsTo(MahasiswaReguler::class, 'mahasiswa_reguler_id');
    }

    /** Alias opsional yang sering dipakai di view/controller */
    public function mahasiswa(): BelongsTo
    {
        return $this->belongsTo(MahasiswaReguler::class, 'mahasiswa_reguler_id');
    }

    /* -----------------------------------------------------------------
     | Accessors / Helpers
     * -----------------------------------------------------------------*/
    /**
     * Accessor opsional: pakai 'nominal' jika ada, fallback ke 'jumlah'.
     */
    public function getNominalFinalAttribute(): int
    {
        $n = $this->attributes['nominal'] ?? null;
        $j = $this->attributes['jumlah']  ?? null;
        return (int) ($n ?? $j ?? 0);
    }

    /** Format rupiah untuk jumlah (enak dipakai di Blade) */
    public function getJumlahFormattedAttribute(): string
    {
        $val = (int) ($this->attributes['jumlah'] ?? 0);
        return 'Rp ' . number_format($val, 0, ',', '.');
    }

    /**
     * URL bukti pembayaran (dukung 2 skema kolom: 'bukti_pembayaran' lama, atau 'bukti' baru)
     * - bukti_pembayaran: disimpan langsung di storage/public/...
     * - bukti          : disimpan di storage/public/bukti_reguler/...
     */
    public function getBuktiUrlAttribute(): ?string
    {
        if (!empty($this->attributes['bukti_pembayaran'])) {
            return asset('storage/' . ltrim($this->attributes['bukti_pembayaran'], '/'));
        }
        if (!empty($this->attributes['bukti'])) {
            return asset('storage/bukti_reguler/' . ltrim($this->attributes['bukti'], '/'));
        }
        return null;
    }

    /** Helper status */
    public function isPaid(): bool
    {
        return in_array($this->status, ['Lunas', 'Lunas (Otomatis)'], true);
    }

    public function isWaiting(): bool
    {
        return $this->status === 'Menunggu Verifikasi';
    }

    public function isRejected(): bool
    {
        return $this->status === 'Ditolak';
    }

    public function isUnpaid(): bool
    {
        return ! $this->isPaid();
    }

    /**
     * Kompat alias untuk Blade lama:
     * banyak view lama pakai `keterangan_tolak` padahal di DB namanya `alasan_tolak`.
     * Accessor ini hanya untuk dibaca (read-only), tidak ikut disimpan.
     */
    public function getKeteranganTolakAttribute()
    {
        return $this->attributes['alasan_tolak'] ?? null;
    }

    /* -----------------------------------------------------------------
     | Scopes (biar query lebih enak dipakai)
     * -----------------------------------------------------------------*/
    public function scopePaid(Builder $q): Builder
    {
        return $q->whereIn('status', ['Lunas', 'Lunas (Otomatis)']);
    }

    public function scopeWaiting(Builder $q): Builder
    {
        return $q->where('status', 'Menunggu Verifikasi');
    }

    public function scopeUnpaid(Builder $q): Builder
    {
        return $q->whereNotIn('status', ['Lunas', 'Lunas (Otomatis)']);
    }

    /** Filter cepat per mahasiswa */
    public function scopeForMahasiswa(Builder $q, int|string $mhsId): Builder
    {
        return $q->where('mahasiswa_reguler_id', $mhsId);
    }
}
