<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken as Middleware;

/**
 * CSRF validation without the XSRF-TOKEN cookie.
 *
 * Laravel issues XSRF-TOKEN without HttpOnly so JavaScript HTTP clients
 * (axios) can echo it back in X-XSRF-TOKEN. This app never does that: forms
 * post the `_token` field and fetch() calls send the `csrf-token` meta value
 * as X-CSRF-TOKEN. The cookie only gave injected scripts something to read
 * (ZAP: Cookie No HttpOnly Flag), so it is no longer sent.
 */
class ValidateCsrfToken extends Middleware
{
    protected $addHttpCookie = false;
}
