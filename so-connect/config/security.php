<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Strict Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | Enabled by default: SecurityHeaders enforces the nonce-based policy
    | without 'unsafe-inline' / 'unsafe-eval' (views pass `npm run check:csp`
    | and Alpine runs from its CSP build). Setting CSP_STRICT=false falls back
    | to a permissive enforced policy and sends the strict one as
    | Content-Security-Policy-Report-Only — an escape hatch for debugging only.
    |
    */

    'csp_strict' => (bool) env('CSP_STRICT', true),

];
