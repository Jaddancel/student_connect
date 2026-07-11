<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Google sign-in</title>
</head>
<body style="font-family: system-ui, sans-serif; padding: 2rem; color: #374151;">
    <p>Finishing Google sign-in… you can close this window.</p>
    <script>
        (function () {
            var payload = Object.assign({ source: 'google-link' }, @json($payload));
            try {
                if (window.opener && !window.opener.closed) {
                    window.opener.postMessage(payload, window.location.origin);
                }
            } catch (e) { /* opener gone — nothing to do */ }
            window.close();
        })();
    </script>
</body>
</html>
