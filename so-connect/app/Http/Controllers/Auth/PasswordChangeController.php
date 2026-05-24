<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordChangeController extends Controller
{
    public function show(): \Illuminate\View\View
    {
        return view('pages.auth.change-password', ['title' => 'Set Your Password']);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $password = $request->input('password');

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

        $user = $request->user();
        $user->forceFill([
            'user_password'         => Hash::make($password),
            'force_password_change' => false,
        ])->save();

        return redirect()->route('admin-dashboard')
            ->with('status', 'Password set successfully. Welcome to StudentConnect!');
    }
}
