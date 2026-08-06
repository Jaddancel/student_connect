<?php

return [
    /*
     * Where the browser loads the editor script from. Host-visible, because it
     * is the user's browser that fetches it — not the app container. Leave
     * blank to disable the in-browser editor entirely; the form builder then
     * falls back to reporting the editor as unavailable rather than rendering
     * a broken iframe.
     */
    'public_url' => env('ONLYOFFICE_PUBLIC_URL'),

    /*
     * Where Laravel reaches the Document Server (command/conversion service),
     * over the compose network.
     */
    'internal_url' => env('ONLYOFFICE_INTERNAL_URL', 'http://onlyoffice'),

    /*
     * Where the Document Server reaches Laravel to download a template and
     * post it back. This is the app's *container* hostname, not localhost —
     * localhost inside that container is the Document Server itself.
     */
    'app_url' => env('ONLYOFFICE_APP_URL', 'http://laravel.test'),

    /*
     * Shared secret for signing every payload in both directions. Must match
     * JWT_SECRET on the onlyoffice service in compose.yaml.
     */
    'jwt_secret' => env('ONLYOFFICE_JWT_SECRET', ''),

    'timeout' => (int) env('ONLYOFFICE_TIMEOUT', 60),

    /*
     * Lifetime of the signed document-download URL handed to the Document
     * Server. Long enough to cover a slow fetch, short enough that a leaked
     * link expires; the editing session itself is not bounded by this.
     */
    'download_ttl_minutes' => (int) env('ONLYOFFICE_DOWNLOAD_TTL', 30),
];
