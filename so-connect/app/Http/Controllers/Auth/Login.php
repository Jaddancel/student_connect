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

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->route('dashboard');
        }

        return back()
            ->withErrors(['user_email' => 'No records with the provided credentials.'])
            ->onlyInput('user_email');
    }
}
