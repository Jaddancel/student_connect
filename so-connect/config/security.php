<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Strict Content-Security-Policy
    |--------------------------------------------------------------------------
    |
    | When enabled, SecurityHeaders enforces the nonce-based policy without
    | 'unsafe-inline' / 'unsafe-eval'. While disabled, that policy is sent as
    | Content-Security-Policy-Report-Only next to a permissive enforced one.
    | Enable only once `npm run check:csp` reports no problems and the assets
    | have been rebuilt with Alpine's CSP build.
    |
    */

    'csp_strict' => (bool) env('CSP_STRICT', false),

];
