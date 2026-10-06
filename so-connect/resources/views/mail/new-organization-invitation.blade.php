<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Organization Invitation</title>
</head>
<body>
    <p>Hello,</p>

    <p>A new organization, <strong>{{ $organizationName }}</strong>, has been registered on StudentConnect and you have been invited to become its {{ $role }}.</p>

    <p>Click the link below to complete your officer account and accept the role:</p>

    <p><a href="{{ $activationUrl }}">{{ $activationUrl }}</a></p>

    <p>This link will expire in 7 days. If you believe you received this by mistake, you may safely ignore it.</p>

    <p>— StudentConnect</p>
</body>
</html>
