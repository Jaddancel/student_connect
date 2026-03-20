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
            'name' => ['required', 'min:3', 'max:23', Rule::unique('users', 'name')],
            'email' => ['required', 'min:3', Rule::unique('users', 'email')],
            'password' => 'required',
        ]);

        $incomingFields['password'] = bcrypt($incomingFields['password']);
        $loggedUser = User::create($incomingFields);

        auth()->guard()->login($loggedUser);

        return redirect('/student_dashboard');
    }
}
