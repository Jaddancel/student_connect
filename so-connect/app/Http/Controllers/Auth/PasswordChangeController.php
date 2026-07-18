<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Rules\StrongPassword;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class PasswordChangeController extends Controller
{
    public function show(): \Illuminate\View\View
    {
        return view('pages.auth.change-password', ['title' => 'Set Your Password']);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'password'              => ['required', 'string', new StrongPassword, 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $password = $request->input('password');

        $user = $request->user();
        $user->forceFill([
            'user_password'         => Hash::make($password),
            'force_password_change' => false,
        ])->save();

        return redirect()->route('admin-dashboard')
            ->with('status', 'Password set successfully. Welcome to StudentConnect!');
    }
}
