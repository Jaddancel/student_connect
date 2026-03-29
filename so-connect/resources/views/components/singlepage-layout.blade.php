<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>{{ isset($title) ? $title . ' ' : 'SOConnect' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex flex-col font-sans m-8">
    <h1 class="text-xl font-black pb-3">{{ $title }}</h1>
    <main class="bg-base-200 rounded-box p-10">
        {{ $slot }}
    </main>

</body>

</html>
