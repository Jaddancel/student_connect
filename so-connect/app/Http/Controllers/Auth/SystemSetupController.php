<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SystemSetupController extends Controller
{
    public function create()
    {
        // Defense-in-depth: the global middleware already locks this once a
        // superadmin exists, but guard here too.
        if (User::where('user_type', 1)->exists()) {
            return redirect()->route('home');
        }

        return view('pages.auth.superadmin-setup', [
            'title' => 'System Setup',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        // Guard against races / double-submit creating a second superadmin.
        if (User::where('user_type', 1)->exists()) {
            return redirect()->route('home');
        }

        $validated = $request->validate([
            'email'                 => ['required', 'email', 'max:255', 'unique:users,user_email'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $password = $validated['password'];

        $failures = [];
        if (! preg_match('/[A-Z]/', $password)) {
            $failures[] = 'at least one uppercase letter';
        }
        if (! preg_match('/[a-z]/', $password)) {
            $failures[] = 'at least one lowercase letter';
        }
        if (! preg_match('/[0-9]/', $password)) {
            $failures[] = 'at least one number';
        }
        if (! preg_match('/[^A-Za-z0-9]/', $password)) {
            $failures[] = 'at least one special character';
        }

        if (! empty($failures)) {
            throw ValidationException::withMessages([
                'password' => 'Password must contain ' . implode(', ', $failures) . '.',
            ]);
        }

        $user = User::create([
            'user_email'            => $validated['email'],
            'user_password'         => Hash::make($password),
            'user_type'             => 1,
            'profile'               => null,
            'profile_pending'       => false,
            'force_password_change' => false,
            'email_verified_at'     => now(),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('status', 'Superadmin account created. Welcome to StudentConnect!');
    }
}
