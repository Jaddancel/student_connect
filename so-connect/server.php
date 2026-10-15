<?php

/*
 * Router for `php artisan serve` (picked up automatically from the project
 * root in place of the framework's resources/server.php).
 *
 * The framework router returns `false` for files under public/, letting PHP's
 * built-in server send them as-is — and it drops any header() set before that,
 * so static assets went out without X-Content-Type-Options (ZAP finding). Here
 * existing static files are sent through Symfony's BinaryFileResponse with the
 * same hardening headers public/.htaccess applies under Apache.
 */

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\MimeTypes;

$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    $path = $publicPath.$uri;

    // Directories, PHP scripts and anything with a ".." segment keep the
    // built-in server's own handling (which confines requests to the docroot).
    if (! is_file($path)
        || preg_match('#(^|[/\\\\])\.\.([/\\\\]|$)#', $uri)
        || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php') {
        return false;
    }

    require __DIR__.'/vendor/autoload.php';

    // Type by extension, not content sniffing: under nosniff a stylesheet or
    // script labelled text/plain would be refused by the browser.
    $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $contentType = MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? 'application/octet-stream';

    $response = new BinaryFileResponse($path, 200, [
        'Content-Type' => $contentType,
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'SAMEORIGIN',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ], true, null, true, true);

    $request = Request::createFromGlobals();
    $response->prepare($request);
    $response->isNotModified($request);
    header_remove('X-Powered-By');
    $response->send();

    return true;
}

$formattedDateTime = date('D M j H:i:s Y');

$requestMethod = $_SERVER['REQUEST_METHOD'];
$remoteAddress = $_SERVER['REMOTE_ADDR'].':'.$_SERVER['REMOTE_PORT'];

file_put_contents('php://stdout', "[$formattedDateTime] $remoteAddress [$requestMethod] URI: $uri\n");

require_once $publicPath.'/index.php';
