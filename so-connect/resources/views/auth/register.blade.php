<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - SOConnect</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex items-center justify-center bg-base-200 font-sans">
    <div class="card w-full max-w-md bg-base-100 shadow-lg">
        <div class="card-body">
            <h1 class="text-2xl font-bold text-center mb-2">Create an Account</h1>
            <p class="text-center text-sm text-base-content/60 mb-6">Join SOConnect today</p>

            @if ($errors->any())
                <div role="alert" class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <ul class="list-disc list-inside text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="/register" class="space-y-4">
                @csrf

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Name</label>
                    <input type="text" name="name" value="{{ old('name') }}" class="input input-bordered w-full"
                        placeholder="Enter your name" required autofocus />
                </fieldset>

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Email</label>
                    <input type="email" name="email" value="{{ old('email') }}" class="input input-bordered w-full"
                        placeholder="you@example.com" required />
                </fieldset>

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Password</label>
                    <input type="password" name="password" class="input input-bordered w-full"
                        placeholder="Enter your password" required />
                </fieldset>

                <button type="submit" class="btn btn-primary w-full text-white">Register</button>
            </form>

            <p class="text-center text-sm mt-4">
                Already have an account?
                <a href="{{ route('login') }}" class="link link-primary font-semibold">Sign In</a>
            </p>
        </div>
    </div>
     

</body>

</html>