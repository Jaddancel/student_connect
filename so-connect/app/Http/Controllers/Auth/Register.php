<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class Register extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $incomingFields = $request->validate([
            'fname' => ['required', 'string', 'max:255'],
            'lname' => ['required', 'string', 'max:255'],
            'user_email' => ['required', 'email', 'max:255', Rule::unique('users', 'user_email')],
            'user_password' => ['required', 'min:8'],
        ]);

        $loggedUser = User::create([
            'user_email' => $incomingFields['user_email'],
            'user_password' => $incomingFields['user_password'],
            'user_type' => 3,
        ]);

        Auth::login($loggedUser);
        $request->session()->regenerate();

        return redirect('/profile/create')->withInput([
            'fname' => $incomingFields['fname'],
            'lname' => $incomingFields['lname'],
        ]);
    }
}
