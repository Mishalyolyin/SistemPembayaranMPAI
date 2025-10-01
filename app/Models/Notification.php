<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['mahasiswa_id', 'judul', 'pesan', 'dibaca'];

    public function mahasiswa()
    {
        return $this->belongsTo(Mahasiswa::class);
    }
}
