<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KalenderEvent extends Model
{
    protected $table = 'kalender_events';

    protected $fillable = [
        'judul_event',
        'tanggal',
        'untuk',
    ];
}
