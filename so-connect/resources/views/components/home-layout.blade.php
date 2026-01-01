<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>SOConnect</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex flex-col bg-[#fafaf6] font-sans">
    <div class="navbar bg-base-100 shadow-sm">
        <div class="flex-1">
            <a href="#" class="btn btn-ghost text-xl">SOConnect</a>
        </div>
        <div class="flex-none">
            <button class="btn btn-ghost ">Log In</button>
            <button class="btn btn-primary">Sign Up</button>
        </div>
    </div>
    <main>
        {{ $slot }}
    </main>
</body>

</html>