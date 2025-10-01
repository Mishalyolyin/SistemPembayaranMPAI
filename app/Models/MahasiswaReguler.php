<?php

namespace App\Models;

use App\Models\InvoiceReguler;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// === penting untuk reset password ===
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;

class MahasiswaReguler extends Authenticatable implements CanResetPasswordContract
{
    use HasFactory, Notifiable, CanResetPassword;

    /**
     * Pastikan nama tabel sesuai migration kamu.
     * Jika migration-nya "mahasiswa_regulers", ganti di sini juga.
     */
    protected $table = 'mahasiswa_reguler';

    protected $fillable = [
        'nama',
        'nim',
        'email',
        'no_hp',
        'alamat',
        'status',
        'angsuran',
        // 'total_tagihan', // sengaja tidak di-fillable biar aman
        'semester_awal',
        'tahun_akademik',
        'bulan_mulai',
        'password',
        'foto',            // ✔ ikutkan kalau kolomnya ada
        'tanggal_upload',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'total_tagihan'     => 'int',
        'angsuran'          => 'int',
        'tanggal_upload'    => 'datetime',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',   // auto-hash saat set
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /** =======================
     * Relasi
     * ======================= */
    public function invoicesReguler(): HasMany
    {
        return $this->hasMany(InvoiceReguler::class, 'mahasiswa_reguler_id');
    }

    // Alias universal (biar with('invoices') tetep works)
    public function invoices(): HasMany
    {
        return $this->hasMany(InvoiceReguler::class, 'mahasiswa_reguler_id');
    }

    /** =======================
     * Accessor kecil: selalu int & aman saat null
     * ======================= */
    protected function totalTagihan(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => (int) ($value ?? 0)
        );
    }

    /**
     * Catatan:
     * - Dengan trait CanResetPassword, model ini sudah punya method
     *   getEmailForPasswordReset() & sendPasswordResetNotification($token).
     * - Di config/auth.php pastikan ada:
     *
     * 'providers' => [
     *   'mahasiswa_reguler' => ['driver' => 'eloquent', 'model' => \App\Models\MahasiswaReguler::class],
     * ],
     * 'guards' => [
     *   'mahasiswa_reguler' => ['driver' => 'session', 'provider' => 'mahasiswa_reguler'],
     * ],
     * 'passwords' => [
     *   'mahasiswa_reguler' => [
     *     'provider' => 'mahasiswa_reguler',
     *     'table' => 'password_reset_tokens',
     *     'expire' => 60, 'throttle' => 60,
     *   ],
     * ],
     */
}
