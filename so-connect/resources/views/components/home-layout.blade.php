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
            <a href="" class="btn btn-ghost ">Log In</a>
            <a href="" class="btn btn-primary text-white">Sign Up</a>
        </div>
    </div>
    <main class="flex items-center justify-center min-h-screen md:min-3/4">
        {{ $slot }}
    </main>
</body>

</html>