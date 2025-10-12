<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Set XSRF cookie? (optional)
     */
    protected $addHttpCookie = false;

    /**
     * Only exclude webhook endpoints.
     */
    protected $except = [
        'webhooks/*',
        'webhooks/bri*',
        'webhooks/bri/*',
    ];

}
