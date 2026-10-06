<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class Login extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'user_email' => ['required'],
            'user_password' => ['required'],
        ]);

        $credentials = [
            'user_email' => $validated['user_email'],
            'password' => $validated['user_password'],
        ];

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();

            $user = Auth::user();

            if (! $user->hasVerifiedEmail()) {
                Auth::logout();
                $request->session()->invalidate();

                return back()
                    ->withErrors(['user_email' => 'Your account has not been activated yet. Please check your email for an activation link.'])
                    ->onlyInput('user_email');
            }

            return redirect()->route('dashboard');
        }

        return back()
            ->withErrors(['user_email' => 'No records with the provided credentials.'])
            ->onlyInput('user_email');
    }
}
