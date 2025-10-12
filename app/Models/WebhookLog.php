<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WebhookLog extends Model
{
    protected $table = 'webhook_logs';

    protected $fillable = [
        'endpoint', 'headers', 'payload', 'signature', 'status_code', 'note', 'resolved_table'
    ];

    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
    ];
}
