<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class Register extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $incomingFields = $request->validate([
            'user_email' => ['required', 'min:3', Rule::unique('users', 'user_email')],
            'user_password' => ['required', 'min:6'],
        ]);

        $incomingFields['user_password'] = bcrypt($incomingFields['user_password']);
        $loggedUser = User::create($incomingFields);

        auth()->guard()->login($loggedUser);

        return redirect('/profile/create');
    }
}
