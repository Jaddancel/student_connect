<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex items-center justify-center bg-base-200 font-sans">
    <div class="card w-full max-w-md bg-base-100 shadow-lg ">
        <div class="card-body">
            <h1 class="text-2xl font-bold text-center mb-2">Welcome Back</h1>
            <p class="text-center text-sm text-base-content/60 mb-6">Sign in to SOConnect</p>

            @if ($errors->any())
                <div role="alert" class="alert alert-error mb-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ $errors->first() }}</span>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Email</label>
                    <input type="email" name="user_email" value="{{ old('user_email') }}"
                        class="input input-bordered w-full" placeholder="you@example.com" required autofocus />
                </fieldset>

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Password</label>
                    <input type="password" name="user_password" class="input input-bordered w-full"
                        placeholder="Enter your password" required />
                </fieldset>

                <div class="flex items-center justify-between">
                    <label class="label cursor-pointer gap-2">
                        <input type="checkbox" name="remember" class="checkbox checkbox-sm checkbox-primary" />
                        <span class="label-text text-sm">Remember me</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-full text-white">Sign In</button>
            </form>

            <p class="text-center text-sm mt-4">
                Don't have an account?
                <a href="{{ route('register') }}" class="link link-primary font-semibold">Register</a>
            </p>
        </div>
    </div>
</body>

</html>
