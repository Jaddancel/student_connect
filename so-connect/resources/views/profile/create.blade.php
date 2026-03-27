<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Profile </title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen flex items-center justify-center bg-base-200 font-sans">
    <div class="card w-full max-w-md bg-base-100 shadow-lg">
        <div class="card-body">
            <h1 class="text-2xl font-bold text-center mb-2">Create Your Profile</h1>
            <p class="text-center text-sm text-base-content/60 mb-6">Complete your profile information</p>

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

            <form method="POST" action="{{ route('profile.make_profile') }}" class="space-y-4">
                @csrf

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">First Name</label>
                    <input type="text" name="first_name" value="{{ old('first_name') }}"
                        class="input input-bordered w-full" placeholder="John" required />
                </fieldset>

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Last Name</label>
                    <input type="text" name="last_name" value="{{ old('last_name') }}"
                        class="input input-bordered w-full" placeholder="Doe" required />
                </fieldset>

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Middle Name</label>
                    <input type="text" name="middle_name" value="{{ old('middle_name') }}"
                        class="input input-bordered w-full" placeholder="(Optional)" />
                </fieldset>

                <fieldset class="fieldset">
                    <label class="fieldset-legend text-sm font-semibold">Occupation</label>
                    <select name="occupation" class="select select-bordered w-full" required>
                        <option value="" disabled selected>Select your occupation</option>
                        <option value="student" {{ old('occupation') == 'student' ? 'selected' : '' }}>Student</option>
                        <option value="faculty" {{ old('occupation') == 'faculty' ? 'selected' : '' }}>Faculty</option>
                    </select>
                </fieldset>

                <button type="submit" class="btn btn-primary w-full text-white">Create Profile</button>
            </form>
        </div>
    </div>
</body>

</html>
