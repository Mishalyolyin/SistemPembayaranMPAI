<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegulerSetting extends Model
{
    protected $table = 'settings_reguler'; // ✅ harus sama persis dengan migration

    protected $fillable = ['key', 'value'];
}
