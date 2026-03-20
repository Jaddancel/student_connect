<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SOConnect</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5ede0 0%, #ede8e0 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .flip-container {
            perspective: 1000px;
            width: 100%;
            max-width: 24rem;
            padding: 0.5rem;
        }

        .flip-box {
            position: relative;
            width: 100%;
            transition: all 0.6s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            transform-style: preserve-3d;
        }

        .flip-box.flipped {
            transform: rotateY(180deg);
        }

        .flip-front,
        .flip-back {
            position: absolute;
            width: 100%;
            left: 0;
            top: 0;
            backface-visibility: hidden;
        }

        .flip-back {
            transform: rotateY(180deg);
        }

        .card {
            background: linear-gradient(135deg, #0d5f5a 0%, #1a7a72 100%) !important;
            border-radius: 18px !important;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15), 0 0 40px rgba(13, 95, 90, 0.2) !important;
            border: 2px solid #2f9b93 !important;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .card-body {
            padding: 1.25rem !important;
        }

        h1 {
            color: #f5ede0 !important;
            font-weight: 700;
            font-size: 1.45rem !important;
            margin-bottom: 0.2rem !important;
            text-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        }

        p {
            color: #b8d4d0;
            font-size: 0.7rem;
            font-weight: 500;
            margin-bottom: 0.7rem !important;
        }

        .fieldset {
            margin-bottom: 0.65rem;
            position: relative;
        }

        .fieldset-legend {
            color: #e0f2f0;
            font-size: 0.6rem;
            font-weight: 700;
            margin-bottom: 0.2rem;
            display: block;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .input {
            border: 2px solid rgba(255, 255, 255, 0.3) !important;
            border-radius: 10px !important;
            padding: 0.55rem 0.75rem !important;
            font-size: 0.82rem;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.95) !important;
            color: #333 !important;
            font-weight: 500;
        }

        .input:focus {
            border-color: #5eccc3 !important;
            background-color: white !important;
            box-shadow: 0 0 0 4px rgba(94, 204, 195, 0.15) !important;
            outline: none;
        }

        .input::placeholder {
            color: #999;
            font-weight: 400;
        }

        .checkbox {
            border-radius: 6px !important;
            border: 2px solid rgba(255, 255, 255, 0.4) !important;
            width: 16px !important;
            height: 16px !important;
        }

        .checkbox:checked {
            background-color: #5eccc3 !important;
            border-color: #5eccc3 !important;
        }

        .label {
            gap: 0.5rem !important;
        }

        .label-text {
            color: #c8dedb;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .btn {
            border-radius: 14px !important;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 0.7rem 1.25rem !important;
            transition: all 0.3s ease;
            border: none;
            font-size: 0.85rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #5eccc3, #2f9b93) !important;
            color: #f5ede0 !important;
            box-shadow: 0 10px 25px rgba(94, 204, 195, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 40px rgba(94, 204, 195, 0.4);
            background: linear-gradient(135deg, #7ee0d9, #3fa99a) !important;
        }

        .btn-primary:active {
            transform: translateY(-1px);
        }

        .link {
            color: #5eccc3 !important;
            text-decoration: none;
            transition: all 0.2s ease;
            font-weight: 700;
            cursor: pointer;
        }

        .link:hover {
            color: #7ee0d9 !important;
            text-decoration: underline;
        }

        .register-link {
            cursor: pointer;
            user-select: none;
        }

        .login-link {
            cursor: pointer;
            user-select: none;
        }

        .alert {
            border-radius: 12px !important;
            border: 2px solid #ffb3a1 !important;
            background-color: rgba(255, 179, 161, 0.1) !important;
            color: #ff8a73 !important;
            padding: 0.8rem !important;
            font-size: 0.8rem;
        }

        .alert-error {
            border-color: #ff8a73 !important;
        }

        .space-y-4 > * + * {
            margin-top: 0.5rem;
        }

        .flex {
            display: flex;
        }

        .items-center {
            align-items: center;
        }

        .justify-between {
            justify-content: space-between;
        }

        .gap-2 {
            gap: 0.35rem;
        }

        .mb-2 {
            margin-bottom: 0.2rem;
        }

        .mb-4 {
            margin-bottom: 0.6rem;
        }

        .mb-6 {
            margin-bottom: 0.8rem;
        }

        .mt-4 {
            margin-top: 0.6rem;
        }

        .text-center {
            text-align: center;
        }

        .text-sm {
            font-size: 0.875rem;
        }

        .text-2xl {
            font-size: 1.875rem;
        }

        .font-bold {
            font-weight: 700;
        }

        .font-semibold {
            font-weight: 600;
        }

        .w-full {
            width: 100%;
        }
    </style>
</head>

<body class="min-h-screen flex items-center justify-center bg-base-200 font-sans">
    <div class="flip-container">
        <div class="flip-box" id="flipBox">
            <!-- Login Form (Front) -->
            <div class="flip-front">
                <div class="card w-full bg-base-100 shadow-lg">
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
                            <span class="register-link link link-primary font-semibold">Register</span>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Register Form (Back) -->
            <div class="flip-back">
                <div class="card w-full bg-base-100 shadow-lg">
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
                            <span class="login-link link link-primary font-semibold">Sign In</span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const flipBox = document.getElementById('flipBox');
        const flipFront = document.querySelector('.flip-front');
        const flipBack = document.querySelector('.flip-back');
        const registerLinks = document.querySelectorAll('.register-link');
        const loginLinks = document.querySelectorAll('.login-link');

        function updateHeight() {
            if (flipBox.classList.contains('flipped')) {
                flipBox.style.height = flipBack.offsetHeight + 'px';
            } else {
                flipBox.style.height = flipFront.offsetHeight + 'px';
            }
        }

        registerLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                flipBox.classList.add('flipped');
                setTimeout(updateHeight, 300);
            });
        });

        loginLinks.forEach(link => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                flipBox.classList.remove('flipped');
                setTimeout(updateHeight, 300);
            });
        });

        // Initial height setup
        window.addEventListener('load', updateHeight);
        window.addEventListener('resize', updateHeight);
    </script>
</body>

</html>