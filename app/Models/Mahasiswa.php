<?php

namespace App\Models;

use App\Models\Invoice; // relasi RPL
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// === penting untuk reset password ===
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Auth\Passwords\CanResetPassword;

class Mahasiswa extends Authenticatable implements CanResetPasswordContract
{
    use HasFactory, Notifiable, CanResetPassword;

    /**
     * (opsional, hanya dokumentatif)
     * Pastikan guard utk auth mahasiswa di config/auth.php adalah 'mahasiswa'
     * dan providernya menunjuk ke model ini.
     */
    protected $table = 'mahasiswas';

    protected $fillable = [
        'nama',
        'nim',
        'email',
        'no_hp',
        'alamat',
        'status',
        'angsuran',
        // 'total_tagihan', // sengaja tidak di-fillable agar tidak kebetulan tertimpa
        'semester_awal',
        'tahun_akademik',
        'bulan_mulai',
        'password',
        'foto',            // ✔ ikutkan foto kalau memang ada kolomnya
        'tanggal_upload',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'total_tagihan'     => 'int',
        'angsuran'          => 'int',
        'tanggal_upload'    => 'datetime',
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',  // Laravel akan hash otomatis saat set
        'created_at'        => 'datetime',
        'updated_at'        => 'datetime',
    ];

    /** =======================
     * Relasi
     * ======================= */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class, 'mahasiswa_id');
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

    /** =======================
     * Reset Password Notes
     * =======================
     * Dengan trait CanResetPassword, model sudah punya:
     * - getEmailForPasswordReset()
     * - sendPasswordResetNotification($token)
     *
     * Jika nanti kamu ingin custom URL email reset agar
     * mengarah ke route 'mahasiswa.password.reset',
     * set global callback di AppServiceProvider:
     *
     * use Illuminate\Auth\Notifications\ResetPassword;
     * ResetPassword::createUrlUsing(function ($notifiable, $token) {
     *     if ($notifiable instanceof \App\Models\Mahasiswa) {
     *         return url(route('mahasiswa.password.reset', [
     *             'token' => $token,
     *             'email' => $notifiable->getEmailForPasswordReset(),
     *         ], false));
     *     }
     *     return url(route('password.reset', ['token' => $token]));
     * });
     */
}
